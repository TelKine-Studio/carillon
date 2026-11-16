<?php

namespace App\Http\Controllers;

use App\Models\LiveActivityStartToken;
use App\Models\Project;
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
            'colors' => ['starting' => 'zinc', 'started' => 'blue', 'active' => 'green', 'ended' => 'zinc', 'failed' => 'red'],
            'labels' => ['starting' => __('Starting'), 'started' => __('Started'), 'active' => __('On screen'), 'ended' => __('Ended'), 'failed' => __('Refused by Apple')],
        ]);
    }
}
