<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Devices that receive the tests sent from the composer (OneSignal "test subscriptions"). */
class TestDeviceController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('audience.test-devices', [
            'project' => $project,
            'devices' => $project->subscriptions()->where('is_test', true)->with('subscriber')->orderBy('test_name')->get(),
        ]);
    }

    /** Adds the device(s) of a device id or an external id. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('send', $project);
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'test_name' => ['nullable', 'string', 'max:64'],
        ]);

        $identifier = trim($data['identifier']);
        $devices = $project->subscriptions()
            ->whereIn('type', Subscription::PUSH_TYPES)
            ->where(fn ($query) => $query
                ->when(Str::isUuid($identifier), fn ($q) => $q->whereKey($identifier))
                ->orWhereHas('subscriber', fn ($q) => $q->where('external_id', $identifier)))
            ->get();

        if ($devices->isEmpty()) {
            return back()->withErrors(['identifier' => __('No device found with this id or external id.')])->withInput();
        }

        foreach ($devices as $device) {
            $device->forceFill(['is_test' => true, 'test_name' => $data['test_name'] ?: ($device->test_name ?? $device->device_model)])->save();
        }

        return back()->with('status', trans_choice(':count test device added.|:count test devices added.', $devices->count(), ['count' => $devices->count()]));
    }

    public function update(Request $request, Project $project, string $subscription): RedirectResponse
    {
        $this->authorize('send', $project);
        $device = $project->subscriptions()->where('is_test', true)->findOrFail($subscription);
        $device->forceFill(['test_name' => $request->validate(['test_name' => ['nullable', 'string', 'max:64']])['test_name'] ?? null])->save();

        return back()->with('status', __('Name saved.'));
    }

    public function destroy(Project $project, string $subscription): RedirectResponse
    {
        $this->authorize('send', $project);
        $project->subscriptions()->whereKey($subscription)->update(['is_test' => false, 'test_name' => null]);

        return back()->with('status', __('No longer a test device.'));
    }
}
