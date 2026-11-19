<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One call of the SDK about a Live Activity, accepted or refused. */
#[Fillable(['project_id', 'subscription_id', 'subscription_ref', 'action', 'activity_id', 'activity_type', 'outcome', 'detail', 'app_version', 'created_at'])]
class LiveActivityCall extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Kept a week: enough to follow a problem, not a history. */
    public const DAYS = 7;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return Builder<LiveActivityCall> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::DAYS));
    }
}
