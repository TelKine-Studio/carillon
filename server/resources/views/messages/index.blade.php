@php
    $tabs = ['' => __('All'), 'sent' => __('Sent'), 'scheduled' => __('Scheduled'), 'draft' => __('Drafts'), 'sending' => __('Sending'), 'canceled' => __('Canceled')];
@endphp
<x-layouts.app :title="__('Messages').' · '.$project->name">
    <x-ui.page-header :title="__('Messages')" :description="__('Notifications sent from the dashboard and the API.')">
        <x-ui.button variant="secondary" :href="route('projects.live-activities.index', $project)" icon="activity">{{ __('Live Activities') }}</x-ui.button>
        @can('send', $project)
            <x-ui.button :href="route('projects.messages.create', $project)" icon="send">{{ __('New message') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div x-data="liveList('message-results')" data-live-also="message-tabs message-status message-labels">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0">
            <nav id="message-tabs" class="scrollbar-none flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800">
                @foreach ($tabs as $key => $tabLabel)
                    @php
                        $count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0);
                        $current = (string) $status === $key;
                    @endphp
                    <a data-live-link href="{{ route('projects.messages.index', [$project, 'status' => $key ?: null, 'q' => $search ?: null, 'label' => $label ?: null]) }}" @if ($current) aria-current="page" @endif
                        @class(['-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition',
                            'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' => $current,
                            'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! $current])>
                        {{ $tabLabel }}
                        <span class="min-w-5 rounded bg-zinc-100 px-1 text-center text-xs tabular-nums text-zinc-500 dark:bg-white/10 dark:text-zinc-400">{{ number_format($count, 0, ',', ' ') }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form method="GET" data-live class="relative sm:w-72">
            <span id="message-status">@if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif @if ($label)<input type="hidden" name="label" value="{{ $label }}">@endif</span>
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
            <x-ui.icon name="refresh" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-zinc-400" x-show="loading" x-cloak />
            <input type="search" name="q" autocomplete="off" value="{{ $search }}" placeholder="{{ __('Search messages…') }}" class="form-control pl-9">
        </form>
    </div>

    <div id="message-labels" class="mb-4 flex flex-wrap items-center gap-1.5 empty:hidden">@if ($labels)
        <x-ui.icon name="tag" class="mr-1 size-4 text-zinc-400" />
        @foreach ($labels as $item)
            @php
                $on = mb_strtolower($item) === mb_strtolower($label);
            @endphp
            <a data-live-link href="{{ route('projects.messages.index', [$project, 'status' => $status ?: null, 'q' => $search ?: null, 'label' => $on ? null : $item]) }}"
                @class(['inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-medium transition',
                    'border-brand-500 bg-brand-50 text-brand-800 dark:border-brand-500/60 dark:bg-brand-500/10 dark:text-brand-200' => $on,
                    'border-zinc-200 text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-white' => ! $on])>
                {{ $item }}@if ($on)<x-ui.icon name="x" class="size-3" />@endif
            </a>
        @endforeach
    @endif</div>

    <div id="message-results" class="card overflow-hidden transition-opacity" :class="loading && 'is-loading'">
        @if ($messages->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium sm:px-5">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Audience') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Started sending') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Delivered') }}</th>
                            <th class="px-4 py-3 text-right font-medium sm:px-5">{{ __('Click-through rate') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($messages as $message)
                            @php
                                $href = $message->status === 'draft' && auth()->user()->can('send', $project) ? route('projects.messages.create', [$project, 'from' => $message->id]) : route('projects.messages.show', [$project, $message]);
                                $when = $message->status === 'scheduled' ? $message->send_after : ($message->started_at ?? null);
                            @endphp
                            <tr class="group relative transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="max-w-0 px-4 py-3.5 sm:px-5" style="width: 42%">
                                    <a href="{{ $href }}" class="flex min-w-0 items-center gap-2 font-medium text-zinc-900 after:absolute after:inset-0 dark:text-white">
                                        <span class="truncate">{{ $message->label() }}</span>
                                        @if ($message->is_test)<x-ui.badge color="violet" class="shrink-0">{{ __('Test') }}</x-ui.badge>@endif
                                        @if ($message->ab_variant)<x-ui.badge color="blue" class="shrink-0">{{ $message->ab_variant === 'W' ? __('A/B winner') : 'A/B · '.$message->ab_variant }}</x-ui.badge>@endif
                                    </a>
                                    <p class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        @if ($message->name || \App\Models\Message::localized($message->headings, app()->getLocale()))
                                            <span class="truncate">{{ \App\Models\Message::localized($message->contents, app()->getLocale()) }}</span>
                                        @endif
                                        @foreach ($message->labels ?? [] as $item)<x-ui.badge class="relative z-10 shrink-0">{{ $item }}</x-ui.badge>@endforeach
                                    </p>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap"><x-messages.status :status="$message->status" variant="dot" /></td>
                                <td class="max-w-44 truncate px-3 py-3.5 text-zinc-600 dark:text-zinc-400">{{ $message->audienceLabel() }}</td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-zinc-600 tabular-nums dark:text-zinc-400">
                                    {{ $when?->isoFormat('DD/MM/YY, HH:mm') ?? '—' }}
                                    <span class="block text-xs text-zinc-400">{{ $message->author?->name ?? ($message->api_key_id ? __('API') : '') }}</span>
                                </td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ number_format($message->successful, 0, ',', ' ') }}</td>
                                <td class="px-4 py-3.5 text-right font-medium text-zinc-900 tabular-nums sm:px-5 dark:text-white">{{ $message->rate($message->clicked) !== null ? number_format($message->rate($message->clicked), 2, ',', ' ').' %' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty icon="send" :title="$search || $status || $label ? __('No message matches') : __('No message yet')"
                :description="$search || $status || $label ? __('Try another search or status.') : __('Write your first notification, or send one with the API.')">
                @if (! $search && ! $status && ! $label)
                    @can('send', $project)
                        <x-ui.button :href="route('projects.messages.create', $project)" icon="send">{{ __('New message') }}</x-ui.button>
                    @endcan
                @endif
            </x-ui.empty>
        @endif

        @if ($messages->hasPages())
            <div data-live-pages class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800">{{ $messages->links() }}</div>
        @endif
    </div>
    </div>
</x-layouts.app>
