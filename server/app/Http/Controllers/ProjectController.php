<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public const SECTIONS = ['general', 'platforms', 'api-keys', 'members'];

    public function index(Request $request): View
    {
        $projects = $request->user()->accessibleProjects()
            ->with('credentials')
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('projects.index', ['projects' => $projects]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $project = DB::transaction(function () use ($request, $data) {
            $project = Project::create(['name' => $data['name'], 'created_by' => $request->user()->id]);
            $project->addMember($request->user(), ProjectRole::Owner);

            return $project;
        });

        $this->storeIcon($request, $project);
        Audit::log('project.created', $project, ['name' => $project->name]);

        return redirect()->route('projects.settings', [$project, 'platforms'])
            ->with('status', __('Project created. Add the push keys of your app to start sending.'));
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $project->load('credentials');

        return view('projects.show', [
            'project' => $project,
            'role' => $request->user()->roleIn($project),
            'steps' => [
                ['done' => $project->configuredProviders() !== [], 'title' => __('Add the push keys of your app'), 'text' => __('Apple (APNs), Android (Firebase) or web push.'), 'href' => route('projects.settings', [$project, 'platforms'])],
                ['done' => $project->apiKeys()->exists(), 'title' => __('Create an API key'), 'text' => __('Your server uses it to send notifications.'), 'href' => route('projects.settings', [$project, 'api-keys'])],
                ['done' => false, 'title' => __('Integrate the SDK in your app'), 'text' => __('Flutter or web: a few lines with the App ID below.'), 'href' => null],
                ['done' => false, 'title' => __('Send your first notification'), 'text' => __('From the dashboard or the API.'), 'href' => null],
            ],
        ]);
    }

    public function settings(Request $request, Project $project, string $section = 'general'): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $this->authorize('view', $project);

        $role = $request->user()->roleIn($project);

        return view('projects.settings.'.$section, [
            'project' => $project->load('credentials'),
            'section' => $section,
            'role' => $role,
            'canConfigure' => $role->canConfigure(),
            'apiKeys' => $section === 'api-keys' ? $project->apiKeys()->with('creator')->latest()->get() : collect(),
            'members' => $section === 'members' ? $project->members()->orderBy('name')->get() : collect(),
            'invitations' => $section === 'members' ? Invitation::query()->pending()->where('project_id', $project->id)->latest()->get() : collect(),
            'candidates' => $section === 'members' && $role->canConfigure()
                ? User::query()->where('is_active', true)->whereNotIn('id', $project->members()->pluck('users.id'))->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'default_url' => ['nullable', 'url:http,https', 'max:500'],
            'default_ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
        ]);

        $project->update([
            'name' => $data['name'],
            'settings' => array_merge($project->settings ?? [], [
                'default_url' => $data['default_url'] ?? null,
                'default_ttl' => isset($data['default_ttl']) ? (int) $data['default_ttl'] : null,
            ]),
        ]);

        if ($request->boolean('remove_icon') && $project->icon_path) {
            Storage::disk('local')->delete($project->icon_path);
            $project->update(['icon_path' => null]);
        }
        $this->storeIcon($request, $project);

        Audit::log('project.updated', $project);

        return back()->with('status', __('Project saved.'));
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($request->input('confirm_name') !== $project->name) {
            throw ValidationException::withMessages(['confirm_name' => __('Type the name of the project to confirm.')]);
        }

        Audit::log('project.deleted', null, ['name' => $project->name, 'app_id' => $project->app_id]);
        $project->delete();

        return redirect()->route('projects.index')->with('status', __('Project deleted.'));
    }

    public function icon(Request $request, Project $project): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($project->icon_path && Storage::disk('local')->exists($project->icon_path), 404);

        return Storage::disk('local')->response($project->icon_path, null, ['Cache-Control' => 'private, max-age=604800']);
    }

    protected function storeIcon(Request $request, Project $project): void
    {
        if (! $request->hasFile('icon')) {
            return;
        }

        if ($project->icon_path) {
            Storage::disk('local')->delete($project->icon_path);
        }

        $path = $request->file('icon')->store('project-icons', 'local');
        $project->update(['icon_path' => $path]);
    }
}
