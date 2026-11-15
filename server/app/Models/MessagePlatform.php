<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Delivery totals of a message for one platform (ios, android, web). */
#[Fillable(['message_id', 'platform', 'recipients', 'successful', 'failed', 'errored', 'received', 'clicked'])]
class MessagePlatform extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
