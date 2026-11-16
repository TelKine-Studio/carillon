<?php

namespace App\Audience;

use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * People (with an external id) reachable on several devices of the same
 * platform. A few are normal (two phones); a figure that keeps growing
 * tells that reinstalls leave old subscriptions behind. Nothing new is
 * collected about the devices to compute it.
 */
final class Duplicates
{
    public static function count(Project $project): int
    {
        $groups = DB::table('subscriptions')
            ->join('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')
            ->where('subscriptions.project_id', $project->id)
            ->where('subscriptions.enabled', true)
            ->whereNull('subscriptions.invalid_at')
            ->whereIn('subscriptions.type', Subscription::PUSH_TYPES)
            ->whereNotNull('subscribers.external_id')
            ->groupBy('subscriptions.subscriber_id', 'subscriptions.type')
            ->havingRaw('count(*) > 1')
            ->select('subscriptions.subscriber_id');

        return DB::query()->fromSub($groups, 'groups')->distinct()->count('subscriber_id');
    }
}
