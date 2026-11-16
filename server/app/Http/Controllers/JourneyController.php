<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Automations (OneSignal "Journeys"). */
class JourneyController extends Controller
{
    /** Units of the wait step, in minutes. */
    public const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $journeys = $project->journeys()->latest()->get();
        $counts = DB::table('journey_entries')
            ->whereIn('journey_id', $journeys->pluck('id'))
            ->selectRaw('journey_id, status, count(*) as total')
            ->groupBy('journey_id', 'status')
            ->get()
            ->groupBy('journey_id');

        return view('journeys.index', ['project' => $project, 'journeys' => $journeys, 'counts' => $counts]);
    }

    public function create(Request $request, Project $project): View
    {
        $this->authorize('send', $project);

        // Starting points, like the templates of OneSignal.
        $recipe = $request->query('recipe');
        $journey = new Journey(match ($recipe) {
            'welcome' => [
                'name' => __('Welcome'),
                'trigger' => ['type' => 'new_device'],
                'steps' => [
                    ['type' => 'wait', 'amount' => 10, 'unit' => 'minutes'],
                    ['type' => 'push', 'title' => __('Welcome!'), 'body' => __('Thanks for installing the app. Here is what you can do…')],
                    ['type' => 'wait', 'amount' => 2, 'unit' => 'days'],
                    ['type' => 'push', 'title' => __('Did you know?'), 'body' => __('A tip to get the most out of the app.')],
                ],
            ],
            'winback' => [
                'name' => __('Win back inactive people'),
                'trigger' => ['type' => 'inactive', 'days' => 7],
                'reentry' => true,
                'steps' => [
                    ['type' => 'push', 'title' => __('We miss you'), 'body' => __('Come back and see what is new.')],
                    ['type' => 'wait', 'amount' => 3, 'unit' => 'days'],
                    ['type' => 'push', 'title' => __('A little something for you'), 'body' => __('Open the app to discover it.')],
                ],
            ],
            default => ['trigger' => ['type' => 'new_device'], 'steps' => [['type' => 'push', 'title' => '', 'body' => '']]],
        });

        return $this->form($project, $journey);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('send', $project);

        $journey = $project->journeys()->create([...$this->validated($request, $project), 'created_by' => $request->user()->id, 'status' => 'draft']);
        Audit::log('journey.created', $project, ['name' => $journey->name]);

        return redirect()->route('projects.journeys.show', [$project, $journey])->with('status', __('Journey saved as a draft: activate it when ready.'));
    }

    public function edit(Project $project, Journey $journey): View
    {
        $this->authorize('send', $project);
        abort_unless($journey->project_id === $project->id, 404);

        return $this->form($project, $journey);
    }

    public function update(Request $request, Project $project, Journey $journey): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($journey->project_id === $project->id, 404);

        $journey->update($this->validated($request, $project));
        Audit::log('journey.updated', $project, ['name' => $journey->name]);

        return redirect()->route('projects.journeys.show', [$project, $journey])->with('status', __('Journey saved.'));
    }

    public function show(Project $project, Journey $journey): View
    {
        $this->authorize('view', $project);
        abort_unless($journey->project_id === $project->id, 404);

        $entries = $journey->entries();
        // People waiting after each step (a wait moves them to the next index).
        $waiting = (clone $entries)->where('status', 'active')->selectRaw('step, count(*) as total')->groupBy('step')->pluck('total', 'step');
        $sent = $journey->messages()
            ->selectRaw('journey_step, sum(recipients) as recipients, sum(successful) as delivered, sum(clicked) as clicked')
            ->groupBy('journey_step')
            ->get()
            ->keyBy('journey_step');

        return view('journeys.show', [
            'project' => $project,
            'journey' => $journey,
            'totals' => (clone $entries)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'waiting' => $waiting,
            'sent' => $sent,
            'recent' => (clone $entries)->with('subscriber')->latest()->limit(10)->get(),
        ]);
    }

    public function status(Request $request, Project $project, Journey $journey): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($journey->project_id === $project->id, 404);

        $status = $request->validate(['status' => ['required', Rule::in(['active', 'paused'])]])['status'];
        if ($status === 'active' && $journey->steps === []) {
            return back()->withErrors(['alert' => __('Add at least one step first.')]);
        }

        $journey->forceFill(['status' => $status, 'activated_at' => $status === 'active' ? ($journey->activated_at ?? now()) : $journey->activated_at])->save();
        Audit::log('journey.'.($status === 'active' ? 'activated' : 'paused'), $project, ['name' => $journey->name]);

        return back()->with('status', $status === 'active'
            ? __('Journey active: people who meet the trigger enter it from now on.')
            : __('Journey paused: nobody enters or moves on until you activate it again.'));
    }

    public function destroy(Project $project, Journey $journey): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($journey->project_id === $project->id, 404);

        $journey->delete();
        Audit::log('journey.deleted', $project, ['name' => $journey->name]);

        return redirect()->route('projects.journeys.index', $project)->with('status', __('Journey deleted.'));
    }

    protected function form(Project $project, Journey $journey): View
    {
        return view('journeys.form', [
            'project' => $project,
            'journey' => $journey,
            'segments' => $project->segments()->orderBy('name')->pluck('name'),
            'templates' => $project->templates()->orderBy('name')->get(['id', 'name', 'options']),
        ]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Project $project): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger.type' => ['required', Rule::in(Journey::TRIGGERS)],
            'trigger.days' => ['nullable', 'required_if:trigger.type,inactive', 'integer', 'min:1', 'max:365'],
            'trigger.key' => ['nullable', 'required_if:trigger.type,tag', 'string', 'max:128'],
            'trigger.value' => ['nullable', 'string', 'max:255'],
            'trigger.event' => ['nullable', 'required_if:trigger.type,event', 'string', 'max:128'],
            'segment' => ['nullable', 'string', 'max:255'],
            'reentry' => ['boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:30'],
            'steps.*.type' => ['required', Rule::in(Journey::STEPS)],
            'steps.*.amount' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'steps.*.unit' => ['nullable', Rule::in(array_keys(self::UNITS))],
            'steps.*.template_id' => ['nullable', 'uuid'],
            'steps.*.title' => ['nullable', 'string', 'max:200'],
            'steps.*.body' => ['nullable', 'string', 'max:100000'],
            'steps.*.subject' => ['nullable', 'string', 'max:200'],
            'steps.*.url' => ['nullable', 'url:http,https', 'max:500'],
            'steps.*.key' => ['nullable', 'string', 'max:128'],
            'steps.*.operator' => ['nullable', Rule::in(['is', 'is_not'])],
            'steps.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $steps = [];
        foreach (array_values($data['steps']) as $index => $step) {
            $steps[] = match ($step['type']) {
                'wait' => ['type' => 'wait', 'amount' => (int) ($step['amount'] ?? 1), 'unit' => $step['unit'] ?? 'hours', 'minutes' => (int) ($step['amount'] ?? 1) * self::UNITS[$step['unit'] ?? 'hours']],
                'condition' => ['type' => 'condition', 'key' => (string) ($step['key'] ?? ''), 'operator' => $step['operator'] ?? 'is', 'value' => (string) ($step['value'] ?? '')],
                default => array_filter([
                    'type' => $step['type'],
                    'template_id' => $step['template_id'] ?? null,
                    'title' => $step['title'] ?? null,
                    'subject' => $step['subject'] ?? null,
                    'body' => $step['body'] ?? null,
                    'url' => $step['url'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            };

            $sent = end($steps);
            // A template of the other channel (the step type changed): write the content instead.
            if (! empty($sent['template_id'])) {
                $template = $project->templates()->find($sent['template_id']);
                if (! $template || ($template->option('target_channel') === 'email') !== ($sent['type'] === 'email')) {
                    unset($sent['template_id']);
                    $steps[array_key_last($steps)] = $sent;
                }
            }
            if (in_array($sent['type'], ['push', 'email'], true) && empty($sent['template_id']) && empty($sent['body'])) {
                throw ValidationException::withMessages(["steps.{$index}.body" => __('Step :number: write the message or choose a template.', ['number' => $index + 1])]);
            }
            if ($sent['type'] === 'condition' && $sent['key'] === '') {
                throw ValidationException::withMessages(["steps.{$index}.key" => __('Step :number: choose the tag to check.', ['number' => $index + 1])]);
            }
        }

        $trigger = array_filter([
            'type' => $data['trigger']['type'],
            'days' => $data['trigger']['type'] === 'inactive' ? (int) $data['trigger']['days'] : null,
            'key' => $data['trigger']['type'] === 'tag' ? $data['trigger']['key'] : null,
            'value' => $data['trigger']['type'] === 'tag' ? ($data['trigger']['value'] ?? null) : null,
            'event' => $data['trigger']['type'] === 'event' ? $data['trigger']['event'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'name' => $data['name'],
            'trigger' => $trigger,
            'segment' => $data['segment'] ?? null ?: null,
            'reentry' => $request->boolean('reentry'),
            'steps' => $steps,
        ];
    }
}
