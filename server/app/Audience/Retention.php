<?php

namespace App\Audience;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Weekly retention cohorts: people grouped by the week they were first seen,
 * and the share of them active again N weeks later.
 */
final class Retention
{
    /**
     * @return list<array{start: CarbonImmutable, size: int, weeks: list<float|null>}>
     */
    public static function weekly(Project $project, int $weeks = 8): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfWeek()->subWeeks($weeks - 1);

        $cohort = [];
        $active = [];
        $sizes = array_fill(0, $weeks, 0);
        DB::table('subscribers')
            ->where('project_id', $project->id)
            ->where('first_seen_at', '>=', $start)
            ->select(['id', 'first_seen_at'])
            ->orderBy('id')
            ->lazy(2000)
            ->each(function ($row) use (&$cohort, &$sizes, &$active, $start) {
                $index = intdiv((int) round($start->diffInDays(CarbonImmutable::parse($row->first_seen_at)->startOfDay())), 7);
                $cohort[$row->id] = $index;
                $sizes[$index]++;
                $active[$index][0][$row->id] = true; // the week they were first seen
            });

        // Distinct people active in each week after their first one.
        DB::table('activity_days')
            ->where('project_id', $project->id)
            ->where('day', '>=', $start->toDateString())
            ->select(['subscriber_id', 'day'])
            ->orderBy('subscriber_id')
            ->lazy(5000)
            ->each(function ($row) use (&$active, $cohort, $start) {
                if (! isset($cohort[$row->subscriber_id])) {
                    return;
                }
                $index = $cohort[$row->subscriber_id];
                $offset = intdiv((int) round($start->diffInDays(CarbonImmutable::parse($row->day))), 7) - $index;
                if ($offset >= 0) {
                    $active[$index][$offset][$row->subscriber_id] = true;
                }
            });

        $rows = [];
        for ($index = $weeks - 1; $index >= 0; $index--) {
            $elapsed = $weeks - $index; // weeks begun since the cohort started, this one included
            $values = [];
            for ($offset = 0; $offset < $weeks; $offset++) {
                $values[] = $offset < $elapsed && $sizes[$index] > 0
                    ? count($active[$index][$offset] ?? []) / $sizes[$index] * 100
                    : null;
            }
            $rows[] = ['start' => $start->addWeeks($index), 'size' => $sizes[$index], 'weeks' => $values];
        }

        return $rows;
    }
}
