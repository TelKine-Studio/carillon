<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['webhook_id', 'event_id', 'event', 'status', 'error', 'duration_ms', 'attempt', 'created_at'])]
class WebhookDelivery extends Model
{
    public const UPDATED_AT = null;

    /** Attempts kept per webhook. */
    public const KEEP = 50;

    public function successful(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300;
    }
}
