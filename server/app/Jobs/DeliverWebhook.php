<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\NetworkGuard;
use App\Webhooks\Webhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Posts one event to a webhook, retried with growing delays. */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     * @param  bool  $test  test event from the dashboard: sent even if the
     *                      webhook is disabled, and never counted as a failure
     */
    public function __construct(public int $webhookId, public array $payload, public bool $test = false) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $webhook = Webhook::query()->with('project')->find($this->webhookId);
        if (! $webhook || (! $webhook->enabled && ! $this->test)) {
            return;
        }

        $body = json_encode($this->payload + ['project' => ['app_id' => $webhook->project->app_id, 'name' => $webhook->project->name]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $started = microtime(true);
        $status = null;
        $error = null;

        try {
            if ($blocked = NetworkGuard::checkUrl($webhook->url)) {
                throw new RuntimeException($blocked);
            }
            $response = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()->withOptions(NetworkGuard::pin($webhook->url))
                ->withHeaders([
                    'User-Agent' => 'Carillon-Webhooks/1.0',
                    'Carillon-Event' => $this->payload['type'],
                    'Carillon-Delivery' => $this->payload['id'],
                    'Carillon-Signature' => Webhooks::signature($webhook->secret, $body, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
            $status = $response->status();
            if (! $response->successful()) {
                $error = 'HTTP '.$status;
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
        }

        $this->record($webhook, $status, $error, (int) round((microtime(true) - $started) * 1000));

        if ($error !== null) {
            // Retried later; failed() counts it once all attempts are spent.
            throw new RuntimeException('Webhook delivery failed: '.$error);
        }

        if (! $this->test) {
            $webhook->forceFill(['failures' => 0, 'last_delivery_at' => now()])->save();
        }
    }

    public function failed(?Throwable $e): void
    {
        $webhook = Webhook::query()->find($this->webhookId);
        if (! $webhook || $this->test) {
            return;
        }

        $webhook->increment('failures');
        if ($webhook->failures >= Webhook::MAX_FAILURES && $webhook->enabled) {
            $webhook->forceFill(['enabled' => false, 'disabled_reason' => 'failures'])->save();
            Webhooks::flush();
        }
    }

    protected function record(Webhook $webhook, ?int $status, ?string $error, int $duration): void
    {
        $webhook->deliveries()->create([
            'event_id' => $this->payload['id'],
            'event' => $this->payload['type'],
            'status' => $status,
            'error' => $error,
            'duration_ms' => $duration,
            'attempt' => $this->attempts(),
            'created_at' => now(),
        ]);

        $keep = $webhook->deliveries()->orderByDesc('id')->skip(WebhookDelivery::KEEP)->value('id');
        if ($keep) {
            $webhook->deliveries()->where('id', '<=', $keep)->delete();
        }
    }
}
