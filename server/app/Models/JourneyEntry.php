<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person going through a journey: the step they are at, and when it runs. */
#[Fillable(['journey_id', 'subscriber_id', 'step', 'status', 'next_run_at', 'waiting_since', 'completed_at'])]
class JourneyEntry extends Model
{
    protected function casts(): array
    {
        return ['next_run_at' => 'datetime',
            'waiting_since' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Journey, $this> */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}
