@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, $v < 10 ? 1 : 0, ',', ' ').' %';
    // Change against the previous period of the same length, in %.
    $trend = fn (?float $now, ?float $before) => ($now === null || ! $before) ? null : ($now - $before) / $before * 100;
    $vs = trans_choice('vs previous :count day|vs previous :count days', $days, ['count' => $days]);
    $sections = [
        'platform' => [__('Platforms'), 'smartphone'],
        'country' => [__('Countries'), 'globe'],
        'language' => [__('Languages'), 'message'],
        'app_version' => [__('App versions'), 'layers'],
    ];
@endphp
<x-layouts.app :title="__('Statistics').' · '.$project->name">
    <x-ui.page-header :title="__('Statistics')" :description="__('Sending, clicks and audience of :project.', ['project' => $project->name])" />

    @if ($legacy)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>{{ trans_choice(':count device still runs the OneSignal SDK: its clicks are not counted until the app is updated with the Carillon SDK.|:count devices still run the OneSignal SDK: their clicks are not counted until the app is updated with the Carillon SDK.', $legacy, ['count' => $n($legacy)]) }}</p>
        </div>
    @endif

    {{-- Tabs and period, as the Analytics of OneSignal. --}}
    <div x-data="{ tab: (location.hash.slice(1) || 'overview'), go(t) { this.tab = t; history.replaceState(null, '', '#' + t); window.scrollTo({ top: 0, behavior: 'smooth' }) } }">
        <nav class="scrollbar-none mb-5 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800">
            @foreach (['overview' => __('Overview'), 'conversions' => __('Conversions'), 'engagement' => __('Engagement'), 'audience' => __('Audience'), 'retention' => __('Cohort retention')] as $key => $label)
                <button type="button" x-on:click="go('{{ $key }}')" :class="tab === '{{ $key }}' ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'" class="-mb-px shrink-0 border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition">{{ $label }}</button>
            @endforeach
        </nav>

        <nav class="mb-5 inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]" aria-label="{{ __('Period') }}">
            @foreach (App\Http\Controllers\StatsController::PERIODS as $period)
                <a href="{{ route('projects.stats', [$project, 'days' => $period]) }}" x-bind:href="'{{ route('projects.stats', [$project, 'days' => $period]) }}#' + tab" @if ($days === $period) aria-current="page" @endif
                    @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                        'bg-white text-zinc-900 shadow-sm shadow-zinc-950/10 dark:bg-zinc-800 dark:text-white' => $days === $period,
                        'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $days !== $period])>
                    @if ($days === $period)<x-ui.icon name="calendar" class="size-3.5" />@endif{{ trans_choice(':count day|:count days', $period) }}
                </a>
            @endforeach
        </nav>

        {{-- Overview: four analytic cards. --}}
        <div x-show="tab === 'overview'" class="grid gap-4 lg:grid-cols-2">
            @foreach ([
                ['delivered', __('Delivered notifications'), $n($kpis['delivered']), $trend($kpis['delivered'], $kpis['previous']['delivered']), trans_choice(':count message|:count messages', $kpis['messages'], ['count' => $n($kpis['messages'])]), $charts['delivered'], __('No message sent in this period.'), 'engagement'],
                ['clicked', __('Click rate'), $pct($kpis['ctr']), $trend($kpis['ctr'], $kpis['previous']['ctr']), trans_choice(':count click|:count clicks', $kpis['clicked'], ['count' => $n($kpis['clicked'])]), $charts['clicked'], __('No click in this period.'), 'engagement'],
                ['new_devices', __('New devices'), $n($kpis['new_devices']), $trend($kpis['new_devices'], $kpis['previous']['new_devices']), __('Devices registered by the SDK or imported.'), $charts['new_devices'], __('No new device in this period.'), 'audience'],
                ['devices', __('Reachable devices'), $n($kpis['devices']), $trend($kpis['devices'], $kpis['previous']['devices']), __('Total at the end of each day.'), $charts['devices'], __('No device yet.'), 'audience'],
            ] as [$key, $title, $value, $change, $metric, $points, $empty, $more])
                <section class="card flex flex-col p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
                    <p class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">{{ $value }}</span>
                        <x-ui.trend :value="$change" :label="$vs" />
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $metric }}</p>
                    <div class="mt-4 flex-1">
                        <x-ui.bar-chart :points="$points" :label="$title" :empty="$empty" />
                    </div>
                    <div class="mt-4 flex justify-end border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <button type="button" x-on:click="go('{{ $more }}')" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200">{{ __('View details') }}<x-ui.icon name="chevron-right" class="size-4" /></button>
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Engagement: clicks and the best messages. --}}
        <div x-show="tab === 'engagement'" x-cloak class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.stat :label="__('Delivered notifications')" :value="$n($kpis['delivered'])" icon="send" :delta="$trend($kpis['delivered'], $kpis['previous']['delivered'])" :delta-label="$vs" />
                <x-ui.stat :label="__('Clicks')" :value="$n($kpis['clicked'])" icon="external" :delta="$trend($kpis['clicked'], $kpis['previous']['clicked'])" :delta-label="$vs" />
                <x-ui.stat :label="__('Click rate')" :value="$pct($kpis['ctr'])" icon="activity" :delta="$trend($kpis['ctr'], $kpis['previous']['ctr'])" :delta-label="$vs" />
            </div>
            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card :title="__('Delivered notifications')" :description="__('Per day of sending.')">
                    <x-ui.bar-chart :points="$charts['delivered']" :label="__('Delivered notifications')" :empty="__('No message sent in this period.')" />
                </x-ui.card>
                <x-ui.card :title="__('Clicks')" :description="__('Per day of sending of the message.')">
                    <x-ui.bar-chart :points="$charts['clicked']" :label="__('Clicks')" :empty="__('No click in this period.')" />
                </x-ui.card>
            </div>
    <x-ui.card :title="__('Best messages')" :description="__('Highest click rate in the period, among messages delivered to at least 10 devices.')" icon="sparkles" :padding="false">
        @if ($top->isEmpty())
            <p class="px-5 pb-5 text-sm text-zinc-500 sm:px-6">{{ __('Not enough messages in this period.') }}</p>
        @else
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($top as $message)
                    <li>
                        <a href="{{ route('projects.messages.show', [$project, $message]) }}" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-zinc-50 sm:px-6 dark:hover:bg-white/[0.03]">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white" title="{{ $message->label() }}">{{ $message->label() }}</span>
                                <span class="block text-xs text-zinc-500">{{ ($message->started_at ?? $message->created_at)->isoFormat('LL') }} · {{ trans_choice(':count delivered|:count delivered', $message->successful, ['count' => $n($message->successful)]) }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $pct($message->clicked / max(1, $message->successful) * 100) }}</span>
                                <span class="block text-xs text-zinc-500">{{ trans_choice(':count click|:count clicks', $message->clicked, ['count' => $n($message->clicked)]) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
        </div>

        {{-- Conversions: from the notification to the conversion. --}}
        <div x-show="tab === 'conversions'" x-cloak class="space-y-6">
    @if ($funnel['delivered'] > 0)
        <x-ui.card :title="__('From the notification to the conversion')" :description="__('Over the period: what the people did after receiving a message.')" icon="layers">
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ([[__('Delivered'), $funnel['delivered']], [__('Received'), $funnel['received']], [__('Clicked'), $funnel['clicked']], [__('Converted'), $funnel['converted']]] as $index => [$label, $value])
                    <div class="relative overflow-hidden rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <span class="absolute inset-x-0 bottom-0 bg-brand-500/10 dark:bg-brand-500/15" style="height: {{ max(4, round($value / $funnel['delivered'] * 100)) }}%"></span>
                        <p class="relative text-xs text-zinc-500">{{ $label }}</p>
                        <p class="relative mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $n($value) }}</p>
                        @if ($index > 0)<p class="relative text-xs tabular-nums text-zinc-500">{{ $pct($value / $funnel['delivered'] * 100) }} {{ __('of the delivered') }}</p>@endif
                    </div>
                @endforeach
            </div>
            @if ($legacy)
                <p class="mt-4 text-xs text-zinc-500">{{ __('Receptions and clicks of devices still on the OneSignal SDK are not reported.') }}</p>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card :title="__('Conversions')" :description="__('Events reported by your app or your server, and the part that follows a message.')" icon="sparkles" :padding="false">
        @if ($conversions->isEmpty())
            <div class="px-5 pb-5 text-sm text-zinc-500 sm:px-6">
                {{ __('No event in this period. Send them from your server (POST /api/v1/apps/{app_id}/custom_events) or the SDK (trackEvent).') }}
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-xs text-zinc-500">
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="px-5 py-2.5 text-left font-medium sm:px-6">{{ __('Event') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Total') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('After a message') }}</th>
                        <th class="hidden px-3 py-2.5 text-right font-medium sm:table-cell">{{ __('Value') }}</th>
                        <th class="px-5 py-2.5 text-right font-medium sm:px-6">{{ __('Value after a message') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($conversions as $row)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-zinc-900 sm:px-6 dark:text-zinc-100">{{ $row->name }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $n($row->total) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $n($row->attributed) }} <span class="text-xs text-zinc-400">{{ $pct($row->total ? $row->attributed / $row->total * 100 : null) }}</span></td>
                            <td class="hidden px-3 py-3 text-right tabular-nums sm:table-cell">{{ $row->amount ? $n($row->amount) : '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums text-zinc-900 sm:px-6 dark:text-white">{{ $row->attributed_amount ? $n($row->attributed_amount) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

        </div>

        {{-- Audience: who the devices are. --}}
        <div x-show="tab === 'audience'" x-cloak class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card :title="__('New devices')" :description="__('Devices registered by the SDK or imported.')">
                    <x-ui.bar-chart :points="$charts['new_devices']" :label="__('New devices')" :empty="__('No new device in this period.')" />
                </x-ui.card>
                <x-ui.card :title="__('Reachable devices')" :description="__('Total at the end of each day.')">
                    <x-ui.bar-chart :points="$charts['devices']" :label="__('Reachable devices')" :empty="__('No device yet.')" />
                </x-ui.card>
            </div>
    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($sections as $key => [$title, $icon])
            <x-ui.card :title="$title" :icon="$icon">
                @if ($breakdowns[$key]->isEmpty())
                    <p class="text-sm text-zinc-500">{{ __('No device yet.') }}</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($breakdowns[$key] as $row)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span @class(['min-w-0 truncate', 'text-zinc-900 dark:text-zinc-100' => empty($row['other']), 'text-zinc-500' => ! empty($row['other'])])>{{ $row['label'] }}</span>
                                    <span class="shrink-0 tabular-nums text-zinc-500"><span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $n($row['value']) }}</span> · {{ $pct($row['share']) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-zinc-100 dark:bg-white/5">
                                    <div @class(['h-full rounded-full', 'bg-brand-500 dark:bg-brand-400' => empty($row['other']), 'bg-zinc-300 dark:bg-zinc-600' => ! empty($row['other'])]) style="width: {{ max(1, $row['share']) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

        </div>

        {{-- Retention by cohort. --}}
        <div x-show="tab === 'retention'" x-cloak>
    <x-ui.card :title="__('Retention by cohort')" :description="__('People grouped by the week they were first seen, and the share of them who used the app again in the following weeks.')" icon="activity" :padding="false">
        @if (collect($retention)->sum('size') === 0)
            <p class="px-5 pb-5 text-sm text-zinc-500 sm:px-6">{{ __('No new people in the last 8 weeks yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="text-xs text-zinc-500">
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <th class="px-5 py-2.5 text-left font-medium sm:px-6">{{ __('Week of arrival') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('People') }}</th>
                            @foreach (range(0, 7) as $week)
                                <th class="px-1 py-2.5 text-center font-medium">{{ $week === 0 ? __('Week 0') : __('W:n', ['n' => $week]) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($retention as $row)
                            <tr>
                                <td class="px-5 py-1 whitespace-nowrap text-zinc-700 sm:px-6 dark:text-zinc-300">{{ $row['start']->isoFormat('D MMM') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums text-zinc-900 dark:text-white">{{ $n($row['size']) }}</td>
                                @foreach ($row['weeks'] as $week => $value)
                                    <td class="p-0.5">
                                        @if ($value === null || $row['size'] === 0)
                                            <span class="block h-9 rounded-md bg-zinc-50 dark:bg-white/[.03]"></span>
                                        @else
                                            {{-- One hue, darker = more people back; the text keeps the ink colors. --}}
                                            <span class="retention-cell flex h-9 items-center justify-center rounded-md text-xs font-medium tabular-nums {{ $value >= 70 ? 'text-white' : 'text-zinc-800 dark:text-zinc-100' }}"
                                                style="--level: {{ round(8 + min(100, $value) * 0.84) }}%"
                                                title="{{ __(':value of the people first seen the week of :date were active :weeks week(s) later', ['value' => $pct($value), 'date' => $row['start']->isoFormat('D MMM'), 'weeks' => $week]) }}">{{ $pct($value) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-5 pt-3 pb-5 text-xs text-zinc-500 sm:px-6">{{ __('Activity is counted from the app sessions (Carillon SDK), the clicks and, for devices still on OneSignal, the nightly sync.') }}</p>
        @endif
    </x-ui.card>

        </div>
    </div>
</x-layouts.app>
