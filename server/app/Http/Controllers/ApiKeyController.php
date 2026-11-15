<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        [$key, $plain] = ApiKey::generate($project, $data['name'], $request->user());
        Audit::log('api_key.created', $key, ['project' => $project->app_id, 'name' => $key->name]);

        return back()
            ->with('status', __('API key created. Copy it now: it will not be shown again.'))
            ->with('plain_api_key', $plain);
    }

    public function destroy(Project $project, ApiKey $apiKey): RedirectResponse
    {
        $this->authorize('configure', $project);

        Audit::log('api_key.revoked', $apiKey, ['project' => $project->app_id, 'name' => $apiKey->name]);
        $apiKey->delete();

        return back()->with('status', __('API key revoked. Requests using it are now refused.'));
    }
}
