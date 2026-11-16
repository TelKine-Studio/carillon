<?php

namespace App\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\LiveActivities\LiveActivities;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Tokens of Live Activities, sent by the SDK of the app. */
class LiveActivityController extends Controller
{
    public function __construct(protected LiveActivities $activities) {}

    /** Push-to-start token of a type of activity (iOS 17.2+). */
    public function startToken(Request $request, string $appId, string $id): JsonResponse
    {
        $data = $request->validate([
            'activity_type' => ['required', 'string', 'max:128'],
            'token' => ['nullable', 'string', 'max:4000'],
        ]);
        $device = $this->device($appId, $id);

        filled($data['token'] ?? null)
            ? $this->activities->saveStartToken($device, $data['activity_type'], $data['token'])
            : $this->activities->removeStartToken($device, $data['activity_type']);

        return response()->json(['success' => true]);
    }

    /** The app shows an activity and has its update token. */
    public function enter(Request $request, string $appId, string $id): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['required', 'string', 'max:255'],
            'activity_type' => ['nullable', 'string', 'max:128'],
            'token' => ['required', 'string', 'max:4000'],
        ]);
        $activity = $this->activities->enter($this->device($appId, $id), $data['activity_id'], $data['activity_type'] ?? 'Unknown', $data['token']);

        return response()->json(['success' => true, 'status' => $activity->status]);
    }

    /** The activity left the screen of the device. */
    public function exit(Request $request, string $appId, string $id): JsonResponse
    {
        $data = $request->validate(['activity_id' => ['required', 'string', 'max:255']]);
        $this->activities->exit($this->device($appId, $id), $data['activity_id']);

        return response()->json(['success' => true]);
    }

    protected function device(string $appId, string $id): Subscription
    {
        // PostgreSQL refuses to compare a UUID column with any other text.
        abort_unless(Str::isUuid($id), 404, 'Unknown subscription.');
        $device = Subscription::query()->whereKey($id)->where('type', 'ios')
            ->whereHas('project', fn ($q) => $q->where('app_id', $appId))->first();
        abort_unless($device, 404, 'Unknown subscription.');

        return $device;
    }
}
