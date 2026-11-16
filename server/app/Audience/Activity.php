<?php

namespace App\Audience;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * When people use the app: days (activity_days, for retention) and hours of
 * the day (subscribers.hour_counts, for the smart send time).
 */
final class Activity
{
    public static function record(string $subscriberId, int $projectId, ?DateTimeInterface $at = null): void
    {
        $at ??= now();
        $inserted = DB::table('activity_days')->insertOrIgnore([
            'subscriber_id' => $subscriberId,
            'project_id' => $projectId,
            'day' => $at->format('Y-m-d'),
        ]);

        // Hours are counted once per day and hour (repeated syncs do not weigh more).
        $hour = (int) \DateTimeImmutable::createFromInterface($at)->setTimezone(new \DateTimeZone('UTC'))->format('G');
        $key = 'activity-hour:'.$subscriberId.':'.$at->format('Y-m-d').':'.$hour;
        if (! $inserted && cache()->has($key)) {
            return;
        }
        cache()->put($key, true, now()->addDay());
        self::countHour($subscriberId, $hour, 1);
    }

    /** A click weighs more than an opening: it is the moment the person reads. */
    public static function click(string $subscriberId, ?DateTimeInterface $at = null): void
    {
        self::countHour($subscriberId, (int) \DateTimeImmutable::createFromInterface($at ?? now())->setTimezone(new \DateTimeZone('UTC'))->format('G'), 2);
    }

    private static function countHour(string $subscriberId, int $hour, int $weight): void
    {
        $current = json_decode((string) DB::table('subscribers')->where('id', $subscriberId)->value('hour_counts'), true) ?: [];
        $current[$hour] = ($current[$hour] ?? 0) + $weight;
        DB::table('subscribers')->where('id', $subscriberId)->update(['hour_counts' => json_encode($current)]);
    }

    /**
     * The hour (UTC) at which the person is the most active, or null.
     *
     * @param  array<int|string, int>|null  $counts
     */
    public static function bestHour(?array $counts): ?int
    {
        if (! $counts) {
            return null;
        }
        arsort($counts);

        return (int) array_key_first($counts);
    }
}
