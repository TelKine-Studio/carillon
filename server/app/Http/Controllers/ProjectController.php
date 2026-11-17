<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public const SECTIONS = ['general', 'platforms', 'api-keys', 'webhooks', 'members'];

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

        $reachable = $project->subscriptions()->where('enabled', true)->whereNull('invalid_at');
        $since = now()->subDays(29)->startOfDay();
        $daily = $project->subscriptions()
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $recent = $project->messages()->where('is_test', false)->where('status', '!=', 'draft')->where('created_at', '>=', now()->subDays(30));
        // The 30 days before, to show the trend as OneSignal does.
        $before = $project->messages()->where('is_test', false)->where('status', '!=', 'draft')->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)]);

        return view('projects.show', [
            'project' => $project,
            'role' => $request->user()->roleIn($project),
            'stats' => [
                'devices' => (clone $reachable)->count(),
                'platforms' => (clone $reachable)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
                'new' => collect(range(29, 0))->map(fn ($d) => (int) ($daily[now()->subDays($d)->toDateString()] ?? 0))->all(),
                'messages' => (clone $recent)->count(),
                'delivered' => (int) (clone $recent)->sum('successful'),
                'clicked' => (int) (clone $recent)->sum('clicked'),
                'previous' => [
                    'new' => $project->subscriptions()->whereBetween('created_at', [$since->copy()->subDays(30), $since])->count(),
                    'messages' => (clone $before)->count(),
                    'delivered' => (int) (clone $before)->sum('successful'),
                    'clicked' => (int) (clone $before)->sum('clicked'),
                ],
            ],
            'latest' => $project->messages()->latest()->limit(5)->get(),
            'steps' => [
                ['done' => $project->configuredProviders() !== [], 'title' => __('Add the push keys of your app'), 'text' => __('Apple (APNs), Android (Firebase) or web push.'), 'href' => route('projects.settings', [$project, 'platforms'])],
                ['done' => $project->apiKeys()->exists(), 'title' => __('Create an API key'), 'text' => __('Your server uses it to send notifications.'), 'href' => route('projects.settings', [$project, 'api-keys'])],
                ['done' => $project->subscriptions()->exists(), 'title' => __('Integrate the SDK in your app'), 'text' => __('Flutter or web: a few lines with the App ID below.'), 'href' => null],
                ['done' => $project->messages()->exists(), 'title' => __('Send your first notification'), 'text' => __('From the dashboard or the API.'), 'href' => null],
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
            'webhooks' => $section === 'webhooks' ? $project->webhooks()->with(['deliveries' => fn ($q) => $q->latest('id')->limit(10)])->oldest()->get() : collect(),
            'members' => $section === 'members' ? $project->members()->orderBy('name')->get() : collect(),
            'invitations' => $section === 'members' ? Invitation::query()->pending()->where('project_id', $project->id)->latest()->get() : collect(),
            'candidates' => $section === 'members' && $role->canConfigure()
                ? User::query()->where('is_active', true)->whereNotIn('id', $project->members()->pluck('users.id'))->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    /** Whether the SDK reports the position of devices whose app has the permission. */
    public function updateLocation(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $on = $request->boolean('collect_location');

        $project->update(['settings' => array_merge($project->settings ?? [], ['collect_location' => $on])]);
        if (! $on) {
            $this->forgetLocations($project);
        }

        return back()->with('success', $on ? __('Positions are collected again.') : __('Positions are no longer collected; the stored ones are erased.'));
    }

    /** Identity verification of the SDK logins (external id signed by the server of the app). */
    public function updateIdentity(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $settings = $project->settings ?? [];

        if ($request->boolean('rotate') || ! filled($settings['identity_secret'] ?? null)) {
            $settings['identity_secret'] = Crypt::encryptString(Str::random(48));
        }
        if ($request->has('identity_verification') && filled($settings['identity_secret'] ?? null)) {
            $settings['identity_verification'] = $request->boolean('identity_verification');
        }
        $project->update(['settings' => $settings]);
        Audit::log('project.identity_verification', $project, ['enabled' => (bool) ($settings['identity_verification'] ?? false), 'rotated' => $request->boolean('rotate')]);

        return back()->with('success', match (true) {
            $request->boolean('rotate') => __('New identity secret generated: update your server.'),
            (bool) ($settings['identity_verification'] ?? false) => __('Identity verification is on: logins without a valid hash are refused.'),
            default => __('Identity verification is off.'),
        });
    }

    public function destroyLocations(Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $this->forgetLocations($project);

        return back()->with('success', __('Stored positions erased.'));
    }

    protected function forgetLocations(Project $project): void
    {
        $project->subscriptions()->whereNotNull('latitude')->update(['latitude' => null, 'longitude' => null, 'location_accuracy' => null, 'located_at' => null]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'default_url' => ['nullable', 'url:http,https', 'max:500'],
            'default_ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
            'frequency_cap' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $project->update([
            'name' => $data['name'],
            'settings' => array_merge($project->settings ?? [], [
                'default_url' => $data['default_url'] ?? null,
                'default_ttl' => isset($data['default_ttl']) ? (int) $data['default_ttl'] : null,
                'frequency_cap' => ! empty($data['frequency_cap']) ? (int) $data['frequency_cap'] : null,
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
