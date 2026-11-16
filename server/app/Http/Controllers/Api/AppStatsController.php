<?php

namespace App\Http\Controllers\Api;

use App\Audience\Duplicates;
use App\Audience\Presence;
use App\Http\Controllers\Controller;
use App\Models\OneSignalSync;
use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audience of the app for your own administration: reachable devices per
 * platform, and how many still run the OneSignal SDK (the figure that tells
 * when OneSignal can be switched off).
 */
class AppStatsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');
        $reachable = $project->subscriptions()->where('enabled', true)->whereNull('invalid_at');
        $push = (clone $reachable)->whereIn('type', Subscription::PUSH_TYPES);

        $devices = (clone $push)->count();
        $legacy = (clone $push)->where('sdk', Subscription::ONESIGNAL_SDK)->count();
        $sync = OneSignalSync::query()->where('project_id', $project->id)->first();

        return response()->json([
            'app_id' => $project->app_id,
            'devices' => $devices,
            'legacy' => $legacy,
            'carillon_sdk' => $devices - $legacy,
            'legacy_share' => $devices ? round($legacy / $devices * 100, 1) : null,
            'platforms' => (clone $push)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->map(fn ($v) => (int) $v),
            'channels' => [
                'email' => (clone $reachable)->where('type', 'email')->count(),
                'sms' => (clone $reachable)->where('type', 'sms')->count(),
                'whatsapp' => (clone $reachable)->where('type', 'whatsapp')->count(),
            ],
            'online' => Presence::now($project)['people'],
            // People reachable on several devices of one platform: a rising figure means old installs pile up.
            'duplicates' => Duplicates::count($project),
            'onesignal_sync' => $sync ? array_filter([
                'last_synced_at' => $sync->last_synced_at?->toIso8601String(),
                'last_error' => $sync->last_error,
            ]) : null,
        ]);
    }
}
