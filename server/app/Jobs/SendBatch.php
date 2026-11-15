<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Subscription;
use App\Push\ApnsSender;
use App\Push\FcmSender;
use App\Push\Result;
use App\Push\Sender;
use App\Push\WebPushSender;
use App\Webhooks\Webhooks;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Sends a message to up to 500 devices of one platform. */
class SendBatch implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @param list<string> $subscriptionIds */
    public function __construct(public string $messageId, public string $type, public array $subscriptionIds) {}

    public function handle(): void
    {
        $message = Message::query()->with('project.credentials')->find($this->messageId);
        if (! $message || $message->status === 'canceled' || $this->batch()?->cancelled()) {
            return;
        }

        // A retried batch skips the devices already handled.
        $done = DB::table('deliveries')->where('message_id', $message->id)->whereIn('subscription_id', $this->subscriptionIds)->pluck('subscription_id')->all();
        $subscriptions = Subscription::query()->with('subscriber')
            ->whereIn('id', array_diff($this->subscriptionIds, $done))
            ->whereNull('invalid_at')
            ->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $credential = $message->project->credential(Subscription::PROVIDERS[$this->type]);

        try {
            $results = $this->sender()->send($credential, $message, $subscriptions);
        } catch (Throwable $e) {
            // Keys refused, network down...: every device of the batch is errored.
            $results = $subscriptions->mapWithKeys(fn ($s) => [$s->id => Result::errored(mb_substr($e->getMessage(), 0, 180))])->all();
            $credential?->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }

        $this->record($message, $results);
    }

    protected function sender(): Sender
    {
        return match ($this->type) {
            'ios' => app(ApnsSender::class),
            'android' => app(FcmSender::class),
            'web' => app(WebPushSender::class),
        };
    }

    /** @param array<string, Result> $results */
    protected function record(Message $message, array $results): void
    {
        $now = now();
        $rows = [];
        $counts = ['successful' => 0, 'failed' => 0, 'errored' => 0];
        $invalid = [];

        foreach ($results as $id => $result) {
            $rows[] = [
                'message_id' => $message->id,
                'subscription_id' => $id,
                'status' => $result->status,
                'error' => $result->error ? mb_substr($result->error, 0, 250) : null,
                'sent_at' => $now,
            ];
            $counts[match ($result->status) {
                'sent' => 'successful', 'failed' => 'failed', default => 'errored'
            }]++;
            if ($result->invalid) {
                $invalid[] = $id;
            }
        }

        DB::transaction(function () use ($message, $rows, $counts, $invalid, $now) {
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('deliveries')->insertOrIgnore($chunk);
            }

            $counts = array_filter($counts);
            if ($counts !== []) {
                DB::table('messages')->where('id', $message->id)->incrementEach($counts, ['updated_at' => $now]);
                DB::table('message_platforms')->where('message_id', $message->id)->where('platform', $this->type)->incrementEach($counts);
            }

            if ($invalid !== []) {
                DB::table('subscriptions')->whereIn('id', $invalid)->update(['invalid_at' => $now, 'updated_at' => $now]);
            }
        });

        // Tokens refused for good (app uninstalled...).
        if ($invalid !== []) {
            foreach (Subscription::query()->with('subscriber')->whereIn('id', $invalid)->get() as $subscription) {
                Webhooks::dispatch($message->project_id, 'subscription.unsubscribed', array_filter([
                    'subscription_id' => $subscription->id,
                    'subscriber_id' => $subscription->subscriber_id,
                    'external_id' => $subscription->subscriber?->external_id,
                    'platform' => $subscription->type,
                    'reason' => 'token_invalid',
                ], fn ($value) => $value !== null));
            }
        }
    }
}
