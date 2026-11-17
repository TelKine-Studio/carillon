<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A connection of a project with another tool: events sent out (analytics,
 * chat, storage) or data coming in (Segment-compatible source, SQL database).
 */
#[Fillable(['project_id', 'kind', 'name', 'config', 'secret', 'events', 'enabled', 'created_by'])]
#[Hidden(['secret'])]
class Integration extends Model
{
    /** Kind => [direction, category]. */
    public const KINDS = [
        'posthog' => ['out', 'analytics'],
        'mixpanel' => ['out', 'analytics'],
        'amplitude' => ['out', 'analytics'],
        'slack' => ['out', 'chat'],
        'discord' => ['out', 'chat'],
        's3' => ['out', 'storage'],
        'segment' => ['in', 'cdp'],
        'postgres' => ['in', 'database'],
        'mysql' => ['in', 'database'],
    ];

    /** Kinds that receive the events of the project, one by one. */
    public const STREAMS = ['posthog', 'mixpanel', 'amplitude', 'slack', 'discord'];

    /** Events an integration can follow. */
    public const EVENTS = [
        'message.sent',
        'notification.received',
        'notification.clicked',
        'email.opened',
        'email.clicked',
        'subscription.created',
        'subscription.unsubscribed',
        'onesignal_sync.failed',
        'integration.failed',
    ];

    /** Events chosen when the integration is created. */
    public const DEFAULT_EVENTS = [
        'analytics' => ['notification.received', 'notification.clicked', 'email.opened', 'email.clicked', 'subscription.created', 'subscription.unsubscribed'],
        'chat' => ['message.sent', 'onesignal_sync.failed', 'integration.failed'],
    ];

    /** Disabled after this many failures in a row. */
    public const MAX_FAILURES = 20;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'secret' => 'encrypted',
            'events' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_result' => 'array',
        ];
    }

    public static function newWriteKey(): string
    {
        return 'cws_'.Str::random(40);
    }

    public function direction(): string
    {
        return self::KINDS[$this->kind][0] ?? 'out';
    }

    public function category(): string
    {
        return self::KINDS[$this->kind][1] ?? 'other';
    }

    public function isStream(): bool
    {
        return in_array($this->kind, self::STREAMS, true);
    }

    public function listensTo(string $event): bool
    {
        return $this->isStream() && in_array($event, $this->events ?? [], true);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /** Result of a run (sync, export, delivery), kept on the integration. */
    public function succeeded(array $result = []): void
    {
        $this->forceFill([
            'last_run_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
            'failures' => 0,
            'last_result' => $result ?: $this->last_result,
        ])->save();
    }

    public function failedWith(string $error): void
    {
        $this->forceFill([
            'last_run_at' => now(),
            'last_error' => mb_substr($error, 0, 500),
            'failures' => $this->failures + 1,
        ])->save();

        if ($this->failures >= self::MAX_FAILURES && $this->enabled) {
            $this->forceFill(['enabled' => false, 'disabled_reason' => 'failures'])->save();
        }
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
