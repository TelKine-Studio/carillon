<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'name', 'filters'])]
class Segment extends Model
{
    /** Built-in segments every project has, as in OneSignal. */
    public const BUILT_IN = ['All', 'Subscribed Users', 'Active Users', 'Engaged Users', 'Inactive Users', 'iOS Users', 'Android Users', 'Web Users', 'Test Devices'];

    /** Names used by OneSignal for the same segments, accepted by the API. */
    public const ALIASES = [
        'Total Subscriptions' => 'Subscribed Users',
        'Active Subscriptions' => 'Active Users',
        'Engaged Subscriptions' => 'Engaged Users',
        'Inactive Subscriptions' => 'Inactive Users',
        'iOS audience' => 'iOS Users',
        'Android audience' => 'Android Users',
        'Web audience' => 'Web Users',
    ];

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
