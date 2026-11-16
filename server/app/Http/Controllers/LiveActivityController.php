<?php

namespace App\Http\Controllers;

use App\LiveActivities\LiveActivities;
use App\Models\LiveActivityStartToken;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Live Activities of a project: what is on screen, what ended, and why. */
class LiveActivityController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $search = trim((string) $request->query('q'));
        $activities = $project->liveActivities()
            ->with('subscription:id,device_model,subscriber_id', 'subscription.subscriber:id,external_id')
            ->when($search !== '', fn ($q) => $q->where('activity_id', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('live-activities.index', [
            'project' => $project,
            'activities' => $activities,
            'search' => $search,
            'open' => $project->liveActivities()->open()->count(),
            'today' => $project->liveActivities()->where('created_at', '>=', now()->subDay())->count(),
            'types' => LiveActivityStartToken::query()->where('project_id', $project->id)
                ->selectRaw('activity_type, count(*) as devices')->groupBy('activity_type')->orderBy('activity_type')->pluck('devices', 'activity_type'),
            // Android apps on the Carillon SDK 0.3+ (Live Updates).
            'android' => $project->subscriptions()->reachable()->where('type', 'android')->where('sdk', 'like', 'carillon-flutter/%')
                ->where('sdk', 'not like', 'carillon-flutter/0.1.%')->where('sdk', 'not like', 'carillon-flutter/0.2.%')->count(),
            'colors' => ['starting' => 'zinc', 'started' => 'blue', 'active' => 'green', 'ended' => 'zinc', 'failed' => 'red'],
            'labels' => ['starting' => __('Starting'), 'started' => __('Started'), 'active' => __('On screen'), 'ended' => __('Ended'), 'failed' => __('Refused by Apple')],
        ]);
    }

    /** Starts an activity on chosen devices, to test the widget of an app. */
    public function start(Request $request, Project $project, LiveActivities $activities): RedirectResponse
    {
        $this->authorize('send', $project);
        $data = $request->validate([
            'subscription_ids' => ['required', 'string', 'max:4000'],
            'activity_type' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z_][A-Za-z0-9_.]*$/'],
            'activity_id' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
            'state' => ['nullable', 'json'],
            'attributes' => ['nullable', 'json'],
            'android' => ['nullable', 'json'],
            'stale_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'end_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
        ]);

        $result = $activities->start($project, $data['activity_type'], array_filter([
            'activity_id' => $data['activity_id'],
            'include_subscription_ids' => preg_split('/[\s,]+/', trim($data['subscription_ids']), -1, PREG_SPLIT_NO_EMPTY),
            'headings' => filled($data['title'] ?? null) ? ['en' => $data['title']] : null,
            'contents' => filled($data['body'] ?? null) ? ['en' => $data['body']] : null,
            'event_updates' => json_decode($data['state'] ?? '{}', true) ?: [],
            'event_attributes' => json_decode($data['attributes'] ?? '{}', true) ?: [],
            'android' => json_decode($data['android'] ?? 'null', true),
            'stale_date' => isset($data['stale_minutes']) ? now()->addMinutes((int) $data['stale_minutes'])->getTimestamp() : null,
            'end_after' => isset($data['end_minutes']) ? (int) $data['end_minutes'] * 60 : null,
        ], fn ($value) => $value !== null));

        return back()->with('status', $this->summary($result));
    }

    /** Update or end, from the dashboard. */
    public function notify(Request $request, Project $project, LiveActivities $activities): RedirectResponse
    {
        $this->authorize('send', $project);
        $data = $request->validate([
            'activity_id' => ['required', 'string', 'max:255'],
            'event' => ['required', 'in:update,end'],
            'update_title' => ['nullable', 'string', 'max:255'],
            'update_body' => ['nullable', 'string', 'max:1000'],
            'update_state' => ['nullable', 'json'],
            'update_android' => ['nullable', 'json'],
            'priority' => ['nullable', 'in:5,10'],
            'dismiss_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ]);

        $result = $activities->notify($project, $data['activity_id'], $data['event'], array_filter([
            'headings' => filled($data['update_title'] ?? null) ? ['en' => $data['update_title']] : null,
            'contents' => filled($data['update_body'] ?? null) ? ['en' => $data['update_body']] : null,
            'event_updates' => filled($data['update_state'] ?? null) ? (json_decode($data['update_state'], true) ?: []) : null,
            'android' => json_decode($data['update_android'] ?? 'null', true),
            'priority' => (int) ($data['priority'] ?? 10),
            'dismissal_date' => isset($data['dismiss_minutes']) ? now()->addMinutes((int) $data['dismiss_minutes'])->getTimestamp() : null,
        ], fn ($value) => $value !== null));

        if ($result === null) {
            return back()->withErrors(['alert' => __('No device shows this activity (never started, or ended).')]);
        }

        return back()->with('status', $this->summary($result));
    }

    /** @param array<string, mixed> $result */
    protected function summary(array $result): string
    {
        $parts = [trans_choice(':count device reached|:count devices reached', $result['recipients'], ['count' => $result['recipients']])];
        if ($result['already'] ?? 0) {
            $parts[] = __(':count already showing it', ['count' => $result['already']]);
        }
        if ($result['skipped']['no_start_token'] ?? 0) {
            $parts[] = __(':count without start token', ['count' => $result['skipped']['no_start_token']]);
        }
        if ($result['skipped']['unsupported'] ?? 0) {
            $parts[] = __(':count unable to show it', ['count' => $result['skipped']['unsupported']]);
        }
        if ($result['pending'] ?? 0) {
            $parts[] = __(':count waiting for the token of the app', ['count' => $result['pending']]);
        }
        if ($result['errors'] ?? []) {
            $parts[] = __('refused: :errors', ['errors' => implode(', ', $result['errors'])]);
        }

        return implode(' · ', $parts).'.';
    }
}
