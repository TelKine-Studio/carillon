<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'subscription_id', 'status', 'error', 'sent_at', 'received_at', 'clicked_at'])]
class Delivery extends Model
{
    use Prunable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** Per-device details older than the retention period (message totals stay). */
    public function prunable(): Builder
    {
        return static::query()->where('sent_at', '<', now()->subDays(max(1, app(Settings::class)->integer('messages.retention_days', 90))));
    }
}
