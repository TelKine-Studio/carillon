<?php

namespace App\Http\Controllers;

use App\Audience\Retention;
use App\Models\CustomEvent;
use App\Models\Project;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Statistics of a project over a period: sending, clicks, audience. */
class StatsController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function __invoke(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $previousStart = $start->subDays($days);

        // Messages: totals per day of sending (kept forever, unlike per-device details).
        $sentAt = DB::raw('date(coalesce(started_at, created_at))');
        $messages = $project->messages()->whereNotIn('status', ['canceled', 'draft'])->where('is_test', false);
        $perDay = (clone $messages)
            ->where('created_at', '>=', $start)
            ->select([DB::raw('date(coalesce(started_at, created_at)) as day'), DB::raw('count(*) as messages'), DB::raw('sum(successful) as delivered'), DB::raw('sum(clicked) as clicked')])
            ->groupBy($sentAt)
            ->get()
            ->keyBy('day');
        $previous = (clone $messages)
            ->whereBetween('created_at', [$previousStart, $start])
            ->selectRaw('sum(successful) as delivered, sum(clicked) as clicked, count(*) as messages')
            ->first();

        // Devices.
        $reachable = $project->subscriptions()->where('enabled', true)->whereNull('invalid_at')->whereIn('type', Subscription::PUSH_TYPES);
        $newPerDay = $project->subscriptions()
            ->whereIn('type', Subscription::PUSH_TYPES)
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('total', 'day');
        $devices = (clone $reachable)->count();

        $dates = collect(range(0, $days - 1))->map(fn (int $offset) => $start->addDays($offset));
        $series = fn (callable $value) => $dates->map(fn (CarbonImmutable $date) => ['date' => $date, 'value' => (int) $value($date->toDateString())])->all();

        // People who used the app each day (sessions of the SDK, clicks, nightly sync).
        $activePerDay = DB::table('activity_days')->where('project_id', $project->id)->where('day', '>=', $start->toDateString())
            ->selectRaw('day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $activePrevious = DB::table('activity_days')->where('project_id', $project->id)->whereBetween('day', [$previousStart->toDateString(), $start->subDay()->toDateString()])->count();
        // Subscription changes, like OneSignal: first subscriptions and unsubscriptions per day.
        $changes = fn (string $column) => $project->subscriptions()->whereIn('type', Subscription::PUSH_TYPES)->where($column, '>=', $start)
            ->selectRaw("date({$column}) as day, count(*) as total")->groupBy(DB::raw("date({$column})"))->pluck('total', 'day');
        $subscribedPerDay = $changes('subscribed_at');
        $unsubscribedPerDay = $changes('unsubscribed_at');

        $delivered = (int) $perDay->sum('delivered');
        $clicked = (int) $perDay->sum('clicked');
        $newDevices = (int) $newPerDay->sum();

        // Running total of devices, ending on today's count.
        $running = $devices - $newDevices;
        $totals = $dates->map(function (CarbonImmutable $date) use (&$running, $newPerDay) {
            $running += (int) ($newPerDay[$date->toDateString()] ?? 0);

            return ['date' => $date, 'value' => max(0, $running)];
        })->all();

        return view('stats.index', [
            'project' => $project,
            'days' => $days,
            'kpis' => [
                'devices' => $devices,
                'new_devices' => $newDevices,
                'messages' => (int) $perDay->sum('messages'),
                'delivered' => $delivered,
                'clicked' => $clicked,
                'active' => (int) $activePerDay->sum(),
                'subscribed' => (int) $subscribedPerDay->sum(),
                'unsubscribed' => (int) $unsubscribedPerDay->sum(),
                'ctr' => $delivered > 0 ? $clicked / $delivered * 100 : null,
                'previous' => [
                    'delivered' => (int) ($previous->delivered ?? 0),
                    'clicked' => (int) ($previous->clicked ?? 0),
                    'ctr' => ($previous->delivered ?? 0) > 0 ? $previous->clicked / $previous->delivered * 100 : null,
                    'new_devices' => $project->subscriptions()->whereIn('type', Subscription::PUSH_TYPES)->whereBetween('created_at', [$previousStart, $start])->count(),
                    'devices' => max(0, $devices - $newDevices),
                    'active' => $activePrevious,
                ],
            ],
            'charts' => [
                'delivered' => $series(fn (string $day) => $perDay[$day]->delivered ?? 0),
                'clicked' => $series(fn (string $day) => $perDay[$day]->clicked ?? 0),
                'new_devices' => $series(fn (string $day) => $newPerDay[$day] ?? 0),
                'devices' => $totals,
                'active' => $series(fn (string $day) => $activePerDay[$day] ?? 0),
                'subscribed' => $series(fn (string $day) => $subscribedPerDay[$day] ?? 0),
                'unsubscribed' => $series(fn (string $day) => $unsubscribedPerDay[$day] ?? 0),
            ],
            'breakdowns' => [
                'platform' => $this->breakdown((clone $reachable)->select('type as label'), fn ($type) => ['ios' => 'iOS', 'android' => 'Android', 'huawei' => 'Huawei', 'amazon' => 'Amazon', 'web' => __('Web')][$type] ?? $type),
                'country' => $this->breakdown((clone $reachable)->join('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')->select('subscribers.country as label'), fn ($code) => $code ? (locale_get_display_region('-'.$code, app()->getLocale()) ?: $code) : null),
                'language' => $this->breakdown((clone $reachable)->join('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')->select('subscribers.language as label'), fn ($code) => $code ? (locale_get_display_language($code, app()->getLocale()) ?: $code) : null),
                'app_version' => $this->breakdown((clone $reachable)->select('app_version as label'), fn ($version) => $version),
            ],
            'top' => (clone $messages)
                ->where('created_at', '>=', $start)
                ->where('successful', '>=', 10)
                ->orderByRaw('clicked * 1.0 / successful desc')
                ->limit(5)
                ->get(),
            'conversions' => CustomEvent::query()
                ->where('project_id', $project->id)
                ->where('occurred_at', '>=', $start)
                ->selectRaw('name, count(*) as total, sum(case when message_id is not null then 1 else 0 end) as attributed, sum(value) as amount, sum(case when message_id is not null then value else 0 end) as attributed_amount')
                ->groupBy('name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'retention' => Retention::weekly($project),
            // From the sending to the conversion, over the period.
            'funnel' => [
                'delivered' => $delivered,
                'received' => (int) (clone $messages)->where('created_at', '>=', $start)->sum('received'),
                'clicked' => $clicked,
                'converted' => CustomEvent::query()->where('project_id', $project->id)->where('occurred_at', '>=', $start)->whereNotNull('message_id')->whereNotNull('subscriber_id')->distinct()->count('subscriber_id'),
            ],
            'legacy' => (clone $reachable)->where('sdk', Subscription::ONESIGNAL_SDK)->count(),
        ]);
    }

    /**
     * Share of the reachable devices per value, largest first; the tail is
     * grouped as "Other" and missing values as "Unknown".
     *
     * @return Collection<int, array{label: string, value: int, share: float, other?: bool}>
     */
    protected function breakdown($query, callable $label, int $limit = 6): Collection
    {
        $rows = DB::query()->fromSub($query, 'rows')
            ->selectRaw('label, count(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $sum = max(1, (int) $rows->sum('total'));
        $named = $rows->map(fn ($row) => ['label' => $label($row->label) ?: __('Unknown'), 'value' => (int) $row->total, 'unknown' => $row->label === null || $row->label === '']);

        $top = $named->reject(fn ($row) => $row['unknown'])->take($limit);
        $rest = $named->sum('value') - $top->sum('value');

        return $top->values()
            ->when($rest > 0, fn (Collection $rows) => $rows->push(['label' => __('Other or unknown'), 'value' => $rest, 'other' => true]))
            ->map(fn (array $row) => $row + ['share' => $row['value'] / $sum * 100]);
    }
}
