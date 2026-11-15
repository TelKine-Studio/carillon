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

        $counts = [];
        foreach (Segment::BUILT_IN as $name) {
            if ($name !== 'All') {
                $counts[$name] = $this->audience->query($project, ['segments' => [$name]])->count();
            }
        }

        $segments = $project->segments()->orderBy('name')->get();
        foreach ($segments as $segment) {
            try {
                $counts[$segment->name] = $this->audience->query($project, ['segments' => [$segment->name]])->count();
            } catch (ValidationException) {
                $counts[$segment->name] = null;
            }
        }

        return view('segments.index', ['project' => $project, 'segments' => $segments, 'counts' => $counts]);
    }

    public function create(Project $project): View
    {
        $this->authorize('send', $project);

        return view('segments.form', ['project' => $project, 'segment' => new Segment(['filters' => [['field' => 'tag', 'key' => '', 'relation' => '=', 'value' => '']]])]);
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
                || (($f['field'] !== 'tag' || filled($f['key'] ?? null))
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
            'name' => ['required', 'string', 'max:100', Rule::notIn(Segment::BUILT_IN),
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
