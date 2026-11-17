<?php

namespace App\Webhooks;

use App\Jobs\DeliverIntegrationEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Integration;
use App\Models\Project;
use App\Models\Webhook;
use Illuminate\Support\Str;

/** Sends the events of a project to its webhooks (in the queue). */
class Webhooks
{
    /** Enabled webhooks per project, kept a few seconds (workers run long). */
    protected static array $cache = [];

    protected const CACHE_SECONDS = 10;

    /** @param array<string, mixed> $data */
    public static function dispatch(Project|int $project, string $event, array $data): void
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        [$at, $webhooks, $integrations] = self::$cache[$projectId] ?? [0, null, null];
        if ($webhooks === null || time() - $at > self::CACHE_SECONDS) {
            $webhooks = Webhook::query()->where('project_id', $projectId)->where('enabled', true)->get();
            $integrations = Integration::query()->where('project_id', $projectId)->where('enabled', true)
                ->whereIn('kind', Integration::STREAMS)->get();
            self::$cache[$projectId] = [time(), $webhooks, $integrations];
        }

        $payload = null;
        foreach ($webhooks as $webhook) {
            if ($webhook->listensTo($event)) {
                DeliverWebhook::dispatch($webhook->id, $payload ??= self::payload($event, $data));
            }
        }
        // Analytics and chat tools following the same events.
        foreach ($integrations as $integration) {
            if ($integration->listensTo($event)) {
                DeliverIntegrationEvent::dispatch($integration->id, $payload ??= self::payload($event, $data));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function payload(string $event, array $data): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'type' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    /** Forgets the cached webhooks (after a change, and between jobs). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Signature header: t=<timestamp>,v1=<HMAC-SHA256 of "<timestamp>.<body>">. */
    public static function signature(string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
