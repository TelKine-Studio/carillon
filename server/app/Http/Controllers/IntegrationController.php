<?php

namespace App\Http\Controllers;

use App\Integrations\Catalog;
use App\Integrations\S3Export;
use App\Integrations\SqlSync;
use App\Jobs\DeliverIntegrationEvent;
use App\Jobs\RunIntegration;
use App\Models\Integration;
use App\Models\Project;
use App\Support\Audit;
use App\Webhooks\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Integrations of a project: connections and catalog (Data → Integrations). */
class IntegrationController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $integrations = $project->integrations()->orderBy('name')->get();
        $webhooks = $project->webhooks()->orderBy('url')->get();
        $tab = $request->query('tab', $integrations->isEmpty() && $webhooks->isEmpty() ? 'catalog' : 'connections');

        return view('integrations.index', [
            'project' => $project,
            'integrations' => $integrations,
            'webhooks' => $webhooks,
            'catalog' => Catalog::all($project),
            'categories' => Catalog::categories(),
            'tab' => in_array($tab, ['connections', 'catalog'], true) ? $tab : 'connections',
        ]);
    }

    public function create(Project $project, string $card): View
    {
        $this->authorize('configure', $project);
        $card = Catalog::find($project, $card) ?? abort(404);

        return view('integrations.form', [
            'project' => $project,
            'card' => $card,
            'integration' => new Integration([
                'kind' => $card['kind'],
                'name' => $card['name'],
                'events' => Integration::DEFAULT_EVENTS[Integration::KINDS[$card['kind']][1]] ?? [],
                'config' => [],
            ]),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $card = Catalog::find($project, (string) $request->input('card')) ?? abort(404);
        $kind = $card['kind'];

        [$attributes, $secret] = $this->validated($request, $kind, null);
        $integration = $project->integrations()->create($attributes + [
            'kind' => $kind,
            'secret' => $kind === 'segment' ? Integration::newWriteKey() : $secret,
            'created_by' => $request->user()->id,
        ]);
        Webhooks::flush();
        Audit::log('integration.created', $project, ['kind' => $kind, 'name' => $integration->name]);

        return redirect()->route('projects.integrations.show', [$project, $integration])->with('status', __('Integration added.'));
    }

    public function show(Project $project, Integration $integration): View
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id, 404);

        return view('integrations.form', [
            'project' => $project,
            'card' => Catalog::find($project, $integration->kind) ?? abort(404),
            'integration' => $integration,
        ]);
    }

    public function update(Request $request, Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id, 404);

        if ($request->has('enabled')) {
            $enabled = $request->boolean('enabled');
            $integration->forceFill(['enabled' => $enabled, 'failures' => 0, 'disabled_reason' => $enabled ? null : 'manual'])->save();
            Webhooks::flush();

            return back()->with('status', $enabled ? __('Integration enabled.') : __('Integration paused.'));
        }

        [$attributes, $secret] = $this->validated($request, $integration->kind, $integration);
        $integration->fill($attributes);
        if (filled($secret) && $integration->kind !== 'segment') {
            $integration->secret = $secret;
        }
        $integration->save();
        Webhooks::flush();
        Audit::log('integration.updated', $project, ['kind' => $integration->kind, 'name' => $integration->name]);

        return back()->with('status', __('Integration saved.'));
    }

    /** Checks the settings right away: test event, first rows of the query, test file. */
    public function test(Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id, 404);

        try {
            $message = match (true) {
                $integration->isStream() => $this->testStream($integration),
                in_array($integration->kind, ['postgres', 'mysql'], true) => $this->testQuery($integration),
                $integration->kind === 's3' => __('Test file written: :key', ['key' => S3Export::test($integration)]),
                default => abort(404),
            };
        } catch (Throwable $e) {
            $integration->forceFill(['last_run_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return back()->withErrors(['alert' => __('The test failed: :error', ['error' => mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300)])]);
        }

        return back()->with('status', $message);
    }

    /** Runs a sync or an export now, in the queue. */
    public function run(Request $request, Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id && in_array($integration->kind, ['postgres', 'mysql', 's3'], true), 404);

        $day = $integration->kind === 's3' ? ($request->date('day')?->toDateString() ?? now()->subDay()->toDateString()) : null;
        RunIntegration::dispatch($integration->id, $day);

        return back()->with('status', $integration->kind === 's3' ? __('Export of :day started: reload the page in a moment.', ['day' => $day]) : __('Sync started: reload the page in a moment.'));
    }

    public function rotate(Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id && $integration->kind === 'segment', 404);

        $integration->forceFill(['secret' => Integration::newWriteKey()])->save();
        Audit::log('integration.key_rotated', $project, ['name' => $integration->name]);

        return back()->with('status', __('New write key generated: update your source.'));
    }

    public function destroy(Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($integration->project_id === $project->id, 404);

        $integration->delete();
        Webhooks::flush();
        Audit::log('integration.deleted', $project, ['kind' => $integration->kind, 'name' => $integration->name]);

        return redirect()->route('projects.integrations.index', $project)->with('status', __('Integration deleted.'));
    }

    protected function testStream(Integration $integration): string
    {
        (new DeliverIntegrationEvent($integration->id, Webhooks::payload('integration.test', [
            'subscriber_id' => 'carillon-test',
            'platform' => 'test',
        ]), test: true))->handle();
        $integration->forceFill(['last_run_at' => now(), 'last_success_at' => now(), 'last_error' => null])->save();

        return __('Test event delivered.');
    }

    protected function testQuery(Integration $integration): string
    {
        $query = SqlSync::query((string) $integration->setting('query'));
        $name = SqlSync::connect($integration);
        try {
            SqlSync::readOnly(DB::connection($name));
            $rows = DB::connection($name)->select('select * from ('.$query.') as carillon_preview limit 5');
        } finally {
            DB::purge($name);
        }
        $columns = array_keys((array) ($rows[0] ?? []));
        $id = $integration->setting('id_column', 'external_id');
        if ($rows && ! in_array($id, $columns, true)) {
            throw new \InvalidArgumentException(__('The query must return a column “:column” with the external id of each user.', ['column' => $id]));
        }

        return $rows
            ? __('Connection OK. Columns: :columns.', ['columns' => implode(', ', $columns)])
            : __('Connection OK, but the query returns no row.');
    }

    /** @return array{0: array<string, mixed>, 1: ?string} */
    protected function validated(Request $request, string $kind, ?Integration $integration): array
    {
        $secretRule = $integration ? 'nullable' : 'required';
        $rules = ['name' => ['required', 'string', 'max:80']];
        $rules += match ($kind) {
            'posthog' => ['secret' => [$secretRule, 'string', 'max:200'], 'region' => ['required', Rule::in(['us', 'eu', 'custom'])], 'host' => ['nullable', 'required_if:region,custom', 'url:https,http', 'max:255'], 'prefix' => ['nullable', 'string', 'max:40']],
            'mixpanel' => ['secret' => [$secretRule, 'string', 'max:200'], 'region' => ['required', Rule::in(['us', 'eu', 'in'])], 'prefix' => ['nullable', 'string', 'max:40']],
            'amplitude' => ['secret' => [$secretRule, 'string', 'max:200'], 'region' => ['required', Rule::in(['us', 'eu'])], 'prefix' => ['nullable', 'string', 'max:40']],
            'slack' => ['secret' => [$secretRule, 'url:https', 'starts_with:https://hooks.slack.com/', 'max:500']],
            'discord' => ['secret' => [$secretRule, 'url:https', 'regex:#^https://(discord\.com|discordapp\.com|ptb\.discord\.com|canary\.discord\.com)/api/webhooks/#', 'max:500']],
            'segment' => ['create_users' => ['boolean'], 'skip_traits' => ['nullable', 'string', 'max:500']],
            'postgres', 'mysql' => [
                'host' => ['required', 'string', 'max:255'], 'port' => ['required', 'integer', 'between:1,65535'],
                'database' => ['required', 'string', 'max:128'], 'username' => ['required', 'string', 'max:128'],
                'secret' => [$secretRule, 'string', 'max:500'], 'sslmode' => ['nullable', Rule::in(['disable', 'prefer', 'require', 'verify-full'])],
                'query' => ['required', 'string', 'max:10000', function ($attribute, $value, $fail) {
                    try {
                        SqlSync::query($value);
                    } catch (\InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                }],
                'id_column' => ['required', 'string', 'max:64'], 'schedule' => ['required', Rule::in(['hourly', 'daily'])], 'create_users' => ['boolean'],
            ],
            's3' => [
                'endpoint' => ['nullable', 'url:https,http', 'max:255'], 'region' => ['required', 'string', 'max:40'], 'bucket' => ['required', 'string', 'max:63'],
                'prefix' => ['nullable', 'string', 'max:200'], 'key_id' => ['required', 'string', 'max:128'], 'secret' => [$secretRule, 'string', 'max:200'], 'path_style' => ['boolean'],
            ],
        };
        if (in_array($kind, Integration::STREAMS, true)) {
            $rules += ['events' => ['required', 'array', 'min:1'], 'events.*' => [Rule::in(Integration::EVENTS)]];
        }

        $data = $request->validate($rules, ['events.required' => __('Choose at least one event.')]);
        $config = collect($data)->except(['name', 'secret', 'events'])->all();
        foreach (['create_users', 'path_style'] as $flag) {
            if (array_key_exists($flag, $rules)) {
                $config[$flag] = $request->boolean($flag);
            }
        }

        $attributes = ['name' => $data['name'], 'config' => $config];
        if (isset($data['events'])) {
            $attributes['events'] = array_values(array_unique($data['events']));
        }

        return [$attributes, $data['secret'] ?? null];
    }
}
