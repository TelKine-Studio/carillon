<?php

namespace App\Http\Controllers;

use App\Actions\SaveTemplate;
use App\Http\ComposerInput;
use App\Models\Project;
use App\Models\Template;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Reusable message contents, edited with the composer. */
class TemplateController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('templates.index', [
            'project' => $project,
            'templates' => $project->templates()->withCount('messages')->with('author')->orderBy('name')->get(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('send', $project);

        return view('messages.create', $this->composer($project) + ['templateMode' => true]);
    }

    public function store(Request $request, Project $project, SaveTemplate $save): RedirectResponse
    {
        $this->authorize('send', $project);
        $template = $save->handle($project, $this->input($request), author: $request->user());
        Audit::log('template.created', $template, ['name' => $template->name]);

        return redirect()->route('projects.templates.index', $project)->with('status', __('Template “:name” created.', ['name' => $template->name]));
    }

    public function edit(Project $project, Template $template): View
    {
        $this->authorize('send', $project);
        abort_unless($template->project_id === $project->id, 404);

        return view('messages.create', array_merge($this->composer($project), ['template' => $template, 'source' => $template]));
    }

    public function update(Request $request, Project $project, Template $template, SaveTemplate $save): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($template->project_id === $project->id, 404);

        $save->handle($project, $this->input($request) + ['_replace_options' => true], $template);

        return redirect()->route('projects.templates.index', $project)->with('status', __('Template saved.'));
    }

    public function destroy(Project $project, Template $template): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($template->project_id === $project->id, 404);

        $template->delete();
        Audit::log('template.deleted', $project, ['name' => $template->name]);

        return redirect()->route('projects.templates.index', $project)->with('status', __('Template deleted.'));
    }

    /** @return array<string, mixed> */
    protected function composer(Project $project): array
    {
        $project->load('credentials');

        return [
            'project' => $project,
            'source' => null,
            'segments' => collect(),
            'languages' => MessageController::LANGUAGES,
            'platforms' => [],
        ];
    }

    /** @return array<string, mixed> */
    protected function input(Request $request): array
    {
        $input = ComposerInput::fromRequest($request);
        unset($input['send_after'], $input['delayed_option'], $input['delivery_time_of_day']);
        $input['name'] = trim((string) $request->input('template_name'));

        foreach (['headings', 'subtitle', 'contents'] as $field) {
            $input[$field] ??= null;
        }

        return $input;
    }
}
