<?php

namespace App\Http\Controllers;

use App\Jobs\SyncOneSignal;
use App\Models\OneSignalSync;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Settings of the automatic import from OneSignal. */
class OneSignalSyncController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $existing = OneSignalSync::query()->where('project_id', $project->id)->first();

        $data = $request->validate([
            'onesignal_app_id' => ['required', 'string', 'max:64'],
            'onesignal_api_key' => [$existing ? 'nullable' : 'required', 'string', 'max:500'],
            'onesignal_enabled' => ['boolean'],
        ]);

        $sync = $existing ?? new OneSignalSync(['project_id' => $project->id]);
        $sync->fill([
            'app_id' => trim($data['onesignal_app_id']),
            'enabled' => $request->boolean('onesignal_enabled'),
        ]);
        if (filled($data['onesignal_api_key'] ?? null)) {
            $sync->api_key = trim($data['onesignal_api_key']);
        }
        $sync->save();

        Audit::log('audience.sync_updated', $project, ['enabled' => $sync->enabled]);

        // A first import right away, which also checks the key.
        if (! $existing || $sync->wasChanged(['app_id', 'api_key'])) {
            $this->start($sync);

            return back()->with('status', __('Saved. A first sync has started: it takes a few minutes.'));
        }

        return back()->with('status', __('Saved.'));
    }

    public function run(Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $sync = OneSignalSync::query()->where('project_id', $project->id)->firstOrFail();

        if (! $sync->isRunning()) {
            $this->start($sync);
        }

        return back()->with('status', __('Sync started: it takes a few minutes.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        OneSignalSync::query()->where('project_id', $project->id)->delete();
        Audit::log('audience.sync_removed', $project);

        return back()->with('status', __('Automatic sync removed.'));
    }

    protected function start(OneSignalSync $sync): void
    {
        $sync->forceFill(['pending_url' => null, 'last_error' => null, 'requested_at' => now()])->save();
        SyncOneSignal::dispatch($sync->id);
    }
}
