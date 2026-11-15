<?php

namespace App\Jobs;

use App\Audience\Audience;
use App\Models\Message;
use App\Models\MessagePlatform;
use App\Models\Subscription;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        foreach (Subscription::TYPES as $type) {
            $query = $audience->query($project, $message->targeting)->where('subscriptions.type', $type);
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

            $query->select('subscriptions.id')->chunkById(self::BATCH_SIZE, function ($chunk) use (&$jobs, $type) {
                $jobs[] = new SendBatch($this->messageId, $type, $chunk->pluck('id')->all());
            }, 'subscriptions.id', 'id');
        }

        $message->forceFill(['recipients' => $total])->save();

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

    public static function complete(string $messageId, ?string $error = null): void
    {
        DB::table('messages')->where('id', $messageId)->where('status', 'sending')->update([
            'status' => 'sent',
            'completed_at' => now(),
            'error' => $error,
            'updated_at' => now(),
        ]);
    }
}
