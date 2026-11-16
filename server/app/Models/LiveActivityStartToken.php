<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Push-to-start token of one device for one type of Live Activity (iOS 17.2+). */
#[Fillable(['project_id', 'subscription_id', 'activity_type', 'token'])]
#[Hidden(['token'])]
class LiveActivityStartToken extends Model
{
    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
