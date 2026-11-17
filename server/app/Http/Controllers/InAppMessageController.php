<?php

namespace App\Http\Controllers;

use App\Models\InAppMessage;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Banners and pop-ups shown inside the open app (OneSignal "In-app"). */
class InAppMessageController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('in-app.index', [
            'project' => $project,
            'messages' => $project->inAppMessages()->latest()->get(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('send', $project);

        // Starting points, like the templates of OneSignal.
        $recipes = [
            'announce' => ['name' => __('New feature'), 'layout' => 'modal', 'title' => __('New in the app'), 'body' => __('Discover what we just added for you.'), 'buttons' => [['id' => 'button-1', 'text' => __('Discover'), 'action' => 'dismiss'], ['id' => 'button-2', 'text' => __('Later'), 'action' => 'dismiss']]],
            'offer' => ['name' => __('Special offer'), 'layout' => 'modal', 'title' => __('An offer for you'), 'body' => __('-20% today only. Do not miss it!'), 'buttons' => [['id' => 'button-1', 'text' => __('I take it'), 'action' => 'dismiss']]],
            'update' => ['name' => __('Update required'), 'layout' => 'fullscreen', 'title' => __('Update the app'), 'body' => __('A new version is available with improvements and fixes.'), 'buttons' => [['id' => 'button-1', 'text' => __('Update'), 'action' => 'url', 'url' => 'https://']]],
            'review' => ['name' => __('Ask for a review'), 'layout' => 'banner', 'title' => __('Do you like the app?'), 'body' => __('A review helps us a lot. It takes 10 seconds.'), 'trigger' => ['type' => 'app_open', 'min_sessions' => 5]],
        ];

        return $this->form($project, new InAppMessage([
            'layout' => 'modal',
            'trigger' => ['type' => 'app_open'],
            'frequency' => 'once',
            'buttons' => [['id' => 'ok', 'text' => __('Got it'), 'action' => 'dismiss']],
            ...($recipes[request()->query('recipe')] ?? []),
        ]), $recipes);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('send', $project);

        $message = $project->inAppMessages()->create([...$this->validated($request), 'created_by' => $request->user()->id, 'status' => 'draft']);
        Audit::log('in_app.created', $project, ['name' => $message->name]);

        return redirect()->route('projects.in-app.index', $project)->with('status', __('In-app message saved as a draft: activate it when ready.'));
    }

    public function edit(Project $project, InAppMessage $inApp): View
    {
        $this->authorize('send', $project);
        abort_unless($inApp->project_id === $project->id, 404);

        return $this->form($project, $inApp);
    }

    public function update(Request $request, Project $project, InAppMessage $inApp): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($inApp->project_id === $project->id, 404);

        $inApp->update($this->validated($request));

        return redirect()->route('projects.in-app.index', $project)->with('status', __('In-app message saved.'));
    }

    public function status(Request $request, Project $project, InAppMessage $inApp): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($inApp->project_id === $project->id, 404);

        $status = $request->validate(['status' => ['required', Rule::in(['active', 'paused'])]])['status'];
        $inApp->forceFill(['status' => $status])->save();
        Audit::log('in_app.'.($status === 'active' ? 'activated' : 'paused'), $project, ['name' => $inApp->name]);

        return back()->with('status', $status === 'active'
            ? __('In-app message active: apps show it from their next opening.')
            : __('In-app message paused.'));
    }

    public function destroy(Project $project, InAppMessage $inApp): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($inApp->project_id === $project->id, 404);

        $inApp->delete();

        return redirect()->route('projects.in-app.index', $project)->with('status', __('In-app message deleted.'));
    }

    protected function form(Project $project, InAppMessage $message, array $recipes = []): View
    {
        return view('in-app.form', [
            'project' => $project,
            'message' => $message,
            'recipes' => $recipes,
            'segments' => $project->segments()->orderBy('name')->pluck('name'),
        ]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'layout' => ['required', Rule::in(InAppMessage::LAYOUTS)],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:600'],
            'image_url' => ['nullable', 'url:https', 'max:500'],
            'buttons' => ['nullable', 'array', 'max:2'],
            'buttons.*.text' => ['nullable', 'string', 'max:40'],
            'buttons.*.action' => ['nullable', Rule::in(['dismiss', 'url'])],
            'buttons.*.url' => ['nullable', 'string', 'max:500'],
            'trigger_type' => ['required', Rule::in(['app_open', 'event'])],
            'trigger_event' => ['nullable', 'required_if:trigger_type,event', 'string', 'max:128'],
            'trigger_delay' => ['nullable', 'integer', 'min:0', 'max:600'],
            'min_sessions' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ab_enabled' => ['boolean'],
            'variant_b_title' => ['nullable', 'string', 'max:120'],
            'variant_b_body' => ['nullable', 'required_if:ab_enabled,1', 'string', 'max:600'],
            'segment' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(InAppMessage::FREQUENCIES)],
            'journey_only' => ['boolean'],
            'frequency_days' => ['nullable', 'required_if:frequency,every_days', 'integer', 'min:1', 'max:365'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $buttons = [];
        foreach (array_values($data['buttons'] ?? []) as $index => $button) {
            if (filled($button['text'] ?? null)) {
                $action = ($button['action'] ?? 'dismiss') === 'url' && filled($button['url'] ?? null) ? 'url' : 'dismiss';
                $buttons[] = array_filter(['id' => 'button-'.($index + 1), 'text' => $button['text'], 'action' => $action, 'url' => $action === 'url' ? $button['url'] : null]);
            }
        }

        return [
            'name' => $data['name'],
            'layout' => $data['layout'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'image_url' => $data['image_url'] ?? null,
            'buttons' => $buttons ?: null,
            'trigger' => array_filter([
                'type' => $data['trigger_type'],
                'event' => $data['trigger_type'] === 'event' ? $data['trigger_event'] : null,
                // Seconds after the opening (or the event) before it shows.
                'delay_seconds' => ! empty($data['trigger_delay']) ? (int) $data['trigger_delay'] : null,
                'min_sessions' => ! empty($data['min_sessions']) && (int) $data['min_sessions'] > 1 ? (int) $data['min_sessions'] : null,
            ]),
            'variant_b' => $request->boolean('ab_enabled') && filled($data['variant_b_body'] ?? null)
                ? ['title' => $data['variant_b_title'] ?? null, 'body' => $data['variant_b_body']]
                : null,
            'segment' => ($data['segment'] ?? null) ?: null,
            'frequency' => $data['frequency'],
            'journey_only' => $request->boolean('journey_only'),
            'frequency_days' => $data['frequency'] === 'every_days' ? (int) $data['frequency_days'] : null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }
}
