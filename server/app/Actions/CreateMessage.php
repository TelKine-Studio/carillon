<?php

namespace App\Actions;

use App\Audience\Audience;
use App\Jobs\DispatchMessage;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Push\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a message from the OneSignal "Create notification" format
 * (POST /notifications), used by the API and the dashboard.
 */
class CreateMessage
{
    /** Fields stored as options of the message (everything but texts and targeting). */
    public const OPTIONS = [
        'data', 'url', 'web_url', 'app_url', 'big_picture', 'ios_attachments', 'chrome_web_image', 'chrome_web_icon',
        'chrome_web_badge', 'firefox_icon', 'buttons', 'web_buttons', 'ios_sound', 'android_sound', 'ios_badgeType',
        'ios_badgeCount', 'thread_id', 'ios_category', 'ios_interruption_level', 'ios_relevance_score',
        'android_channel_id', 'android_accent_color', 'small_icon', 'large_icon', 'android_group', 'android_visibility',
        'priority', 'ttl', 'collapse_id', 'web_push_topic', 'content_available', 'require_interaction',
        'delayed_option', 'delivery_time_of_day',
    ];

    /** Recipients that can be listed explicitly in one call. */
    public const MAX_IDS = 20000;

    public function __construct(protected Audience $audience) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Project $project, array $input, ?User $author = null, ?ApiKey $apiKey = null): Message
    {
        $this->validate($input);

        if (filled($input['idempotency_key'] ?? $input['external_id'] ?? null)) {
            $key = (string) ($input['idempotency_key'] ?? $input['external_id']);
            if ($existing = $project->messages()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
        }

        $targeting = $this->targeting($input);
        $sendAfter = $this->sendAfter($input['send_after'] ?? null);

        $options = Arr::only($input, self::OPTIONS);
        // ios_attachments {"id": "https://..."}: the first image is the picture.
        if (! isset($options['big_picture']) && is_array($options['ios_attachments'] ?? null)) {
            $options['big_picture'] = reset($options['ios_attachments']) ?: null;
        }
        $options['ttl'] ??= $project->setting('default_ttl');
        // Only the time zone option changes the delivery ("last-active" is
        // sent right away).
        if (($options['delayed_option'] ?? null) === 'timezone') {
            $options['delivery_time_of_day'] = LocalTime::parse($options['delivery_time_of_day'])->format();
        } else {
            unset($options['delayed_option'], $options['delivery_time_of_day']);
        }
        $options = array_filter($options, fn ($value) => $value !== null);

        $message = new Message([
            'project_id' => $project->id,
            'created_by' => $author?->id,
            'api_key_id' => $apiKey?->id,
            'name' => $input['name'] ?? null,
            'idempotency_key' => $input['idempotency_key'] ?? $input['external_id'] ?? null,
            'headings' => $this->texts($input['headings'] ?? null),
            'subtitle' => $this->texts($input['subtitle'] ?? null),
            'contents' => $this->texts($input['contents'] ?? null) ?? [],
            'options' => $options ?: null,
            'targeting' => $targeting,
            'status' => $sendAfter ? 'scheduled' : 'queued',
            'send_after' => $sendAfter,
        ]);
        $message->setRelation('project', $project);

        // Fails early on an unknown segment or filter.
        $recipients = $this->audience->query($project, $targeting)->count();

        if ($recipients === 0 && ! $sendAfter) {
            throw ValidationException::withMessages(['recipients' => 'All included players are not subscribed']);
        }

        $message->recipients = $recipients;
        $message->save();

        if (! $sendAfter) {
            DispatchMessage::dispatch($message->id);
        }

        return $message;
    }

    /** @param array<string, mixed> $input */
    protected function validate(array $input): void
    {
        $text = ['nullable', 'array'];

        Validator::make($input, [
            'contents' => [empty($input['content_available']) ? 'required' : 'nullable', 'array', 'min:1'],
            'contents.*' => ['nullable', 'string', 'max:4000'],
            'headings' => $text, 'headings.*' => ['nullable', 'string', 'max:500'],
            'subtitle' => $text, 'subtitle.*' => ['nullable', 'string', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'array'],
            'url' => ['nullable', 'string', 'max:2000'],
            'web_url' => ['nullable', 'string', 'max:2000'],
            'app_url' => ['nullable', 'string', 'max:2000'],
            'big_picture' => ['nullable', 'string', 'max:2000'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.id' => ['required_with:buttons', 'string', 'max:64'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:100'],
            'web_buttons' => ['nullable', 'array', 'max:2'],
            'priority' => ['nullable', 'integer', 'between:1,10'],
            'ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
            'collapse_id' => ['nullable', 'string', 'max:64'],
            'ios_badgeType' => ['nullable', 'in:None,SetTo,Increase'],
            'ios_badgeCount' => ['nullable', 'integer'],
            'included_segments' => ['nullable', 'array'], 'included_segments.*' => ['string', 'max:255'],
            'excluded_segments' => ['nullable', 'array'], 'excluded_segments.*' => ['string', 'max:255'],
            'filters' => ['nullable', 'array', 'max:200'],
            'include_subscription_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_player_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_external_user_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_aliases' => ['nullable', 'array'],
            'include_aliases.external_id' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'target_channel' => ['nullable', 'in:push'],
            'send_after' => ['nullable', 'string', 'max:100'],
            'delayed_option' => ['nullable', 'in:timezone,last-active,immediate'],
            'delivery_time_of_day' => ['nullable', 'string', 'max:16'],
        ], [
            'contents.required' => 'Message Notifications must have English language content',
            'target_channel.in' => 'Only push notifications are supported.',
        ])->after(function ($validator) use ($input) {
            if (($input['delayed_option'] ?? null) === 'timezone' && ! LocalTime::parse($input['delivery_time_of_day'] ?? null)) {
                $validator->errors()->add('delivery_time_of_day', 'delivery_time_of_day must be a time of day like "9:00AM" when delayed_option is "timezone".');
            }
        })->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function targeting(array $input): array
    {
        $ids = array_values(array_unique(array_merge($input['include_subscription_ids'] ?? [], $input['include_player_ids'] ?? [])));
        $externalIds = array_values(array_unique(array_map('strval', array_merge(
            $input['include_aliases']['external_id'] ?? [],
            $input['include_external_user_ids'] ?? [],
        ))));
        $segments = array_values($input['included_segments'] ?? []);
        $filters = array_values($input['filters'] ?? []);

        if ($ids === [] && $externalIds === [] && $segments === [] && $filters === []) {
            throw ValidationException::withMessages(['targeting' => 'You must include which players, segments, or tags you wish to send this notification to.']);
        }

        // Platform flags of OneSignal: only those set to true are kept.
        $flags = ['ios' => $input['isIos'] ?? null, 'android' => $input['isAndroid'] ?? null, 'web' => $input['isAnyWeb'] ?? $input['isChromeWeb'] ?? null];
        $platforms = array_keys(array_filter($flags, fn ($value) => $value === true || $value === 'true' || $value === 1));

        return array_filter([
            'subscription_ids' => $ids,
            'external_ids' => $externalIds,
            'segments' => $segments,
            'excluded_segments' => array_values($input['excluded_segments'] ?? []),
            'filters' => $filters,
            'platforms' => $platforms,
        ]);
    }

    protected function sendAfter(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            throw ValidationException::withMessages(['send_after' => 'Invalid date for send_after.']);
        }

        return $date->isFuture() ? $date->utc() : null;
    }

    /** @return array<string, string>|null */
    protected function texts(mixed $texts): ?array
    {
        if (! is_array($texts)) {
            return null;
        }

        $clean = [];
        foreach ($texts as $language => $text) {
            if (is_string($text) && trim($text) !== '') {
                $clean[strtolower(substr((string) $language, 0, 7))] = $text;
            }
        }

        return $clean ?: null;
    }
}
