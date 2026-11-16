<?php

namespace App\Journeys;

use App\Actions\CreateMessage;
use App\Audience\Audience;
use App\Models\Journey;
use App\Models\JourneyEntry;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs the journeys (automations): people enter on a trigger, then go
 * through the steps on their own. The scheduler calls run() every minute;
 * people reaching the same "send" step in the same run share one message.
 */
class JourneyRunner
{
    /** People handled per run (the rest waits for the next minute). */
    public const BATCH = 2000;

    /** Longest wait of a step, in minutes (90 days). */
    public const MAX_WAIT = 129600;

    public function __construct(protected CreateMessage $create, protected Audience $audience) {}

    /** A trigger happened for a person: they enter the matching active journeys. */
    public function trigger(Project $project, string $type, Subscriber $subscriber, array $context = []): void
    {
        $journeys = Journey::query()
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (Journey $journey) => ($journey->trigger['type'] ?? null) === $type && $this->matches($journey->trigger, $context));

        foreach ($journeys as $journey) {
            $this->enter($journey, $subscriber);
        }
    }

    /** Tags of a person changed: journeys triggered by one of the new values start. */
    public function tagsChanged(Subscriber $subscriber, ?array $before): void
    {
        $changed = array_diff_assoc(array_map('strval', $subscriber->tags ?? []), array_map('strval', $before ?? []));
        if ($changed !== []) {
            $this->trigger($subscriber->project, 'tag', $subscriber, ['tags' => $changed]);
        }
    }

    /** Tags and events must match the condition of the trigger. */
    protected function matches(array $trigger, array $context): bool
    {
        return match ($trigger['type']) {
            'tag' => array_key_exists($trigger['key'] ?? '', $context['tags'] ?? [])
                && (blank($trigger['value'] ?? null) || (string) $context['tags'][$trigger['key']] === (string) $trigger['value']),
            'event' => ($context['event'] ?? null) === ($trigger['event'] ?? null),
            default => true,
        };
    }

    public function enter(Journey $journey, Subscriber $subscriber): ?JourneyEntry
    {
        if (! $journey->isActive() || $subscriber->project_id !== $journey->project_id) {
            return null;
        }

        $previous = $journey->entries()->where('subscriber_id', $subscriber->id);
        if ((clone $previous)->where('status', 'active')->exists() || (! $journey->reentry && (clone $previous)->exists())) {
            return null;
        }

        if ($journey->segment && ! $this->inSegment($journey, $subscriber)) {
            return null;
        }

        return $journey->entries()->create([
            'subscriber_id' => $subscriber->id,
            'step' => 0,
            'status' => 'active',
            'next_run_at' => now(),
        ]);
    }

    protected function inSegment(Journey $journey, Subscriber $subscriber): bool
    {
        try {
            return $this->audience->query($journey->project, ['segments' => [$journey->segment]])
                ->where('subscriptions.subscriber_id', $subscriber->id)
                ->exists();
        } catch (ValidationException) {
            return false;
        }
    }

    /** People inactive for the number of days of an "inactive" journey enter it. */
    public function sweepInactive(): int
    {
        $entered = 0;

        Journey::query()->where('status', 'active')->get()
            ->filter(fn (Journey $journey) => ($journey->trigger['type'] ?? null) === 'inactive')
            ->each(function (Journey $journey) use (&$entered) {
                $limit = now()->subDays(max(1, (int) ($journey->trigger['days'] ?? 7)));

                Subscriber::query()
                    ->where('project_id', $journey->project_id)
                    ->whereNotNull('last_active_at')
                    ->where('last_active_at', '<=', $limit)
                    // Not already in it since their last activity.
                    ->whereNotExists(fn ($query) => $query->from('journey_entries')
                        ->whereColumn('journey_entries.subscriber_id', 'subscribers.id')
                        ->where('journey_entries.journey_id', $journey->id)
                        ->when($journey->reentry, fn ($q) => $q->whereColumn('journey_entries.created_at', '>=', 'subscribers.last_active_at')))
                    ->limit(self::BATCH)
                    ->get()
                    ->each(function (Subscriber $subscriber) use ($journey, &$entered) {
                        $entered += $this->enter($journey, $subscriber) ? 1 : 0;
                    });
            });

        return $entered;
    }

    /** Moves every person whose step is due; returns the number of people handled. */
    public function run(): int
    {
        $entries = JourneyEntry::query()
            ->with(['journey.project', 'subscriber'])
            ->where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(self::BATCH)
            ->get();

        foreach ($entries->groupBy('journey_id') as $group) {
            $this->advance($group->first()->journey, $group);
        }

        return $entries->count();
    }

    /** @param Collection<int, JourneyEntry> $entries */
    protected function advance(Journey $journey, Collection $entries): void
    {
        // A paused journey keeps people where they are.
        if (! $journey->isActive()) {
            return;
        }

        $steps = array_values($journey->steps ?? []);
        $sends = [];

        foreach ($entries as $entry) {
            // Instant steps run one after the other; a wait stops the person.
            while (true) {
                $step = $steps[$entry->step] ?? null;

                if ($step === null) {
                    $entry->forceFill(['status' => 'completed', 'completed_at' => now(), 'next_run_at' => null])->save();
                    break;
                }

                if ($step['type'] === 'wait') {
                    $entry->forceFill(['step' => $entry->step + 1, 'next_run_at' => now()->addMinutes(min(self::MAX_WAIT, max(1, (int) ($step['minutes'] ?? 60))))])->save();
                    break;
                }

                if ($step['type'] === 'condition' && ! $this->passes($step, $entry->subscriber)) {
                    $entry->forceFill(['status' => 'exited', 'completed_at' => now(), 'next_run_at' => null])->save();
                    break;
                }

                if (in_array($step['type'], ['push', 'email'], true)) {
                    $sends[$entry->step][] = $entry->subscriber_id;
                }
                $entry->step++;
            }
        }

        foreach ($sends as $index => $subscribers) {
            $this->send($journey, $index, $steps[$index], $subscribers);
        }
    }

    /** Condition step: the person has (or has not) a tag value. */
    protected function passes(array $step, ?Subscriber $subscriber): bool
    {
        $value = $subscriber?->tags[$step['key'] ?? ''] ?? null;
        $expected = (string) ($step['value'] ?? '');

        $match = $expected === '' ? $value !== null && $value !== '' : (string) $value === $expected;

        return ($step['operator'] ?? 'is') === 'is_not' ? ! $match : $match;
    }

    /** One message for everybody reaching this step in this run. */
    protected function send(Journey $journey, int $index, array $step, array $subscribers): void
    {
        $email = $step['type'] === 'email';
        $devices = Subscription::query()
            ->whereIn('subscriber_id', array_unique($subscribers))
            ->where('enabled', true)
            ->whereNull('invalid_at')
            ->when($email, fn ($q) => $q->where('type', 'email'), fn ($q) => $q->whereIn('type', Subscription::PUSH_TYPES))
            ->pluck('id')
            ->all();

        if ($devices === []) {
            return;
        }

        $content = filled($step['template_id'] ?? null)
            ? ['template_id' => $step['template_id']]
            : ($email
                ? ['target_channel' => 'email', 'email_subject' => $step['subject'] ?? '', 'email_body' => $step['body'] ?? '']
                : array_filter([
                    'headings' => filled($step['title'] ?? null) ? ['en' => $step['title']] : null,
                    'contents' => ['en' => (string) ($step['body'] ?? '')],
                    'url' => $step['url'] ?? null,
                ]));

        try {
            foreach (array_chunk($devices, CreateMessage::MAX_IDS) as $chunk) {
                $message = $this->create->handle($journey->project, [
                    ...$content,
                    'name' => $journey->name.' · '.__('Step :number', ['number' => $index + 1]),
                    'include_subscription_ids' => $chunk,
                ]);
                $message->forceFill(['journey_id' => $journey->id, 'journey_step' => $index])->save();
            }
        } catch (ValidationException|Throwable $e) {
            report($e);
        }
    }
}
