<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LiveActivities\LiveActivities;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live Activities API, OneSignal-compatible (iOS), also shown on Android as Live Updates. */
class LiveActivityController extends Controller
{
    public function __construct(protected LiveActivities $activities) {}

    /** POST /apps/{app_id}/activities/activity/{activity_type}: starts an activity by push. */
    public function start(Request $request, string $appId, string $type): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z_][A-Za-z0-9_.]{0,127}$/', $type) === 1, 400, 'Invalid activity_type.');
        $data = $request->validate([
            'event' => ['nullable', 'in:start'],
            'activity_id' => ['required', 'string', 'max:255'],
            'event_attributes' => ['nullable', 'array'],
            'event_updates' => ['nullable', 'array'],
            'name' => ['nullable', 'string', 'max:128'],
            'contents' => ['nullable'],
            'headings' => ['nullable'],
            'include_aliases' => ['nullable', 'array'],
            'include_subscription_ids' => ['nullable', 'array', 'max:2000'],
            'include_external_user_ids' => ['nullable', 'array', 'max:2000'],
            'included_segments' => ['nullable', 'array'],
            'excluded_segments' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'stale_date' => ['nullable', 'integer'],
            'dismissal_date' => ['nullable', 'integer'],
            'end_after' => ['nullable', 'integer', 'min:60'],
            'android' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
            'ios_sound' => ['nullable', 'string', 'max:255'],
            'ios_relevance_score' => ['nullable', 'numeric'],
        ]);

        $result = $this->activities->start($this->project($request), $type, $data);

        return response()->json(['id' => $data['activity_id'], ...$result]);
    }

    /** POST /apps/{app_id}/live_activities/{activity_id}/notifications: update or end. */
    public function notify(Request $request, string $appId, string $activityId): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'in:update,end'],
            'event_updates' => ['nullable', 'array'],
            'name' => ['nullable', 'string', 'max:128'],
            'contents' => ['nullable'],
            'headings' => ['nullable'],
            'priority' => ['nullable', 'integer'],
            'android' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
            'stale_date' => ['nullable', 'integer'],
            'dismissal_date' => ['nullable', 'integer'],
            'ios_sound' => ['nullable', 'string', 'max:255'],
            'ios_relevance_score' => ['nullable', 'numeric'],
        ]);

        $result = $this->activities->notify($this->project($request), $activityId, $data['event'], $data);

        if ($result === null) {
            return response()->json(['errors' => ['Live Activity not found or already ended.']], 404);
        }

        return response()->json(['id' => $activityId, ...$result]);
    }

    protected function project(Request $request): Project
    {
        return $request->attributes->get('project');
    }
}
