<?php

namespace App\Jobs;

use App\Integrations\S3Export;
use App\Integrations\SqlSync;
use App\Models\Integration;
use App\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** One run of a scheduled integration: SQL sync, or nightly export to S3. */
class RunIntegration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public int $integrationId, public ?string $day = null) {}

    public function uniqueId(): string
    {
        return $this->integrationId.':'.$this->day;
    }

    public function handle(): void
    {
        $integration = Integration::query()->with('project')->find($this->integrationId);
        if (! $integration) {
            return;
        }

        try {
            $result = match ($integration->kind) {
                'postgres', 'mysql' => SqlSync::run($integration),
                's3' => S3Export::run($integration, $this->day ? CarbonImmutable::parse($this->day) : CarbonImmutable::yesterday()),
                default => null,
            };
            if ($result !== null) {
                $integration->succeeded($result);
            }
        } catch (Throwable $e) {
            $error = mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 500);
            $integration->failedWith($error);
            Webhooks::dispatch($integration->project_id, 'integration.failed', ['integration_id' => $integration->id, 'kind' => $integration->kind, 'name' => $integration->name, 'error' => $error]);
        }
    }
}
