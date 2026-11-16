<?php

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Models\Delivery;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Audience of a project: devices and the people they belong to. */
class SubscriberController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $search = trim((string) $request->query('q'));
        $platform = $request->query('platform');
        $state = $request->query('state');

        $subscriptions = Subscription::query()
            ->with('subscriber')
            ->where('subscriptions.project_id', $project->id)
            ->when(in_array($platform, Subscription::TYPES, true), fn (Builder $q) => $q->where('type', $platform))
            ->when($state === 'subscribed', fn (Builder $q) => $q->where('enabled', true)->whereNull('invalid_at'))
            ->when($state === 'unsubscribed', fn (Builder $q) => $q->where('enabled', false))
            ->when($state === 'invalid', fn (Builder $q) => $q->whereNotNull('invalid_at'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('subscriptions.id', $search)
                ->orWhereHas('subscriber', fn (Builder $s) => $s->where('external_id', 'like', "%{$search}%")->orWhere('tags', 'like', "%{$search}%"))
                ->orWhere('device_model', 'like', "%{$search}%")))
            ->latest('last_active_at')
            ->paginate(25)
            ->withQueryString();

        $base = Subscription::query()->where('project_id', $project->id);

        return view('audience.index', [
            'project' => $project,
            'subscriptions' => $subscriptions,
            'search' => $search,
            'platform' => $platform,
            'state' => $state,
            'totals' => [
                'subscribed' => (clone $base)->where('enabled', true)->whereNull('invalid_at')->count(),
                'people' => $project->subscribers()->count(),
                'identified' => $project->subscribers()->whereNotNull('external_id')->count(),
                'active' => (clone $base)->where('last_active_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    public function show(Project $project, Subscriber $subscriber): View
    {
        $this->authorize('view', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $subscriber->load('subscriptions');

        return view('audience.show', [
            'project' => $project,
            'subscriber' => $subscriber,
            'deliveries' => Delivery::query()
                ->with('message')
                ->whereIn('subscription_id', $subscriber->subscriptions->pluck('id'))
                ->latest('sent_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /** Replaces the tags with the ones of the form. */
    public function updateTags(Request $request, Project $project, Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $tags = [];
        foreach ((array) $request->input('tags', []) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $tags[mb_substr($key, 0, 128)] = (string) ($row['value'] ?? '');
            }
        }

        $subscriber->tags = null;
        $subscriber->mergeTags($tags);
        $subscriber->save();

        return back()->with('status', __('Tags saved.'));
    }

    /** Test notification to the devices of this person. */
    public function test(Request $request, Project $project, Subscriber $subscriber, CreateMessage $create): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $message = $create->handle($project, [
                'name' => __('Test'),
                'headings' => filled($data['title'] ?? null) ? ['en' => $data['title']] : null,
                'contents' => ['en' => $data['body']],
                'include_subscription_ids' => $subscriber->subscriptions()->pluck('id')->all(),
            ], $request->user());
        } catch (ValidationException) {
            return back()->withErrors(['alert' => __('No device of this person can receive notifications.')]);
        }

        return redirect()->route('projects.messages.show', [$project, $message])->with('status', __('Test notification sent.'));
    }

    /** Marks a device as a test device (or not): it receives the tests of the composer. */
    public function testDevice(Request $request, Project $project, Subscriber $subscriber, string $subscription): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $device = $subscriber->subscriptions()->findOrFail($subscription);
        $device->forceFill([
            'is_test' => $request->boolean('is_test'),
            'test_name' => $request->boolean('is_test') ? ($device->test_name ?? $device->device_model) : null,
        ])->save();

        return back()->with('status', $device->is_test
            ? __('Test device added: it receives the tests sent from the composer.')
            : __('No longer a test device.'));
    }

    public function destroy(Project $project, Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $subscriber->delete();

        return redirect()->route('projects.subscribers.index', $project)->with('status', __('Person and devices deleted.'));
    }
}
