<?php

namespace App\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Public settings of a project read by the SDKs (App ID only). */
class ConfigController extends Controller
{
    public function show(Request $request, string $appId): JsonResponse
    {
        $project = $this->project($appId);
        $webPush = $project->credential('webpush');
        $fcm = $project->credential('fcm');
        // Firebase options of this Android app (public values of its google-services.json).
        $android = $fcm?->detail('android_apps', [])[(string) $request->query('android_package')] ?? null;

        return response()->json([
            'app_id' => $project->app_id,
            'name' => $project->name,
            'icon' => $project->icon_path ? self::iconUrl($project) : null,
            'web' => $webPush?->isUsable() ? ['vapid_public_key' => $webPush->detail('public_key')] : null,
            'firebase' => $android ? [
                'project_id' => $fcm->detail('project_id'),
                'sender_id' => $fcm->detail('sender_id'),
                'app_id' => $android['app_id'],
                'api_key' => $android['api_key'],
            ] : null,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /** The project icon, shown by default on web notifications. */
    public function icon(string $appId): Response
    {
        $project = $this->project($appId);
        abort_unless($project->icon_path && Storage::disk('local')->exists($project->icon_path), 404);

        return Storage::disk('local')->response($project->icon_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public static function iconUrl(Project $project): string
    {
        return url('/api/v1/sdk/apps/'.$project->app_id.'/icon').'?v='.substr(md5((string) $project->icon_path), 0, 8);
    }

    protected function project(string $appId): Project
    {
        $project = Project::query()->with('credentials')->where('app_id', $appId)->first();
        abort_unless($project, 404, 'Unknown app_id.');

        return $project;
    }
}
