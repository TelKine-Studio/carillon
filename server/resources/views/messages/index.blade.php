@php
    $tabs = ['' => __('All'), 'sent' => __('Sent'), 'scheduled' => __('Scheduled'), 'sending' => __('Sending'), 'canceled' => __('Canceled')];
@endphp
<x-layouts.app :title="__('Messages').' · '.$project->name">
    <x-ui.page-header :title="__('Messages')" :description="__('Notifications sent from the dashboard and the API.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.messages.create', $project)" icon="send">{{ __('New message') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0">
            <nav class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                @foreach ($tabs as $key => $label)
                    @php
                        $count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0);
                        $current = (string) $status === $key;
                    @endphp
                    <a href="{{ route('projects.messages.index', [$project, 'status' => $key ?: null, 'q' => $search ?: null]) }}" @if ($current) aria-current="page" @endif
                        @class(['inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                            'bg-white text-zinc-900 shadow-sm shadow-zinc-950/10 dark:bg-zinc-800 dark:text-white dark:shadow-black/40' => $current,
                            'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! $current])>
                        {{ $label }}
                        <span @class(['min-w-5 rounded px-1 text-center text-xs tabular-nums',
                            'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' => $current,
                            'text-zinc-400 dark:text-zinc-500' => ! $current])>{{ number_format($count, 0, ',', ' ') }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form method="GET" class="relative sm:w-72">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search messages…') }}" class="form-control pl-9">
        </form>
    </div>

    <div class="card overflow-hidden">
        @forelse ($messages as $message)
            <a href="{{ route('projects.messages.show', [$project, $message]) }}" class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-4 transition first:border-0 hover:bg-zinc-50/80 sm:px-5 lg:flex-row lg:items-center lg:gap-6 dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $message->label() }}</p>
                        <x-messages.status :status="$message->status" class="shrink-0" />
                    </div>
                    @if ($message->name || \App\Models\Message::localized($message->headings, app()->getLocale()))
                        <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ \App\Models\Message::localized($message->contents, app()->getLocale()) }}</p>
                    @endif
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-zinc-400">
                        <span>{{ $message->audienceLabel() }}</span>
                        <span>·</span>
                        <span>{{ $message->status === 'scheduled' ? __('scheduled :date', ['date' => $message->send_after->diffForHumans()]) : $message->created_at->diffForHumans() }}</span>
                        <span>·</span>
                        <span>{{ $message->author?->name ?? ($message->api_key_id ? __('API') : '—') }}</span>
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-4 text-sm lg:w-80 lg:shrink-0">
                    <div>
                        <dt class="text-xs text-zinc-400">{{ __('Recipients') }}</dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($message->recipients, 0, ',', ' ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400">{{ __('Delivered') }}</dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($message->successful, 0, ',', ' ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400">{{ __('Clicks') }}</dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $message->rate($message->clicked) !== null ? number_format($message->rate($message->clicked), 1, ',', ' ').' %' : '—' }}</dd>
                    </div>
                </dl>
            </a>
        @empty
            <x-ui.empty icon="send" :title="$search || $status ? __('No message matches') : __('No message yet')"
                :description="$search || $status ? __('Try another search or status.') : __('Write your first notification, or send one with the API.')">
                @if (! $search && ! $status)
                    @can('send', $project)
                        <x-ui.button :href="route('projects.messages.create', $project)" icon="send">{{ __('New message') }}</x-ui.button>
                    @endcan
                @endif
            </x-ui.empty>
        @endforelse

        @if ($messages->hasPages())
            <div class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800">{{ $messages->links() }}</div>
        @endif
    </div>
</x-layouts.app>
