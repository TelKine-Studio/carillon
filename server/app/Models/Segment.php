<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'name', 'filters'])]
class Segment extends Model
{
    /** Built-in segments every project has, as in OneSignal. */
    public const BUILT_IN = ['All', 'Subscribed Users', 'Active Users', 'Engaged Users', 'Inactive Users'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
