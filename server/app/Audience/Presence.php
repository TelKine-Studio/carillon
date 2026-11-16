<?php

namespace App\Audience;

use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Who has the app open right now: the SDKs send a heartbeat every minute
 * while the app (or the page) is in the foreground.
 */
final class Presence
{
    /** Seconds without a heartbeat before a device counts as gone. */
    public const WINDOW = 150;

    public static function seen(Subscription $subscription): void
    {
        // One write per minute at most for a device.
        if ($subscription->seen_at && $subscription->seen_at->greaterThan(now()->subSeconds(45))) {
            return;
        }
        DB::table('subscriptions')->where('id', $subscription->id)->update(['seen_at' => now()]);
    }

    /** @return array{online: int, platforms: array<string, int>, people: int} */
    public static function now(Project $project): array
    {
        $online = DB::table('subscriptions')
            ->where('project_id', $project->id)
            ->where('seen_at', '>=', now()->subSeconds(self::WINDOW));

        return [
            'online' => (clone $online)->count(),
            'people' => (clone $online)->distinct()->count('subscriber_id'),
            'platforms' => (clone $online)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
