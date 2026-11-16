<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** An endpoint of the project's servers notified of events, signed with its secret. */
#[Fillable(['project_id', 'url', 'events', 'secret', 'enabled', 'failures', 'disabled_reason', 'last_delivery_at', 'created_by'])]
#[Hidden(['secret'])]
class Webhook extends Model
{
    public const EVENTS = [
        'message.sent',
        'notification.received',
        'notification.clicked',
        'subscription.created',
        'subscription.unsubscribed',
        'onesignal_sync.failed',
    ];

    /** Disabled after this many events in a row could not be delivered. */
    public const MAX_FAILURES = 20;

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'enabled' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
