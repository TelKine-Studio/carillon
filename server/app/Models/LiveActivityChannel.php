<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** Broadcast channel of APNs carrying the updates of one activity_id (iOS 18+). */
#[Fillable(['project_id', 'activity_id', 'environment', 'channel_id', 'deleted_at'])]
class LiveActivityChannel extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    /** @return Builder<LiveActivityChannel> Deleted at Apple a month ago. */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays(30));
    }
}
