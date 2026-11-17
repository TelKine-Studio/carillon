@php
    $lang = app()->getLocale();
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn ($part, $total) => $total > 0 ? number_format($part / $total * 100, 1, ',', ' ').' %' : '—';
    $live = in_array($message->status, ['queued', 'sending'], true);
    $platformNames = ['ios' => ['iOS', 'apple'], 'android' => ['Android', 'android'], 'huawei' => ['Huawei', 'android'], 'amazon' => ['Amazon', 'android'], 'web' => ['Web', 'globe-web'], 'email' => [__('Email'), 'mail'], 'sms' => ['SMS', 'message'], 'whatsapp' => ['WhatsApp', 'chat']];
    $isEmail = $message->channel === 'email';
    $preview = [
        'title' => \App\Models\Message::localized($message->headings, $lang),
        'subtitle' => \App\Models\Message::localized($message->subtitle, $lang),
        'body' => \App\Models\Message::localized($message->contents, $lang),
        'image' => $message->option('big_picture'),
        'icon' => $message->option('large_icon') ?? $message->option('chrome_web_icon'),
        'buttons' => collect($message->option('buttons', []))->pluck('text')->all(),
    ];
@endphp
<x-layouts.app :title="$message->label()">
    @if ($live)
        {{-- Refreshes while the message is being sent. --}}
        <meta http-equiv="refresh" content="4">
    @endif

    <x-ui.page-header :title="$message->label()">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.messages.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Messages') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($message->id, 8, '') }}</span>
        </x-slot:breadcrumb>
        @can('send', $project)
            @if ($message->status === 'draft')
                <form method="POST" action="{{ route('projects.messages.destroy-draft', [$project, $message]) }}" data-confirm-title="{{ __('Delete this draft?') }}" data-confirm="{{ __('It cannot be recovered.') }}" data-confirm-action="{{ __('Delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="ghost-danger" icon="trash">{{ __('Delete') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('projects.messages.create', [$project, 'from' => $message->id])" icon="pencil">{{ __('Edit the draft') }}</x-ui.button>
            @endif
            @if ($message->isCancelable())
                <form method="POST" action="{{ route('projects.messages.cancel', [$project, $message]) }}" data-confirm-title="{{ __('Cancel this message?') }}" data-confirm="{{ __('It will not be sent.') }}" data-confirm-action="{{ __('Cancel the message') }}">
                    @csrf
                    <x-ui.button variant="secondary" icon="x">{{ __('Cancel') }}</x-ui.button>
                </form>
            @endif
            @if ($message->status !== 'draft')
                <x-ui.button variant="secondary" :href="route('projects.messages.create', [$project, 'from' => $message->id])" icon="copy">{{ __('Duplicate') }}</x-ui.button>
            @endif
        @endcan
        @if ($message->ab_test_id)
            <x-ui.button variant="secondary" :href="route('projects.ab-tests.show', [$project, $message->ab_test_id])" icon="activity">{{ __('A/B test') }}</x-ui.button>
        @endif
    </x-ui.page-header>

    @if ($message->labels || auth()->user()->can('send', $project))
        <div class="-mt-2 mb-4 flex flex-wrap items-center gap-1.5">
            @foreach ($message->labels ?? [] as $item)
                <a href="{{ route('projects.messages.index', [$project, 'label' => $item]) }}"><x-ui.badge>{{ $item }}</x-ui.badge></a>
            @endforeach
            @can('send', $project)
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'labels')" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white">
                    <x-ui.icon name="tag" class="size-3.5" />{{ $message->labels ? __('Edit labels') : __('Add labels') }}
                </button>
                <x-ui.modal name="labels" :title="__('Labels')" :description="__('To sort and filter the messages. Separated by commas.')">
                    <form method="POST" action="{{ route('projects.messages.labels', [$project, $message]) }}" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <x-ui.input name="labels" :label="__('Labels')" :value="implode(', ', $message->labels ?? [])" :placeholder="__('e.g. promo, newsletter')" maxlength="300" />
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'labels')">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button icon="check">{{ __('Save') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endcan
        </div>
    @endif

    @php
        $sent = max(0, $message->recipients - $message->remaining());
        $audience = $message->recipients + (int) $message->capped;
        $reached = $isEmail ? $message->received : $message->clicked;
        $value = (float) $conversions->flatten()->sum('amount');
        $platformColors = ['ios' => 'bg-zinc-800 dark:bg-zinc-200', 'android' => 'bg-emerald-500', 'huawei' => 'bg-red-500', 'amazon' => 'bg-amber-500', 'web' => 'bg-sky-500', 'email' => 'bg-violet-500', 'sms' => 'bg-brand-500', 'whatsapp' => 'bg-green-600'];
        $byPlatform = $message->platformStats->sortByDesc('successful')->values();
        $deliveredTotal = max(1, $byPlatform->sum('successful'));
        $duration = $message->started_at && $message->completed_at ? $message->started_at->diffInSeconds($message->completed_at) : null;
        $t = $message->targeting ?? [];
        $schedule = match (true) {
            $message->option('delayed_option') === 'timezone' => __('At :time in the time zone of each device', ['time' => $message->option('delivery_time_of_day')]),
            $message->option('delayed_option') === 'last-active' => __('At the smart time of each person'),
            (bool) $message->send_after => __('Scheduled for :date', ['date' => $message->send_after->isoFormat('LLL')]),
            default => __('Immediately'),
        };
        $settings = array_filter([
            __('Name') => $message->name,
            __('Channel') => ['push' => __('Push notification'), 'email' => __('Email'), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$message->channel ?? 'push'] ?? $message->channel,
            __('Audience') => $message->audienceLabel(),
            __('Recipients') => $n($message->recipients),
            __('Delivery') => $schedule,
            __('Priority') => $message->option('priority') ? ((int) $message->option('priority') >= 10 ? __('High') : __('Normal')) : null,
            __('Time to live (seconds)') => $message->option('ttl'),
            __('Link') => $message->option('url'),
            __('Image') => $message->option('big_picture'),
            __('Collapse id') => $message->option('collapse_id'),
            __('Created by') => $message->author?->name ?? ($apiKey ? __('API key :name', ['name' => $apiKey]) : null),
        ], fn ($v) => $v !== null && $v !== '');
        $tabs = [
            'delivered' => __('Delivered'),
            'received' => $isEmail ? __('Opened') : __('Confirmed reception'),
            'not_received' => $isEmail ? __('Not opened') : __('Reception not confirmed'),
            'clicked' => __('Clicked'),
            'not_clicked' => __('Did not click'),
            'refused' => __('Refused'),
            'failed' => __('Failures'),
        ];
    @endphp

    {{-- Status line, as OneSignal: when it left, how long it took, who sent it. --}}
    <div class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
        <x-messages.status :status="$message->status" />
        <span>
            @if ($message->status === 'scheduled')
                {{ __('Scheduled for :date', ['date' => $message->send_after->isoFormat('LLL')]) }}
            @elseif ($message->status === 'sending' && $message->option('delayed_option') === 'timezone')
                {{ __('Delivered at :time in the time zone of each device', ['time' => $message->option('delivery_time_of_day')]) }}
            @elseif ($message->started_at)
                {{ __('Sending started :date', ['date' => $message->started_at->isoFormat('LLL')]) }}@if ($duration !== null), {{ __('completed in :duration', ['duration' => $duration < 60 ? trans_choice(':count second|:count seconds', max(1, (int) $duration)) : \Carbon\CarbonInterval::seconds((int) $duration)->cascade()->forHumans(['short' => true])]) }}@endif
            @else
                {{ __('Created :date', ['date' => $message->created_at->isoFormat('LLL')]) }}
            @endif
        </span>
        <span>·</span>
        <span>{{ $message->author ? __('Created in the dashboard by :name', ['name' => $message->author->name]) : ($message->api_key_id ? __('Sent with the API') : '—') }}</span>
        @if ($message->template)
            <span>·</span>
            <a href="{{ route('projects.templates.edit', [$project, $message->template]) }}" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white"><x-ui.icon name="file" class="size-3.5" />{{ $message->template->name }}</a>
        @endif
    </div>

    @if ($message->error)
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $message->error }}
        </div>
    @endif

    {{-- Three cards: delivery, engagement, platforms. --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Delivered') }}</h2>
            <p data-count class="mt-3 text-4xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">{{ $n($message->successful) }}</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __(':rate of the messages sent were delivered', ['rate' => $pct($message->successful, $sent)]) }}</p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $sent > 0 ? round($message->successful / $sent * 100, 2) : 0 }}%"></div>
            </div>
            <dl class="mt-5 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ([
                    [__('Audience'), $audience, null, true],
                    [__('Capped'), (int) $message->capped, 'bg-amber-500', false],
                    [__('Remaining'), $message->remaining(), 'bg-yellow-400', false],
                    [__('Sent messages'), $sent, null, true],
                    [__('Refused'), $message->failed, 'bg-red-500', false],
                    [__('Failures'), $message->errored, 'bg-red-800', false],
                    [__('Delivered'), $message->successful, 'bg-emerald-500', false],
                ] as [$label, $count, $dot, $strong])
                    <div @class(['flex items-center justify-between gap-3 py-2', 'pl-4' => $dot])>
                        <dt @class(['flex items-center gap-2', 'font-medium text-zinc-900 dark:text-zinc-100' => $strong, 'text-zinc-600 dark:text-zinc-400' => ! $strong])>
                            @if ($dot)<span class="size-2 rounded-full {{ $dot }}"></span>@endif{{ $label }}
                        </dt>
                        <dd class="tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($count) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="card p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $isEmail ? __('Open rate') : __('Click-through rate') }}</h2>
            <p class="mt-3 flex items-baseline gap-2">
                <span class="text-4xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">{{ $pct($reached, $message->successful) }}</span>
                <span class="text-sm tabular-nums text-zinc-500">({{ $n($reached) }})</span>
            </p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('of the messages delivered') }}</p>
            <dl class="mt-5 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach (array_filter([
                    [__('Total clicks'), $n($message->clicked), null],
                    $isEmail ? null : [__('Confirmed receptions'), $n($message->received), $pct($message->received, $message->successful)],
                    [__('People who converted'), $n($converted), $pct($converted, $message->successful)],
                    $value > 0 ? [__('Value of the conversions'), number_format($value, 0, ',', ' '), null] : null,
                ]) as [$label, $count, $rate])
                    <div class="flex items-center justify-between gap-3 py-2">
                        <dt class="text-zinc-600 dark:text-zinc-400">{{ $label }}</dt>
                        <dd class="tabular-nums text-zinc-900 dark:text-zinc-100">{{ $count }}@if ($rate)<span class="ml-1.5 text-xs text-zinc-400">{{ $rate }}</span>@endif</dd>
                    </div>
                @endforeach
            </dl>
            @if ($legacy)
                <p class="mt-4 flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-xs leading-relaxed text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                    <x-ui.icon name="info" class="mt-px size-3.5 shrink-0" />
                    <span>{{ trans_choice(':count device still runs the OneSignal SDK: its receptions and clicks are not reported until the app is updated.|:count devices still run the OneSignal SDK: their receptions and clicks are not reported until the app is updated.', $legacy, ['count' => $n($legacy)]) }}</span>
                </p>
            @endif
        </section>

        <section class="card p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Deliveries by platform') }}</h2>
            @if ($byPlatform->isEmpty())
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing delivered yet.') }}</p>
            @else
                <div class="mt-4 flex h-3 gap-0.5 overflow-hidden rounded-full">
                    @foreach ($byPlatform as $stat)
                        @continue($stat->successful === 0)
                        <span class="h-full {{ $platformColors[$stat->platform] ?? 'bg-zinc-400' }}" style="width: {{ round($stat->successful / $deliveredTotal * 100, 2) }}%"></span>
                    @endforeach
                </div>
                <dl class="mt-5 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    @foreach ($byPlatform as $stat)
                        <div class="flex items-center justify-between gap-3 py-2">
                            <dt class="flex items-center gap-2 text-zinc-700 dark:text-zinc-300"><span class="size-2 rounded-full {{ $platformColors[$stat->platform] ?? 'bg-zinc-400' }}"></span>{{ $platformNames[$stat->platform][0] ?? $stat->platform }}</dt>
                            <dd class="text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($stat->successful) }}<span class="ml-1.5 text-xs text-zinc-400">{{ __(':count clicks', ['count' => $n($stat->clicked)]) }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>

    {{-- Performance: statistics over time, conversions. --}}
    <section class="card mt-6" x-data="{ tab: 'stats' }">
        <header class="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Performance') }}</h2>
            <nav class="inline-flex gap-0.5 self-start rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                @foreach (['stats' => __('Message statistics'), 'conversions' => __('Conversions')] as $key => $label)
                    <button type="button" x-on:click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-sm shadow-zinc-950/10 dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'" class="rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition">{{ $label }}</button>
                @endforeach
            </nav>
        </header>
        <div class="px-5 pt-4 pb-5 sm:px-6">
            <div x-show="tab === 'stats'">
                @if ($clicks)
                    <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clicks per hour, during the first two days.') }}</p>
                    <x-ui.bar-chart :points="$clicks" :label="__('Clicks over time')" tick-format="ddd HH[h]" tooltip="ddd D MMM, HH[h]" height="h-40" />
                @else
                    <p class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('The clicks will show here, hour by hour.') }}</p>
                @endif
            </div>
            <div x-show="tab === 'conversions'" x-cloak>
                @if ($conversions->isNotEmpty())
                    <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Events of the people in the :hours hours after this message: direct after a click, influenced after a reception.', ['hours' => \App\Audience\Conversions::WINDOW]) }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[420px] text-sm">
                            <thead class="text-xs text-zinc-500">
                                <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                    <th class="py-2.5 pr-3 text-left font-medium">{{ __('Event') }}</th>
                                    <th class="px-3 py-2.5 text-right font-medium">{{ __('Direct') }}</th>
                                    <th class="px-3 py-2.5 text-right font-medium">{{ __('Influenced') }}</th>
                                    <th class="py-2.5 pl-3 text-right font-medium">{{ __('Value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($conversions as $name => $rows)
                                    <tr>
                                        <td class="py-3 pr-3 font-mono text-xs text-zinc-900 dark:text-zinc-100">{{ $name }}</td>
                                        <td class="px-3 py-3 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($rows->firstWhere('attribution', 'direct')?->total ?? 0) }}</td>
                                        <td class="px-3 py-3 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($rows->firstWhere('attribution', 'influenced')?->total ?? 0) }}</td>
                                        <td class="py-3 pl-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $rows->sum('amount') ? number_format((float) $rows->sum('amount'), 0, ',', ' ') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No conversion yet: send the events of your app (purchases…) to see what this message brought.') }}</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Audience activity: the devices of each outcome. --}}
    @if ($message->status !== 'draft')
        <section class="card mt-6" x-data="messageActivity(@js(route('projects.messages.activity', [$project, $message])))">
            <header class="flex items-center justify-between gap-3 px-5 pt-5 sm:px-6">
                <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Audience activity') }}</h2>
                @if ($message->recipients > 0)
                    <x-ui.button size="sm" variant="secondary" :href="route('projects.messages.export', [$project, $message])" icon="file">{{ __('Export') }}</x-ui.button>
                @endif
            </header>
            <nav class="scrollbar-none mt-4 flex gap-1 overflow-x-auto border-b border-zinc-100 px-5 sm:px-6 dark:border-zinc-800">
                @foreach ($tabs as $key => $label)
                    <button type="button" x-on:click="open('{{ $key }}')" :class="tab === '{{ $key }}' ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'" class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-2 py-2.5 text-sm font-medium whitespace-nowrap transition">
                        {{ $label }}<span class="rounded bg-zinc-100 px-1 text-xs tabular-nums text-zinc-500 dark:bg-white/10 dark:text-zinc-400">{{ $n($activity[$key]) }}</span>
                    </button>
                @endforeach
            </nav>
            <div class="relative min-h-32 transition-opacity" :class="loading && 'opacity-50'" x-html="html" x-on:click="page($event)"></div>
        </section>
    @endif

    @if ($errors_summary->isNotEmpty())
        <x-ui.card class="mt-6" :title="__('Reasons of the failures')" :description="$isEmail ? __('Reasons given by the email service or the receiving servers.') : ($message->channel === 'sms' ? __('Reasons given by the SMS provider or the carriers.') : __('Reasons given by Apple, Google or the browsers.'))" :padding="false">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($errors_summary as $row)
                    <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm sm:px-6">
                        <span class="min-w-0 truncate font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $row->error }}</span>
                        <span class="shrink-0 font-medium tabular-nums text-zinc-900 dark:text-white">{{ $n($row->total) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    {{-- Message settings: what was chosen, with the preview. --}}
    <section class="card mt-6">
        <header class="px-5 pt-5 sm:px-6">
            <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Message settings') }}</h2>
        </header>
        <div class="grid gap-8 px-5 pt-4 pb-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-6">
                <div>
                    <h3 class="mb-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('Details') }}</h3>
                    <dl class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        <div class="grid grid-cols-[minmax(0,160px)_minmax(0,1fr)] gap-4 py-2.5">
                            <dt class="text-zinc-500">{{ __('Message ID') }}</dt>
                            <dd class="flex min-w-0 items-center gap-2" x-data="{ copied: false }">
                                <span class="truncate font-mono text-xs text-zinc-800 dark:text-zinc-200">{{ $message->id }}</span>
                                <button type="button" x-on:click="navigator.clipboard.writeText(@js($message->id)); copied = true; setTimeout(() => copied = false, 1500)" class="shrink-0 rounded p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/5 dark:hover:text-zinc-200" title="{{ __('Copy') }}">
                                    <x-ui.icon name="copy" class="size-3.5" x-show="! copied" /><x-ui.icon name="check" class="size-3.5 text-emerald-600" x-show="copied" x-cloak />
                                </button>
                            </dd>
                        </div>
                        @foreach ($settings as $label => $value)
                            <div class="grid grid-cols-[minmax(0,160px)_minmax(0,1fr)] gap-4 py-2.5">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="min-w-0 break-words text-zinc-800 dark:text-zinc-200">{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($message->ab_test_id)
                            <div class="grid grid-cols-[minmax(0,160px)_minmax(0,1fr)] gap-4 py-2.5">
                                <dt class="text-zinc-500">{{ __('A/B test') }}</dt>
                                <dd><a href="{{ route('projects.ab-tests.show', [$project, $message->ab_test_id]) }}" class="text-brand-700 hover:underline dark:text-brand-300">{{ __('Version :letter', ['letter' => $message->ab_variant === 'W' ? __('winner') : $message->ab_variant]) }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($isEmail)
                    @php
                        $subject = $message->option('email_subject');
                        $emailDetails = array_filter([
                            __('Subject') => is_array($subject) ? \App\Models\Message::localized($subject, $lang) : $subject,
                            __('Preview text') => is_string($message->option('email_preheader')) ? $message->option('email_preheader') : null,
                            __('Sender name') => $message->option('email_from_name'),
                        ], fn ($v) => filled($v));
                    @endphp
                    <div>
                        <h3 class="mb-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('Content') }}</h3>
                        <dl class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($emailDetails as $label => $value)
                                <div class="grid grid-cols-[minmax(0,160px)_minmax(0,1fr)] gap-4 py-2.5"><dt class="text-zinc-500">{{ $label }}</dt><dd class="min-w-0 break-words text-zinc-800 dark:text-zinc-200">{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                @else
                    <div>
                        <h3 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('Content') }}</h3>
                        <div class="space-y-3">
                            @foreach ($message->contents as $language => $body)
                                <div class="rounded-lg border border-zinc-100 p-4 text-sm dark:border-zinc-800">
                                    <p class="mb-2 font-mono text-xs text-zinc-400 uppercase">{{ $language }}</p>
                                    @if ($title = $message->headings[$language] ?? null)<p class="font-semibold text-zinc-900 dark:text-white">{{ $title }}</p>@endif
                                    @if ($subtitle = $message->subtitle[$language] ?? null)<p class="font-medium text-zinc-700 dark:text-zinc-300">{{ $subtitle }}</p>@endif
                                    <p class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $body }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($message->option('data'))
                        <x-ui.code :label="__('Additional data')">{{ json_encode($message->option('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-ui.code>
                    @endif
                @endif
            </div>

            <div class="min-w-0">
                @if ($isEmail)
                    @php
                        $body = $message->option('email_body');
                        $body = is_array($body) ? \App\Models\Message::localized($body, $lang) : (string) $body;
                        $sample = \App\Email\EmailBuilder::render($body, ['email' => 'ada@example.com', 'external_id' => 'user-42', 'tags' => ['first_name' => 'Ada'], 'unsubscribe_url' => '#'], true);
                    @endphp
                    <iframe title="{{ __('Preview') }}" class="block h-[28rem] w-full rounded-xl border border-zinc-200 bg-white dark:border-zinc-800" sandbox="" srcdoc="{{ $sample }}"></iframe>
                @elseif ($message->channel === 'whatsapp')
                    <div class="rounded-2xl bg-[#efe7dd] p-4 dark:bg-[#0b141a]">
                        <p class="ml-auto max-w-[85%] rounded-xl rounded-tr-sm bg-[#d9fdd3] px-3 py-2 text-sm break-words whitespace-pre-line text-zinc-900 shadow-sm dark:bg-[#005c4b] dark:text-zinc-100">{{ \App\Models\Message::localized($message->contents, $lang) ?? $message->option('whatsapp_template') }}</p>
                    </div>
                    @if ($message->option('whatsapp_template'))
                        <p class="mt-3 text-xs text-zinc-500">{{ __('Template :name (:language)', ['name' => $message->option('whatsapp_template'), 'language' => $message->option('whatsapp_language')]) }}</p>
                    @endif
                @elseif ($message->channel === 'sms')
                    <div class="rounded-2xl bg-zinc-100 p-4 dark:bg-white/5">
                        <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm whitespace-pre-line text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-zinc-100">{{ \App\Email\EmailBuilder::render((string) \App\Models\Message::localized($message->contents, $lang), ['phone_number' => '+2250700000000', 'external_id' => 'user-42', 'tags' => ['first_name' => 'Ada']], false) }}</p>
                    </div>
                @else
                    <div x-data="{ preview: @js($preview) }">
                        <x-messages.preview :project="$project" />
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
