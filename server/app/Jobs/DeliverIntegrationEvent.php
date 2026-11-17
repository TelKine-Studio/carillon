<?php

namespace App\Jobs;

use App\Integrations\Streams;
use App\Models\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Sends one event to an analytics or chat tool, retried with growing delays. */
class DeliverIntegrationEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /** @param array<string, mixed> $payload */
    public function __construct(public int $integrationId, public array $payload, public bool $test = false) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $integration = Integration::query()->with('project')->find($this->integrationId);
        if (! $integration || ! $integration->isStream() || (! $integration->enabled && ! $this->test)) {
            return;
        }

        $request = Streams::request($integration, $this->payload, $integration->project->name);
        try {
            $http = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()->withHeaders(['User-Agent' => 'Carillon-Integrations/1.0']);
            $response = ($request['form'] ?? false) ? $http->asForm()->post($request['url'], $request['body']) : $http->post($request['url'], $request['body']);
            $error = $response->successful() ? null : 'HTTP '.$response->status().' '.mb_substr(trim($response->body()), 0, 200);
            // Mixpanel answers 200 with {"status": 0, "error": …} when the event is refused.
            if (! $error && $integration->kind === 'mixpanel' && (int) ($response->json('status') ?? 1) !== 1) {
                $error = 'Mixpanel: '.($response->json('error') ?? 'refused');
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
        }

        if ($error !== null) {
            if ($this->test) {
                $integration->forceFill(['last_run_at' => now(), 'last_error' => $error])->save();
            }
            throw new RuntimeException('Integration delivery failed: '.$error);
        }

        // Written at most once a minute: busy projects send many events.
        if ($integration->failures > 0 || $integration->last_error || ! $integration->last_success_at || $integration->last_success_at->lt(now()->subMinute())) {
            $integration->succeeded();
        }
    }

    public function failed(?Throwable $e): void
    {
        if ($this->test || ! $integration = Integration::query()->find($this->integrationId)) {
            return;
        }
        $integration->failedWith(str_replace('Integration delivery failed: ', '', $e?->getMessage() ?? 'failed'));
    }
}
