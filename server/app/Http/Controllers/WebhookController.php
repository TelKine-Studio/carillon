<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhook;
use App\Models\Project;
use App\Models\Webhook;
use App\Support\Audit;
use App\Webhooks\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Webhooks of a project (Settings → Webhooks). */
class WebhookController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $webhook = $project->webhooks()->create($this->validated($request) + [
            'secret' => Webhook::newSecret(),
            'created_by' => $request->user()->id,
        ]);
        Webhooks::flush();
        Audit::log('webhook.created', $project, ['url' => $webhook->url]);

        return back()->with('status', __('Webhook added. Verify its signature with the secret shown below.'))->with('open_webhook', $webhook->id);
    }

    public function update(Request $request, Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($webhook->project_id === $project->id, 404);

        if ($request->has('enabled')) {
            $enabled = $request->boolean('enabled');
            $webhook->forceFill(['enabled' => $enabled, 'failures' => 0, 'disabled_reason' => $enabled ? null : 'manual'])->save();
            Webhooks::flush();

            return back()->with('status', $enabled ? __('Webhook enabled.') : __('Webhook disabled.'));
        }

        $webhook->update($this->validated($request));
        Webhooks::flush();

        return back()->with('status', __('Webhook saved.'))->with('open_webhook', $webhook->id);
    }

    public function rotate(Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($webhook->project_id === $project->id, 404);

        $webhook->forceFill(['secret' => Webhook::newSecret()])->save();
        Audit::log('webhook.secret_rotated', $project, ['url' => $webhook->url]);

        return back()->with('status', __('New secret generated: update your server.'))->with('open_webhook', $webhook->id);
    }

    public function test(Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($webhook->project_id === $project->id, 404);

        // Sent right away, without retries, so the result shows on reload.
        try {
            (new DeliverWebhook($webhook->id, Webhooks::payload('webhook.test', ['message' => 'Hello from Carillon']), test: true))->handle();
            $ok = true;
        } catch (\RuntimeException) {
            $ok = false;
        }

        $response = back()->with('open_webhook', $webhook->id);

        return $ok
            ? $response->with('status', __('Test event delivered.'))
            : $response->withErrors(['alert' => __('The test event could not be delivered: see the attempts below.')]);
    }

    public function destroy(Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless($webhook->project_id === $project->id, 404);

        $webhook->delete();
        Webhooks::flush();
        Audit::log('webhook.deleted', $project, ['url' => $webhook->url]);

        return back()->with('status', __('Webhook deleted.'));
    }

    /** @return array{url: string, events: list<string>} */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(Webhook::EVENTS)],
        ], ['events.required' => __('Choose at least one event.')]);

        return ['url' => $data['url'], 'events' => array_values(array_unique($data['events']))];
    }
}
