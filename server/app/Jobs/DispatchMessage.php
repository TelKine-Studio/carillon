<?php

namespace App\Jobs;

use App\Audience\Audience;
use App\Models\Message;
use App\Models\MessagePlatform;
use App\Models\Subscription;
use App\Push\LocalTime;
use App\Webhooks\Webhooks;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the audience of a message and splits it into batches of devices
 * per platform, sent in parallel by SendBatch jobs.
 */
class DispatchMessage implements ShouldQueue
{
    use Queueable;

    public const BATCH_SIZE = 500;

    public int $tries = 1;

    public function __construct(public string $messageId) {}

    public function handle(Audience $audience): void
    {
        // Claims the message once, even if dispatched twice.
        $claimed = Message::query()->whereKey($this->messageId)->whereIn('status', ['queued', 'scheduled'])
            ->update(['status' => 'sending', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }

        $message = Message::query()->with('project.credentials')->findOrFail($this->messageId);
        $project = $message->project;
        $jobs = [];
        $total = 0;
        $localTime = $message->option('delayed_option') === 'timezone' ? LocalTime::parse($message->option('delivery_time_of_day')) : null;

        // Daily cap of the project: devices that already received that many
        // notifications in the last 24 hours are left out (tests are not capped).
        $cap = (int) $project->setting('frequency_cap');
        $capped = 0;

        foreach (in_array($message->channel, ['email', 'sms'], true) ? [$message->channel] : Subscription::PUSH_TYPES as $type) {
            $query = $audience->query($project, $message->targeting)->where('subscriptions.type', $type);
            if ($cap > 0 && ! $message->is_test && in_array($type, Subscription::PUSH_TYPES, true)) {
                $before = (clone $query)->count();
                $query->whereNotIn('subscriptions.id', DB::table('deliveries')
                    ->select('subscription_id')
                    ->where('status', 'sent')
                    ->where('sent_at', '>=', now()->subDay())
                    ->groupBy('subscription_id')
                    ->havingRaw('count(*) >= ?', [$cap]));
                $capped += $before - (clone $query)->count();
            }
            $count = (clone $query)->count();
            if ($count === 0) {
                continue;
            }

            $total += $count;
            $credential = $project->credential(Subscription::PROVIDERS[$type]);

            if (! $credential?->isUsable()) {
                // No keys for this platform: counted as errored, nothing sent.
                MessagePlatform::query()->create(['message_id' => $message->id, 'platform' => $type, 'recipients' => $count, 'errored' => $count]);
                $message->increment('errored', $count);

                continue;
            }

            MessagePlatform::query()->create(['message_id' => $message->id, 'platform' => $type, 'recipients' => $count]);

            if ($localTime) {
                $this->perTimezone($query, $type, $localTime, $jobs);

                continue;
            }

            $query->select('subscriptions.id')->chunkById(self::BATCH_SIZE, function ($chunk) use (&$jobs, $type) {
                $jobs[] = new SendBatch($this->messageId, $type, $chunk->pluck('id')->all());
            }, 'subscriptions.id', 'id');
        }

        $message->forceFill(['recipients' => $total, 'capped' => $capped])->save();

        if ($jobs === []) {
            $this->complete($message->id, $total === 0 ? 'No subscribed device matches the audience.' : null);

            return;
        }

        $messageId = $message->id;
        Bus::batch($jobs)
            ->name('message:'.$messageId)
            ->allowFailures()
            ->finally(fn (Batch $batch) => static::complete($messageId, $batch->failedJobs > 0 ? 'Some batches failed: '.$batch->failedJobs : null))
            ->dispatch();
    }

    /**
     * Batches per time zone, each delayed until the time of day there.
     *
     * @param  list<SendBatch>  $jobs
     */
    protected function perTimezone(Builder $query, string $type, LocalTime $time, array &$jobs): void
    {
        $now = now();
        $groups = [];
        $at = [];

        $flush = function (string $zone) use (&$groups, &$jobs, &$at, $now, $type) {
            $job = new SendBatch($this->messageId, $type, $groups[$zone]);
            // A minute of tolerance: sent right away when it is about time.
            if ($at[$zone]->greaterThan($now->copy()->addMinute())) {
                $job->delay($at[$zone]);
            }
            $jobs[] = $job;
            $groups[$zone] = [];
        };

        $query->select('subscriptions.id', 'subscribers.timezone')->chunkById(2000, function ($chunk) use (&$groups, &$at, $flush, $time, $now) {
            foreach ($chunk as $row) {
                $zone = $row->timezone ?: 'UTC';
                $at[$zone] ??= $time->nextIn($zone, $now);
                $groups[$zone][] = $row->id;
                if (count($groups[$zone]) >= self::BATCH_SIZE) {
                    $flush($zone);
                }
            }
        }, 'subscriptions.id', 'id');

        foreach (array_keys($groups) as $zone) {
            if ($groups[$zone] !== []) {
                $flush($zone);
            }
        }
    }

    public static function complete(string $messageId, ?string $error = null): void
    {
        $completed = DB::table('messages')->where('id', $messageId)->where('status', 'sending')->update([
            'status' => 'sent',
            'completed_at' => now(),
            'error' => $error,
            'updated_at' => now(),
        ]);

        if ($completed && $message = Message::query()->find($messageId)) {
            Webhooks::dispatch($message->project_id, 'message.sent', array_filter([
                'message_id' => $message->id,
                'name' => $message->name,
                'template_id' => $message->template_id,
                'recipients' => $message->recipients,
                'successful' => $message->successful,
                'failed' => $message->failed,
                'errored' => $message->errored,
                'error' => $message->error,
                'completed_at' => $message->completed_at?->toIso8601String(),
            ], fn ($value) => $value !== null));
        }
    }
}
