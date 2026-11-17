<?php

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Audience\Audience;
use App\Audience\Duplicates;
use App\Journeys\JourneyRunner;
use App\Models\CustomEvent;
use App\Models\Delivery;
use App\Models\Project;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Audience of a project: devices and the people they belong to. */
class SubscriberController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $search = trim((string) $request->query('q'));
        $platform = in_array($request->query('platform'), Subscription::TYPES, true) ? $request->query('platform') : null;
        $state = in_array($request->query('state'), ['subscribed', 'unsubscribed', 'never', 'invalid'], true) ? $request->query('state') : null;
        $segments = [...array_values(array_diff(Segment::BUILT_IN, ['All', 'Subscribed Users'])), ...$project->segments()->orderBy('name')->pluck('name')->all()];
        $segment = in_array($request->query('segment'), $segments, true) ? $request->query('segment') : null;
        $reason = in_array($request->query('reason'), [...Subscription::REASONS, 'unknown'], true) ? $request->query('reason') : null;
        // Like OneSignal: one row per person (default) or per device.
        $view = in_array($request->query('view'), ['devices', 'test'], true) ? $request->query('view') : 'people';
        // Sortable columns of the tables (devices: like OneSignal).
        $sortable = ['last_active' => 'last_active_at', 'first_active' => 'created_at', 'sessions' => 'sessions', 'usage' => 'usage_seconds', 'unsubscribed' => 'unsubscribed_at'];
        $sort = array_key_exists((string) $request->query('sort'), $sortable) ? $request->query('sort') : 'last_active';
        if ($view === 'people' && ! in_array($sort, ['last_active', 'first_active'], true)) {
            $sort = 'last_active';
        }
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sort];

        // Conditions on the devices, shared by both views.
        $devices = fn (Builder $q) => $q
            ->when($platform, fn (Builder $q) => $q->where('subscriptions.type', $platform))
            ->when($state === 'subscribed', fn (Builder $q) => $q->where('subscriptions.enabled', true)->whereNull('subscriptions.invalid_at'))
            ->when($state === 'unsubscribed', fn (Builder $q) => $q->where('subscriptions.enabled', false)->whereNull('subscriptions.invalid_at')->whereNotNull('subscriptions.subscribed_at'))
            ->when($state === 'never', fn (Builder $q) => $q->where('subscriptions.enabled', false)->whereNull('subscriptions.invalid_at')->whereNull('subscriptions.subscribed_at'))
            ->when($segment, fn (Builder $q) => $q->whereIn('subscriptions.id', app(Audience::class)->query($project, ['segments' => [$segment], 'any_permission' => true])->select('subscriptions.id')))
            ->when($state === 'invalid', fn (Builder $q) => $q->whereNotNull('subscriptions.invalid_at'))
            ->when($reason === 'unknown', fn (Builder $q) => $q->whereNull('subscriptions.status_reason')
                ->where(fn (Builder $q) => $q->where('subscriptions.enabled', false)->orWhereNotNull('subscriptions.invalid_at')))
            ->when($reason && $reason !== 'unknown', fn (Builder $q) => $q->where('subscriptions.status_reason', $reason));

        if ($view === 'people') {
            $results = $project->subscribers()
                ->with(['subscriptions' => fn ($q) => $q->select('id', 'subscriber_id', 'type', 'enabled', 'invalid_at', 'is_test', 'device_model')->orderBy('type')])
                ->when($platform || $state || $reason || $segment, fn (Builder $q) => $q->whereHas('subscriptions', $devices))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    // An id is a UUID: PostgreSQL refuses to compare it to any other text.
                    ->when(Str::isUuid($search), fn (Builder $q) => $q->orWhere('subscribers.id', $search)->orWhereHas('subscriptions', fn (Builder $s) => $s->whereKey($search)))
                    ->orWhereContains(['external_id', 'tags'], $search)
                    ->orWhereHas('subscriptions', fn (Builder $s) => $s->where('onesignal_id', $search)->orWhereContains(['device_model', 'test_name'], $search))))
                ->orderByRaw("{$column} is null")
                ->orderBy($column, $direction)
                ->paginate(25)
                ->withQueryString();
        } else {
            $results = Subscription::query()
                ->with('subscriber')
                ->where('subscriptions.project_id', $project->id)
                ->when($view === 'test', fn (Builder $q) => $q->where('subscriptions.is_test', true))
                ->where($devices)
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->when(Str::isUuid($search), fn (Builder $q) => $q->orWhere('subscriptions.id', $search)->orWhere('subscriptions.subscriber_id', $search))
                    ->orWhereHas('subscriber', fn (Builder $s) => $s->whereContains(['external_id', 'tags'], $search))
                    ->orWhere('subscriptions.onesignal_id', $search)
                    ->orWhereContains(['device_model', 'test_name'], $search)))
                ->orderByRaw("subscriptions.{$column} is null")
                ->orderBy("subscriptions.{$column}", $direction)
                ->paginate(25)
                ->withQueryString();
        }

        $base = Subscription::query()->where('project_id', $project->id);

        return view('audience.index', [
            'project' => $project,
            'results' => $results,
            'view' => $view,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
            'platform' => $platform,
            'state' => $state,
            'reason' => $reason,
            'segment' => $segment,
            'segments' => $segments,
            'totals' => [
                'subscribed' => (clone $base)->where('enabled', true)->whereNull('invalid_at')->count(),
                'devices' => (clone $base)->count(),
                'people' => $project->subscribers()->count(),
                'identified' => $project->subscribers()->whereNotNull('external_id')->count(),
                'duplicates' => Duplicates::count($project),
                'active' => (clone $base)->where('last_active_at', '>=', now()->subDays(7))->count(),
                'test' => (clone $base)->where('is_test', true)->count(),
            ],
        ]);
    }

    public function show(Project $project, Subscriber $subscriber): View
    {
        $this->authorize('view', $project);
        abort_unless($subscriber->project_id === $project->id, 404);

        $subscriber->load('subscriptions');

        $deliveries = Delivery::query()->whereIn('subscription_id', $subscriber->subscriptions->pluck('id'));

        return view('audience.show', [
            'project' => $project,
            'subscriber' => $subscriber,
            'events' => CustomEvent::query()->with('message')->where('subscriber_id', $subscriber->id)->latest('occurred_at')->limit(50)->get(),
            'activity' => [
                'sessions' => (int) $subscriber->subscriptions->sum('sessions'),
                'received' => (clone $deliveries)->where('status', 'sent')->where('sent_at', '>=', now()->subDays(7))->count(),
                'clicked' => (clone $deliveries)->whereNotNull('clicked_at')->where('sent_at', '>=', now()->subDays(7))->count(),
            ],
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

        $before = $subscriber->tags;
        $subscriber->tags = null;
        $subscriber->mergeTags($tags);
        $subscriber->save();
        app(JourneyRunner::class)->tagsChanged($subscriber, $before);

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
