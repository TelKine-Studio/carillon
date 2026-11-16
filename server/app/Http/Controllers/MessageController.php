<?php

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Actions\RunAbTest;
use App\Audience\Audience;
use App\Http\ComposerInput;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Messages of a project in the dashboard: list, composer, statistics. */
class MessageController extends Controller
{
    /** Languages offered in the composer (any ISO 639-1 code works in the API). */
    public const LANGUAGES = [
        'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'pt' => 'Português', 'de' => 'Deutsch',
        'it' => 'Italiano', 'ar' => 'العربية', 'nl' => 'Nederlands', 'zh' => '中文', 'ja' => '日本語', 'ru' => 'Русский', 'tr' => 'Türkçe',
    ];

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $messages = $project->messages()
            ->with('author')
            ->when(in_array($status, Message::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereContains(['name', 'contents', 'headings'], $search)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('messages.index', [
            'project' => $project,
            'messages' => $messages,
            'status' => $status,
            'search' => $search,
            'counts' => $project->messages()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        $this->authorize('send', $project);
        $project->load('credentials');

        // "Duplicate" starts from an existing message, "Use" from a template.
        $source = $request->query('from') && Str::isUuid($request->query('from')) ? $project->messages()->find($request->query('from')) : null;
        $usedTemplate = $request->query('template') && Str::isUuid($request->query('template')) ? $project->templates()->find($request->query('template')) : null;

        return view('messages.create', [
            'project' => $project,
            'source' => $usedTemplate ?? $source,
            'draft' => $source?->status === 'draft' ? $source : null,
            'usedTemplate' => $usedTemplate,
            'templates' => $project->templates()->orderBy('name')->get(['id', 'name', 'headings', 'contents']),
            'segments' => $project->segments()->orderBy('name')->pluck('name'),
            'languages' => self::LANGUAGES,
            'platforms' => $this->platforms($project),
        ]);
    }

    public function store(Request $request, Project $project, CreateMessage $create): RedirectResponse
    {
        $this->authorize('send', $project);

        // A/B test: two versions to a sample, the winner to the rest later.
        if ($request->boolean('ab_enabled') && ! $request->boolean('save_draft')) {
            try {
                $test = app(RunAbTest::class)->start(
                    $project,
                    ComposerInput::fromRequest($request),
                    $this->variantB($request),
                    (int) $request->input('ab_sample'),
                    $request->user(),
                );
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['alert' => $this->explain($e)]);
            }

            return redirect()->route('projects.ab-tests.show', [$project, $test])->with('status', __('A/B test started: versions A and B are on their way.'));
        }

        $draft = $request->boolean('save_draft');
        // The draft being edited is replaced by what is sent or saved now.
        $previous = Str::isUuid((string) $request->input('draft_id'))
            ? $project->messages()->where('status', 'draft')->find($request->input('draft_id'))
            : null;

        try {
            $message = $create->handle($project, ComposerInput::fromRequest($request), $request->user(), draft: $draft);
            $previous?->delete();

            // Sent from a template: kept for its statistics (the content is the form's).
            if ($template = Str::isUuid((string) $request->input('template_id')) ? $project->templates()->find($request->input('template_id')) : null) {
                $message->forceFill(['template_id' => $template->id])->save();
            }
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['alert' => $this->explain($e)]);
        }

        if ($draft) {
            return redirect()->route('projects.messages.index', [$project, 'status' => 'draft'])->with('status', __('Draft saved.'));
        }

        return redirect()->route('projects.messages.show', [$project, $message])
            ->with('status', $message->status === 'scheduled'
                ? __('Message scheduled for :date.', ['date' => $message->send_after->setTimezone($request->input('timezone') ?: config('app.timezone'))->isoFormat('LLL')])
                : __('Message on its way to :count devices.', ['count' => number_format($message->recipients, 0, ',', ' ')]));
    }

    /** Sends the message being written to the test devices only. */
    public function test(Request $request, Project $project, CreateMessage $create): JsonResponse
    {
        $this->authorize('send', $project);

        $devices = $project->subscriptions()->where('is_test', true)->where('enabled', true)->whereNull('invalid_at')->pluck('id')->all();
        if ($devices === []) {
            return response()->json(['error' => __('No test device yet: open a person of your audience and mark their device as a test device.')], 422);
        }

        $input = ComposerInput::fromRequest($request);
        foreach (['included_segments', 'excluded_segments', 'include_aliases', 'include_subscription_ids', 'isIos', 'isAndroid', 'isAnyWeb', 'send_after', 'delayed_option', 'delivery_time_of_day'] as $key) {
            unset($input[$key]);
        }
        $input['include_subscription_ids'] = $devices;
        $input['name'] = trim(__('Test').' · '.($input['name'] ?? ''), ' ·');

        try {
            $message = $create->handle($project, $input, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['error' => $this->explain($e)], 422);
        }
        $message->forceFill(['is_test' => true])->save();

        return response()->json([
            'recipients' => $message->recipients,
            'message' => trans_choice('Test sent to :count device.|Test sent to :count devices.', $message->recipients, ['count' => $message->recipients]),
            'url' => route('projects.messages.show', [$project, $message]),
        ]);
    }

    /** Recipients the current composer settings would reach. */
    public function estimate(Request $request, Project $project, Audience $audience): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            $input = ComposerInput::fromRequest($request);
            $targeting = app(CreateMessage::class)->targeting($input);
            $query = $audience->query($project, $targeting);
            $byType = (clone $query)->select('subscriptions.type', DB::raw('count(*) as total'))->groupBy('subscriptions.type')->pluck('total', 'type');
        } catch (ValidationException $e) {
            return response()->json(['total' => 0, 'error' => $this->explain($e)]);
        }

        return response()->json(['total' => (int) $byType->sum(), 'platforms' => $byType]);
    }

    public function show(Project $project, Message $message): View
    {
        $this->authorize('view', $project);
        abort_unless($message->project_id === $project->id, 404);

        $message->load(['platformStats', 'author']);

        return view('messages.show', [
            'project' => $project,
            'message' => $message,
            'clicks' => $this->clicksPerHour($message),
            // Devices of the old app (OneSignal SDK): neither receptions nor clicks come back.
            'legacy' => $message->deliveries()->whereHas('subscription', fn ($q) => $q->where('sdk', Subscription::ONESIGNAL_SDK))->count(),
            'errors_summary' => $message->deliveries()
                ->whereNotNull('error')
                ->select('error', DB::raw('count(*) as total'))
                ->groupBy('error')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
        ]);
    }

    /** Every delivery of a message as CSV: who received it, who clicked. */
    public function export(Project $project, Message $message): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($message->project_id === $project->id, 404);

        $name = 'carillon-'.Str::slug(Str::limit($message->label(), 40, '')).'-'.($message->started_at ?? $message->created_at)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($message) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['subscription_id', 'external_id', 'platform', 'device', 'app_version', 'status', 'error', 'sent_at', 'received_at', 'clicked_at'], escape: '\\');
            $message->deliveries()
                ->with('subscription:id,subscriber_id,type,device_model,app_version', 'subscription.subscriber:id,external_id')
                ->orderBy('id')
                ->chunk(1000, function ($deliveries) use ($out) {
                    foreach ($deliveries as $delivery) {
                        fputcsv($out, [
                            $delivery->subscription_id,
                            $delivery->subscription?->subscriber?->external_id,
                            $delivery->subscription?->type,
                            $delivery->subscription?->device_model,
                            $delivery->subscription?->app_version,
                            $delivery->status,
                            $delivery->error,
                            $delivery->sent_at?->toIso8601String(),
                            $delivery->received_at?->toIso8601String(),
                            $delivery->clicked_at?->toIso8601String(),
                        ], escape: '\\');
                    }
                });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Clicks per hour during the first two days after sending (from the
     * per-device details, kept for the retention period).
     *
     * @return list<array{date: CarbonImmutable, value: int}>
     */
    protected function clicksPerHour(Message $message): array
    {
        $start = $message->started_at ? CarbonImmutable::parse($message->started_at)->startOfHour() : null;
        if (! $start || $message->clicked === 0) {
            return [];
        }

        $hours = (int) min(48, max(1, $start->diffInHours(now()->min($start->addHours(48))) + 1));
        $counts = array_fill(0, $hours, 0);
        $message->deliveries()->whereNotNull('clicked_at')->pluck('clicked_at')->each(function ($at) use ($start, $hours, &$counts) {
            $hour = (int) floor($start->diffInMinutes(CarbonImmutable::parse($at)) / 60);
            if ($hour >= 0 && $hour < $hours) {
                $counts[$hour]++;
            }
        });

        return array_map(fn (int $hour) => ['date' => $start->addHours($hour), 'value' => $counts[$hour]], array_keys($counts));
    }

    public function destroyDraft(Project $project, Message $message): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($message->project_id === $project->id && $message->status === 'draft', 404);
        $message->delete();

        return redirect()->route('projects.messages.index', [$project, 'status' => 'draft'])->with('status', __('Draft deleted.'));
    }

    public function cancel(Project $project, Message $message): RedirectResponse
    {
        $this->authorize('send', $project);
        abort_unless($message->project_id === $project->id, 404);

        if (! $message->isCancelable()) {
            return back()->withErrors(['alert' => __('This message has already been sent.')]);
        }

        $message->forceFill(['status' => 'canceled', 'canceled_at' => now()])->save();

        return back()->with('status', __('Message canceled.'));
    }

    /** Readable message for the API errors shown in the composer. */
    /** Texts of version B, in the language of the first text of the composer. */
    protected function variantB(Request $request): array
    {
        $language = strtolower((string) ($request->input('texts.0.language') ?: 'en'));
        $title = trim((string) $request->input('ab_title'));
        $body = trim((string) $request->input('ab_body'));
        if ($body === '') {
            throw ValidationException::withMessages(['ab_body' => __('Write the text of version B.')]);
        }

        return ['headings' => $title !== '' ? [$language => $title] : null, 'contents' => [$language => $body]];
    }

    protected function explain(ValidationException $e): string
    {
        $message = collect($e->errors())->flatten()->first() ?? __('Invalid message.');

        return match ($message) {
            'All included players are not subscribed' => __('Nobody matches this audience: no subscribed device.'),
            'You must include which players, segments, or tags you wish to send this notification to.' => __('Choose who receives the message.'),
            'Message Notifications must have English language content' => __('Write the text of the message.'),
            default => $message,
        };
    }

    /** @return array<string, array{label: string, icon: string, ready: bool}> */
    protected function platforms(Project $project): array
    {
        return [
            'ios' => ['label' => 'iOS', 'icon' => 'apple', 'ready' => (bool) $project->credential('apns')?->isUsable()],
            'android' => ['label' => 'Android', 'icon' => 'android', 'ready' => (bool) $project->credential('fcm')?->isUsable()],
            'web' => ['label' => 'Web', 'icon' => 'globe-web', 'ready' => (bool) $project->credential('webpush')?->isUsable()],
        ];
    }
}
