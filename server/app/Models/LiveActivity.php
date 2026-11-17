<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Live Activity on one device. */
#[Fillable(['project_id', 'subscription_id', 'activity_type', 'activity_id', 'status', 'update_token', 'content_state', 'pending', 'updates', 'error', 'started_at', 'token_at', 'updated_last_at', 'end_at', 'ended_at', 'ends', 'pushes'])]
#[Hidden(['update_token'])]
class LiveActivity extends Model
{
    use HasUuids, MassPrunable;

    /** Statuses of an activity still on screen (as far as Carillon knows). */
    public const OPEN = ['starting', 'started', 'active'];

    protected function casts(): array
    {
        return [
            'content_state' => 'array',
            'pushes' => 'array',
            'pending' => 'array',
            'updates' => 'integer',
            'ends' => 'integer',
            'started_at' => 'datetime',
            'token_at' => 'datetime',
            'updated_last_at' => 'datetime',
            'end_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @param Builder<LiveActivity> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN);
    }

    /**
     * Ended activities are kept as long as the delivery history of the
     * messages (Settings → retention), then deleted.
     *
     * @return Builder<LiveActivity>
     */
    public function prunable(): Builder
    {
        $days = max(1, app(Settings::class)->integer('messages.retention_days', 90));

        return static::query()->whereNotIn('status', self::OPEN)->where('updated_at', '<', now()->subDays($days));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
