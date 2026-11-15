<?php

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Audience\Audience;
use App\Models\Message;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contents', 'like', "%{$search}%")
                ->orWhere('headings', 'like', "%{$search}%")))
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

        // "Duplicate": the composer starts from an existing message.
        $source = $request->query('from') ? $project->messages()->find($request->query('from')) : null;

        return view('messages.create', [
            'project' => $project,
            'source' => $source,
            'segments' => $project->segments()->orderBy('name')->pluck('name'),
            'languages' => self::LANGUAGES,
            'platforms' => $this->platforms($project),
        ]);
    }

    public function store(Request $request, Project $project, CreateMessage $create): RedirectResponse
    {
        $this->authorize('send', $project);

        try {
            $message = $create->handle($project, $this->input($request), $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['alert' => $this->explain($e)]);
        }

        return redirect()->route('projects.messages.show', [$project, $message])
            ->with('status', $message->status === 'scheduled'
                ? __('Message scheduled for :date.', ['date' => $message->send_after->setTimezone($request->input('timezone') ?: config('app.timezone'))->isoFormat('LLL')])
                : __('Message on its way to :count devices.', ['count' => number_format($message->recipients, 0, ',', ' ')]));
    }

    /** Recipients the current composer settings would reach. */
    public function estimate(Request $request, Project $project, Audience $audience): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            $input = $this->input($request);
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
            'errors_summary' => $message->deliveries()
                ->whereNotNull('error')
                ->select('error', DB::raw('count(*) as total'))
                ->groupBy('error')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
        ]);
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

    /**
     * Composer form → OneSignal format.
     *
     * @return array<string, mixed>
     */
    protected function input(Request $request): array
    {
        $input = ['name' => $request->input('name')];

        foreach ((array) $request->input('texts', []) as $text) {
            $language = strtolower((string) ($text['language'] ?? ''));
            if (! preg_match('/^[a-z]{2}$/', $language)) {
                continue;
            }
            foreach (['title' => 'headings', 'subtitle' => 'subtitle', 'body' => 'contents'] as $field => $key) {
                if (filled($text[$field] ?? null)) {
                    $input[$key][$language] = $text[$field];
                }
            }
        }

        match ($request->input('audience', 'segments')) {
            'users' => $input['include_aliases'] = ['external_id' => $this->list($request->input('external_ids'))],
            'devices' => $input['include_subscription_ids'] = $this->list($request->input('subscription_ids')),
            default => [
                $input['included_segments'] = array_values((array) $request->input('included_segments', [])),
                $input['excluded_segments'] = array_values((array) $request->input('excluded_segments', [])),
            ],
        };

        $platforms = (array) $request->input('platforms', []);
        if ($platforms !== [] && count($platforms) < 3) {
            $input['isIos'] = in_array('ios', $platforms, true);
            $input['isAndroid'] = in_array('android', $platforms, true);
            $input['isAnyWeb'] = in_array('web', $platforms, true);
        }

        foreach (['url', 'big_picture', 'collapse_id', 'ios_sound', 'thread_id', 'android_channel_id'] as $field) {
            if (filled($request->input($field))) {
                $input[$field] = trim((string) $request->input($field));
            }
        }
        $input['priority'] = (int) $request->input('priority', 10) === 5 ? 5 : 10;
        if (filled($request->input('ttl'))) {
            $input['ttl'] = (int) $request->input('ttl');
        }
        if (filled($request->input('ios_badge'))) {
            $input['ios_badgeType'] = 'SetTo';
            $input['ios_badgeCount'] = (int) $request->input('ios_badge');
        }

        $data = [];
        foreach ((array) $request->input('data', []) as $row) {
            if (filled($row['key'] ?? null)) {
                $data[trim($row['key'])] = (string) ($row['value'] ?? '');
            }
        }
        if ($data !== []) {
            $input['data'] = $data;
        }

        $buttons = [];
        foreach ((array) $request->input('buttons', []) as $row) {
            if (filled($row['text'] ?? null)) {
                $buttons[] = ['id' => trim($row['id'] ?? '') ?: 'button-'.(count($buttons) + 1), 'text' => trim($row['text'])];
            }
        }
        if ($buttons !== []) {
            $input['buttons'] = array_slice($buttons, 0, 3);
            $input['web_buttons'] = array_map(fn ($b) => ['id' => $b['id'], 'text' => $b['text']], array_slice($buttons, 0, 2));
        }

        // The date is typed in the browser's time zone.
        if ($request->input('schedule') === 'later' && filled($request->input('send_at'))) {
            try {
                $input['send_after'] = CarbonImmutable::parse($request->input('send_at'), $request->input('timezone') ?: config('app.timezone'))->utc()->toIso8601String();
            } catch (Throwable) {
                throw ValidationException::withMessages(['send_at' => __('Invalid date.')]);
            }
        }

        if ($request->input('schedule') === 'timezone') {
            $input['delayed_option'] = 'timezone';
            $input['delivery_time_of_day'] = (string) $request->input('delivery_time');
        }

        return $input;
    }

    /** @return list<string> */
    protected function list(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $value) ?: [])));
    }

    /** Readable message for the API errors shown in the composer. */
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
