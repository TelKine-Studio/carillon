<?php

namespace App\Http\Controllers;

use App\Audience\Audience;
use App\Models\Project;
use App\Models\Segment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Saved audiences, defined with the filters of the API. */
class SegmentController extends Controller
{
    public function __construct(protected Audience $audience) {}

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $segments = $project->segments()->orderBy('name')->get();
        $names = [...$segments->pluck('name')->all(), ...array_values(array_diff(Segment::BUILT_IN, ['All']))];

        // Like OneSignal: subscribers per channel, and the people behind them.
        $counts = [];
        foreach ($names as $name) {
            try {
                $count = fn (string $channel) => $this->audience->query($project, ['segments' => [$name], 'channel' => $channel])->count();
                $counts[$name] = [
                    'push' => $count('push'),
                    'email' => $count('email'),
                    'sms' => $count('sms') + $count('whatsapp'),
                    'users' => $this->audience->query($project, ['segments' => [$name]])->distinct()->count('subscriptions.subscriber_id'),
                ];
            } catch (ValidationException) {
                $counts[$name] = null;
            }
        }

        return view('segments.index', ['project' => $project, 'segments' => $segments, 'counts' => $counts]);
    }

    public function create(Project $project): View
    {
        $this->authorize('send', $project);

        // Quick starts of the segments page (OneSignal "quickstart segments").
        [$name, $filters] = match (request()->query('recipe')) {
            'new' => [__('New users'), [['field' => 'first_session', 'relation' => '<', 'hours_ago' => 3]]],
            'country' => [__('Users of a country'), [['field' => 'country', 'relation' => '=', 'value' => $project->subscribers()->whereNotNull('country')->groupBy('country')->orderByRaw('count(*) desc')->value('country') ?? '']]],
            'location' => [__('Around a place'), [['field' => 'location', 'lat' => '', 'long' => '', 'radius' => 5000]]],
            'tag' => [__('Users with a tag'), [['field' => 'tag', 'key' => '', 'relation' => '=', 'value' => '']]],
            default => ['', [['field' => 'tag', 'key' => '', 'relation' => '=', 'value' => '']]],
        };

        return view('segments.form', ['project' => $project, 'segment' => new Segment(['name' => $name, 'filters' => $filters])]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('send', $project);
        $segment = $project->segments()->create($this->validated($request, $project));

        return redirect()->route('projects.segments.index', $project)->with('status', __('Segment “:name” created.', ['name' => $segment->name]));
    }

    public function edit(Project $project, Segment $segment): View
    {
        $this->authorize('send', $project);
        abort_unless($segment->project_id === $project->id, 404);

        return view('segments.form', ['project' => $project, 'segment' => $segment]);
    }

    public function update(Request $request, Project $project, Segment $segment): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($segment->project_id === $project->id, 404);

        $segment->update($this->validated($request, $project, $segment));

        return redirect()->route('projects.segments.index', $project)->with('status', __('Segment saved.'));
    }

    public function destroy(Project $project, Segment $segment): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($segment->project_id === $project->id, 404);

        $segment->delete();

        return back()->with('status', __('Segment deleted.'));
    }

    /** Devices matching filters being edited. */
    public function count(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            // Conditions still being typed are left out of the live count.
            $filters = array_values(array_filter($this->filters($request), fn (array $f) => isset($f['operator'])
                || ($f['field'] === 'location' && isset($f['lat'], $f['long'], $f['radius']))
                || ($f['field'] !== 'location' && (! in_array($f['field'], ['tag', 'event', 'event_value'], true) || filled($f['key'] ?? null))
                    && (in_array($f['relation'], ['exists', 'not_exists'], true) || filled($f['value'] ?? null) || isset($f['hours_ago'])))));
            $total = $filters === [] || collect($filters)->every(fn ($f) => isset($f['operator'])) ? null : $this->audience->query($project, ['filters' => $filters])->count();
        } catch (ValidationException $e) {
            return response()->json(['total' => null, 'error' => collect($e->errors())->flatten()->first()]);
        }

        return response()->json(['total' => $total]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Project $project, ?Segment $segment = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::notIn([...Segment::BUILT_IN, ...array_keys(Segment::ALIASES)]),
                Rule::unique('segments')->where('project_id', $project->id)->ignore($segment?->id)],
        ], ['name.not_in' => __('This name is reserved by a built-in segment.')]);

        $filters = $this->filters($request);
        if ($filters === []) {
            throw ValidationException::withMessages(['filters' => __('Add at least one condition.')]);
        }

        // Rejects unknown fields or relations before saving.
        $this->audience->query($project, ['filters' => $filters])->limit(1)->get();

        return ['name' => $data['name'], 'filters' => $filters];
    }

    /** @return list<array<string, mixed>> */
    protected function filters(Request $request): array
    {
        $raw = json_decode((string) $request->input('filters', '[]'), true);
        if (! is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $filter) {
            if (($filter['operator'] ?? null) === 'OR') {
                $filters[] = ['operator' => 'OR'];

                continue;
            }
            if (! isset($filter['field'])) {
                continue;
            }
            if ($filter['field'] === 'location') {
                $filters[] = array_filter([
                    'field' => 'location',
                    'lat' => is_numeric($filter['lat'] ?? null) ? (float) $filter['lat'] : null,
                    'long' => is_numeric($filter['long'] ?? null) ? (float) $filter['long'] : null,
                    'radius' => is_numeric($filter['radius'] ?? null) ? (float) $filter['radius'] : null,
                ], fn ($v) => $v !== null);

                continue;
            }
            $filters[] = array_filter([
                'field' => (string) $filter['field'],
                'key' => isset($filter['key']) ? trim((string) $filter['key']) : null,
                'relation' => (string) ($filter['relation'] ?? '='),
                'value' => isset($filter['value']) ? trim((string) $filter['value']) : null,
                'hours_ago' => isset($filter['hours_ago']) && $filter['hours_ago'] !== '' ? (float) $filter['hours_ago'] : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        // No dangling OR.
        while ($filters !== [] && (end($filters)['operator'] ?? null) === 'OR') {
            array_pop($filters);
        }

        return array_values($filters);
    }
}
