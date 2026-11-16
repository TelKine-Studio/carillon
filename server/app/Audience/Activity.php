<?php

namespace App\Audience;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/** Days on which a person used the app (activity_days), for retention. */
final class Activity
{
    public static function record(string $subscriberId, int $projectId, ?DateTimeInterface $at = null): void
    {
        DB::table('activity_days')->insertOrIgnore([
            'subscriber_id' => $subscriberId,
            'project_id' => $projectId,
            'day' => ($at ?? now())->format('Y-m-d'),
        ]);
    }
}
