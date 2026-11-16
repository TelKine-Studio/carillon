<?php

namespace App\Http\Controllers;

use App\Jobs\ImportAudience;
use App\Models\AudienceImport;
use App\Models\OneSignalSync;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Import of the subscribers exported from OneSignal. */
class AudienceImportController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('configure', $project);

        return view('audience.import', [
            'project' => $project->load('credentials'),
            'sync' => OneSignalSync::query()->where('project_id', $project->id)->first(),
            'imports' => AudienceImport::query()->where('project_id', $project->id)->with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $request->validate([
            'file' => ['required', 'file', 'max:102400', function ($attribute, $file, $fail) {
                $name = strtolower($file->getClientOriginalName());
                if (! str_ends_with($name, '.csv') && ! str_ends_with($name, '.csv.gz') && ! str_ends_with($name, '.gz')) {
                    $fail(__('Choose the .csv or .csv.gz file exported from OneSignal.'));
                }
            }],
        ]);

        $file = $request->file('file');
        $extension = str_ends_with(strtolower($file->getClientOriginalName()), '.gz') ? 'csv.gz' : 'csv';
        $path = $file->storeAs('imports', bin2hex(random_bytes(16)).'.'.$extension, 'local');

        $import = AudienceImport::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $path,
        ]);
        ImportAudience::dispatch($import->id);
        Audit::log('audience.imported', $project, ['file' => $import->filename]);

        return redirect()->route('projects.imports.create', $project)->with('status', __('Import started: you can leave this page.'));
    }

    /** Progress, polled by the page while an import runs. */
    public function show(Project $project, AudienceImport $import): JsonResponse
    {
        $this->authorize('configure', $project);
        abort_unless($import->project_id === $project->id, 404);

        return response()->json($import->only(['status', 'rows', 'created', 'updated', 'skipped']));
    }
}
