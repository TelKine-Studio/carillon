<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something a person did (purchase, top-up…), attributed to a message when it follows one. */
#[Fillable(['project_id', 'subscriber_id', 'name', 'value', 'properties', 'message_id', 'attribution', 'occurred_at'])]
class CustomEvent extends Model
{
    protected function casts(): array
    {
        return ['properties' => 'array', 'value' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
