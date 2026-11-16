<?php

namespace App\Http\Controllers;

use App\Actions\RunAbTest;
use App\Models\AbTest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Results of an A/B test, and sending of the winning version. */
class AbTestController extends Controller
{
    public function show(Project $project, AbTest $abTest): View
    {
        $this->authorize('view', $project);
        abort_unless($abTest->project_id === $project->id, 404);

        return view('messages.ab-test', [
            'project' => $project,
            'test' => $abTest->load('messages'),
        ]);
    }

    public function winner(Request $request, Project $project, AbTest $abTest, RunAbTest $run): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($abTest->project_id === $project->id, 404);

        $winner = $abTest->messages()->where('ab_variant', $request->input('variant'))->firstOrFail();

        try {
            $message = $run->sendWinner($abTest, $winner, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors(['alert' => collect($e->errors())->flatten()->first()]);
        }

        return redirect()->route('projects.messages.show', [$project, $message])
            ->with('celebrate', true)
            ->with('status', __('Version :letter is on its way to :count devices.', ['letter' => $winner->ab_variant, 'count' => number_format($message->recipients, 0, ',', ' ')]));
    }
}
