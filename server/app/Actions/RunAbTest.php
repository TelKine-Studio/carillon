<?php

namespace App\Actions;

use App\Audience\Audience;
use App\Models\AbTest;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A/B test: version A and version B each go to half of a random sample of
 * the audience; the version chosen afterwards goes to the rest.
 */
class RunAbTest
{
    /** Sample sizes offered, in percent of the audience. */
    public const SAMPLES = [10, 20, 30, 50];

    /** Smallest audience worth testing. */
    public const MIN_AUDIENCE = 20;

    /** Targeting keys replaced by explicit lists of devices. */
    protected const TARGETING = ['included_segments', 'excluded_segments', 'include_aliases', 'include_subscription_ids', 'filters', 'isIos', 'isAndroid', 'isAnyWeb', 'send_after', 'delayed_option', 'delivery_time_of_day'];

    public function __construct(protected CreateMessage $create, protected Audience $audience) {}

    /**
     * @param  array<string, mixed>  $input  the message (version A), in the OneSignal format
     * @param  array{headings: ?array, contents: array}  $variant  the texts of version B
     */
    public function start(Project $project, array $input, array $variant, int $sample, ?User $author = null): AbTest
    {
        if (! in_array($sample, self::SAMPLES, true)) {
            throw ValidationException::withMessages(['ab_sample' => __('Choose the size of the sample.')]);
        }
        if (filled($input['send_after'] ?? null) || ($input['delayed_option'] ?? null) === 'timezone') {
            throw ValidationException::withMessages(['ab_sample' => __('An A/B test is sent right away: choose “Send now”.')]);
        }

        $devices = $this->audience->query($project, $this->create->targeting($input))->pluck('subscriptions.id')->shuffle()->values();
        if ($devices->count() < self::MIN_AUDIENCE) {
            throw ValidationException::withMessages(['ab_sample' => __('An A/B test needs at least :count devices in the audience.', ['count' => self::MIN_AUDIENCE])]);
        }
        if ($devices->count() > CreateMessage::MAX_IDS) {
            throw ValidationException::withMessages(['ab_sample' => __('This audience is too large for an A/B test (:count devices at most).', ['count' => number_format(CreateMessage::MAX_IDS, 0, ',', ' ')])]);
        }

        $half = max(1, (int) round($devices->count() * $sample / 100 / 2));
        [$a, $b, $rest] = [$devices->slice(0, $half), $devices->slice($half, $half), $devices->slice($half * 2)];
        $base = array_diff_key($input, array_flip(self::TARGETING));

        return DB::transaction(function () use ($project, $author, $input, $base, $variant, $sample, $a, $b, $rest) {
            $test = AbTest::query()->create([
                'project_id' => $project->id,
                'created_by' => $author?->id,
                'name' => $input['name'] ?? null,
                'sample' => $sample,
                'remaining' => $rest->values()->all(),
            ]);

            foreach (['A' => [$base, $a], 'B' => [array_replace($base, array_filter($variant)), $b]] as $letter => [$content, $ids]) {
                $message = $this->create->handle($project, [
                    ...$content,
                    'name' => trim(($input['name'] ?? '').' · '.$letter, ' ·'),
                    'include_subscription_ids' => $ids->values()->all(),
                ], $author);
                $message->forceFill(['ab_test_id' => $test->id, 'ab_variant' => $letter])->save();
            }

            return $test;
        });
    }

    /** Sends the chosen version to the devices left out of the sample. */
    public function sendWinner(AbTest $test, Message $winner, ?User $author = null): Message
    {
        if ($test->winner_id) {
            throw ValidationException::withMessages(['alert' => __('The winning version has already been sent.')]);
        }
        if ($winner->ab_test_id !== $test->id || ! in_array($winner->ab_variant, ['A', 'B'], true)) {
            throw ValidationException::withMessages(['alert' => __('Unknown version.')]);
        }
        if ($test->remaining === []) {
            throw ValidationException::withMessages(['alert' => __('The sample was the whole audience: nobody is left.')]);
        }

        $message = $this->create->handle($test->project, [
            ...($winner->options ?? []),
            'headings' => $winner->headings,
            'subtitle' => $winner->subtitle,
            'contents' => $winner->contents,
            'name' => trim(($test->name ?? '').' · '.__('Winner :letter', ['letter' => $winner->ab_variant]), ' ·'),
            'include_subscription_ids' => $test->remaining,
        ], $author);
        $message->forceFill(['ab_test_id' => $test->id, 'ab_variant' => 'W'])->save();
        $test->forceFill(['winner_id' => $winner->id])->save();

        return $message;
    }
}
