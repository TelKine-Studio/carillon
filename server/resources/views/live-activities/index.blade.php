<x-layouts.app :title="__('Live Activities').' · '.$project->name">
    <x-ui.page-header :title="__('Live Activities')" :description="__('Activities shown on the lock screen of iPhones, started and updated through the API.')">
        <x-ui.button variant="secondary" :href="route('projects.messages.index', $project)" icon="arrow-left">{{ __('Messages') }}</x-ui.button>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('On screen now')" :value="number_format($open, 0, ',', ' ')" icon="activity" :hint="__('Started and not ended yet.')" />
        <x-ui.stat :label="__('Started in 24 h')" :value="number_format($today, 0, ',', ' ')" icon="zap" />
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Devices able to start') }}</p>
                <span class="inline-flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400"><x-ui.icon name="apple" class="size-4" /></span>
            </div>
            @forelse ($types as $type => $devices)
                <p class="mt-3 flex items-baseline justify-between gap-3 text-sm"><span class="truncate font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $type }}</span><span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($devices, 0, ',', ' ') }}</span></p>
            @empty
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">0</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('iPhones on iOS 17.2 or later whose app sent a push-to-start token.') }}</p>
            @endforelse
        </div>
    </div>

    <form method="GET" class="relative mb-4 sm:w-72">
        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
        <input type="search" name="q" autocomplete="off" value="{{ $search }}" placeholder="{{ __('Search an activity_id…') }}" class="form-control pl-9">
    </form>

    <div class="card overflow-hidden">
        @forelse ($activities as $activity)
            <div class="flex flex-col gap-2 border-t border-zinc-100 px-4 py-4 first:border-0 sm:px-5 lg:flex-row lg:items-center lg:gap-6 dark:border-zinc-800">
                <div class="min-w-0 flex-1">
                    <p class="flex min-w-0 items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                        <span class="truncate font-mono">{{ $activity->activity_id }}</span>
                        <x-ui.badge :color="$colors[$activity->status] ?? 'zinc'" dot class="shrink-0">{{ $labels[$activity->status] ?? $activity->status }}</x-ui.badge>
                    </p>
                    <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="font-mono">{{ $activity->activity_type }}</span>
                        · {{ $activity->subscription?->device_model ?? 'iOS' }}
                        @if ($externalId = $activity->subscription?->subscriber?->external_id) · {{ $externalId }} @endif
                        @if ($activity->error) · <span class="text-red-600 dark:text-red-400">{{ $activity->error }}</span> @endif
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-4 text-xs lg:w-96">
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Started') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->started_at?->isoFormat('D MMM HH:mm:ss') ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Updates') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->updates }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Ended') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->ended_at?->isoFormat('D MMM HH:mm:ss') ?? '—' }}</dd></div>
                </dl>
            </div>
        @empty
            <x-ui.empty icon="activity" :title="$search !== '' ? __('No activity matches') : __('No Live Activity yet')"
                :description="__('Start one from your server with POST /api/v1/apps/{app_id}/activities/activity/{type}: the guide is in the documentation (Channels → Live Activities).')" />
        @endforelse
    </div>

    @if ($activities->hasPages())
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif
</x-layouts.app>
