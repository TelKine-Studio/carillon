@php $n = fn ($v) => number_format((int) $v, 0, ',', ' '); @endphp
<x-layouts.app :title="__('Audience').' · '.$project->name">
    <x-ui.page-header :title="__('Audience')" :description="__('The devices registered by your apps and the people they belong to.')">
        @can('configure', $project)
            <x-ui.button variant="secondary" :href="route('projects.imports.create', $project)" icon="upload">{{ __('Import from OneSignal') }}</x-ui.button>
        @endcan
        @can('send', $project)
            <x-ui.button variant="secondary" :href="route('projects.segments.index', $project)" icon="layers">{{ __('Segments') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Subscribed devices')" :value="$n($totals['subscribed'])" icon="smartphone" />
        <x-ui.stat :label="__('People')" :value="$n($totals['people'])" icon="users" />
        <x-ui.stat :label="__('Identified')" :value="$n($totals['identified'])" icon="key" :hint="__('With an external id')" />
        <x-ui.stat :label="__('Active this week')" :value="$n($totals['active'])" icon="clock" />
    </div>

    <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" x-data>
        <div class="relative flex-1">
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('External id, tag value, device id…') }}" class="form-control pl-9">
        </div>
        <div class="grid grid-cols-2 gap-3 sm:flex">
            <x-ui.select name="platform" size="sm" class="sm:w-40" :value="$platform" :options="['' => __('All platforms'), 'ios' => 'iOS', 'android' => 'Android', 'web' => 'Web', 'email' => __('Email')]" onchange="this.form.submit()" />
            <x-ui.select name="state" size="sm" class="sm:w-44" :value="$state" :options="['' => __('All states'), 'subscribed' => __('Subscribed'), 'unsubscribed' => __('Unsubscribed'), 'invalid' => __('Unreachable')]" onchange="this.form.submit()" />
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] gap-4 border-b border-zinc-100 px-5 py-2.5 text-xs font-medium text-zinc-500 lg:grid dark:border-zinc-800">
            <span>{{ __('Device') }}</span><span>{{ __('Person') }}</span><span>{{ __('Tags') }}</span><span>{{ __('Last seen') }}</span><span>{{ __('State') }}</span>
        </div>
        @forelse ($subscriptions as $subscription)
            @php $person = $subscription->subscriber; @endphp
            <a href="{{ route('projects.subscribers.show', [$project, $person]) }}" class="grid gap-3 border-t border-zinc-100 px-4 py-3.5 transition first:border-0 hover:bg-zinc-50/80 sm:px-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] lg:items-center lg:gap-4 dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="flex min-w-0 items-center gap-3">
                    <x-audience.device :subscription="$subscription" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $subscription->type === 'email' ? $subscription->token : ($subscription->device_model ?: ucfirst($subscription->type)) }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ collect([$subscription->device_os, $subscription->app_version ? 'v'.$subscription->app_version : null])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                    <span class="ml-auto lg:hidden"><x-audience.state :subscription="$subscription" /></span>
                </div>
                <p class="min-w-0 truncate text-sm">
                    @if ($person->external_id)
                        <span class="font-mono text-xs text-zinc-800 dark:text-zinc-200">{{ $person->external_id }}</span>
                    @else
                        <span class="text-zinc-400">{{ __('Anonymous') }}</span>
                    @endif
                </p>
                <div class="flex min-w-0 flex-wrap gap-1">
                    @foreach (array_slice($person->tags ?? [], 0, 3, true) as $key => $value)
                        <span class="inline-flex max-w-full truncate rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ $key }}={{ $value }}</span>
                    @endforeach
                    @if (count($person->tags ?? []) > 3)<span class="text-[11px] text-zinc-400">+{{ count($person->tags) - 3 }}</span>@endif
                </div>
                <p class="text-xs text-zinc-500">{{ $subscription->last_active_at?->diffForHumans() ?? '—' }}</p>
                <span class="hidden lg:block"><x-audience.state :subscription="$subscription" /></span>
            </a>
        @empty
            <x-ui.empty icon="users" :title="$search || $platform || $state ? __('Nobody matches') : __('No device yet')"
                :description="$search || $platform || $state ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this project’s App ID.')" />
        @endforelse
        @if ($subscriptions->hasPages())
            <div class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800">{{ $subscriptions->links() }}</div>
        @endif
    </div>
</x-layouts.app>
