@php $n = fn ($v) => number_format((int) $v, 0, ',', ' '); @endphp
<x-layouts.app :title="__('Audience').' · '.$project->name">
    <x-ui.page-header :title="__('Audience')" :description="__('The devices registered by your apps and the people they belong to.')">
        @can('configure', $project)
            <x-ui.button variant="secondary" :href="route('projects.imports.create', $project)" icon="upload">{{ __('Import from OneSignal') }}</x-ui.button>
        @endcan
        <x-ui.button variant="secondary" :href="route('projects.test-devices.index', $project)" icon="smartphone">{{ __('Test devices') }}</x-ui.button>
        @can('send', $project)
            <x-ui.button variant="secondary" :href="route('projects.segments.index', $project)" icon="layers">{{ __('Segments') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Subscribed devices')" :value="$n($totals['subscribed'])" icon="smartphone" />
        <x-ui.stat :label="__('People')" :value="$n($totals['people'])" icon="users" />
        <x-ui.stat :label="__('Identified')" :value="$n($totals['identified'])" icon="key" :hint="$totals['duplicates'] ? trans_choice(':count with several devices on one platform|:count with several devices on one platform', $totals['duplicates'], ['count' => $n($totals['duplicates'])]) : __('With an external id')" />
        <x-ui.stat :label="__('Active this week')" :value="$n($totals['active'])" icon="clock" />
    </div>

    @php
        // Sortable column header: a click sorts, a second click reverses.
        $sortLink = function (string $key, string $label) use ($project, $sort, $direction) {
            $active = $sort === $key;
            $url = route('projects.subscribers.index', [$project, ...array_filter(request()->only(['q', 'platform', 'state', 'view'])), 'sort' => $key, 'dir' => $active && $direction === 'desc' ? 'asc' : 'desc']);
            $arrow = $active ? ($direction === 'desc' ? '↓' : '↑') : '↕';

            return new \Illuminate\Support\HtmlString('<a data-live-link href="'.e($url).'" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white'.($active ? ' text-zinc-900 dark:text-white' : '').'">'.e($label).' <span aria-hidden="true" class="'.($active ? '' : 'opacity-40').'">'.$arrow.'</span></a>');
        };
        $views = ['people' => [__('People'), $totals['people']], 'devices' => [__('Devices'), $totals['devices']]];
    @endphp
    <div x-data="liveList('audience-results')" data-live-also="audience-tabs audience-view">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <nav id="audience-tabs" class="inline-flex shrink-0 gap-0.5 self-start rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]" aria-label="{{ __('View') }}">
            @foreach ($views as $key => [$label, $count])
                <a data-live-link href="{{ route('projects.subscribers.index', [$project, ...array_filter(request()->only(['q', 'platform', 'state'])), 'view' => $key === 'people' ? null : $key]) }}" @if ($view === $key) aria-current="page" @endif
                    @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                        'bg-white text-zinc-900 shadow-sm shadow-zinc-950/10 dark:bg-zinc-800 dark:text-white dark:shadow-black/40' => $view === $key,
                        'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $view !== $key])>
                    {{ $label }}<span class="text-xs tabular-nums text-zinc-400">{{ $n($count) }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" data-live class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            <span id="audience-view">@if ($view === 'devices')<input type="hidden" name="view" value="devices">@endif</span>
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                <x-ui.icon name="refresh" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-zinc-400" x-show="loading" x-cloak />
                <input type="search" name="q" value="{{ $search }}" autocomplete="off" placeholder="{{ __('External id, tag value, id…') }}" class="form-control pl-9">
            </div>
            <div class="grid grid-cols-2 gap-3 sm:flex">
                <x-ui.select name="platform" size="sm" class="sm:w-40" :value="$platform" :options="['' => __('All platforms'), 'ios' => 'iOS', 'android' => 'Android', 'huawei' => 'Huawei', 'amazon' => 'Amazon', 'web' => 'Web', 'email' => __('Email'), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp']" />
                <x-ui.select name="state" size="sm" class="sm:w-44" :value="$state" :options="['' => __('All states'), 'subscribed' => __('Subscribed'), 'unsubscribed' => __('Unsubscribed'), 'invalid' => __('Unreachable')]" />
            </div>
        </form>
    </div>

    <div id="audience-results" class="card transition-opacity" :class="loading && 'is-loading'">
    @if ($view === 'people')
        <div class="hidden gap-4 border-b border-zinc-100 px-5 py-2.5 text-xs font-medium text-zinc-500 lg:grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.2fr)_130px_110px_130px_36px] xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1.1fr)_130px_110px_130px_minmax(0,1fr)_36px] dark:border-zinc-800">
            <span>{{ __('Person') }}</span><span>{{ __('External id') }}</span><span>{!! $sortLink('last_active', __('Last active')) !!}</span><span>{{ __('Channels') }}</span><span>{!! $sortLink('first_active', __('First seen')) !!}</span><span class="hidden xl:block">{{ __('Location') }}</span><span></span>
        </div>
        @forelse ($results as $person)
            @php
                $profile = route('projects.subscribers.show', [$project, $person]);
            @endphp
            <div class="relative grid gap-x-4 gap-y-2 border-t border-zinc-100 px-4 py-3 transition first:border-0 last:rounded-b-2xl hover:bg-zinc-50/80 sm:px-5 lg:items-center lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.2fr)_130px_110px_130px_36px] xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1.1fr)_130px_110px_130px_minmax(0,1fr)_36px] dark:border-zinc-800 dark:hover:bg-white/[0.02]">
                <span class="flex min-w-0 items-center gap-3 pr-10 lg:pr-0">
                    <x-ui.avatar :name="$person->external_id ?: '?'" size="sm" />
                    <span class="min-w-0">
                        {{-- The link covers the whole row; the menu stays above it. --}}
                        <a href="{{ $profile }}" class="block truncate font-mono text-xs text-zinc-700 after:absolute after:inset-0 dark:text-zinc-300">{{ $person->id }}</a>
                        <span class="block truncate text-xs text-zinc-500 lg:hidden">{{ $person->external_id ?: __('Anonymous') }} · {{ $person->last_active_at?->diffForHumans() ?? '—' }}</span>
                    </span>
                </span>
                <span class="hidden min-w-0 truncate font-mono text-xs lg:block">
                    @if ($person->external_id)<span class="text-zinc-900 dark:text-zinc-100">{{ $person->external_id }}</span>@else<span class="font-sans text-zinc-400">—</span>@endif
                </span>
                <span class="hidden text-xs text-zinc-600 lg:block dark:text-zinc-400" title="{{ $person->last_active_at?->isoFormat('LLL') }}">{{ $person->last_active_at?->isoFormat('DD/MM/YY HH:mm') ?? '—' }}</span>
                <span class="flex flex-wrap items-center gap-1.5 pl-11 lg:pl-0">
                    @foreach ($person->subscriptions as $device)
                        @php
                            $reachable = $device->enabled && ! $device->invalid_at;
                            $icon = ['ios' => 'apple', 'android' => 'android', 'huawei' => 'android', 'amazon' => 'android', 'email' => 'mail', 'sms' => 'message', 'whatsapp' => 'chat'][$device->type] ?? 'globe-web';
                        @endphp
                        <span class="relative inline-flex size-7 items-center justify-center rounded-md bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"
                            title="{{ ($device->device_model ?: ucfirst($device->type)).' · '.($device->invalid_at ? __('Unreachable') : ($device->enabled ? __('Subscribed') : __('Unsubscribed'))) }}">
                            <x-ui.icon :name="$icon" class="size-3.5" />
                            <span @class(['absolute -top-0.5 -right-0.5 size-2 rounded-full ring-2 ring-white dark:ring-zinc-900', 'bg-emerald-500' => $reachable, 'bg-zinc-300 dark:bg-zinc-600' => ! $device->invalid_at && ! $reachable, 'bg-red-500' => (bool) $device->invalid_at])></span>
                        </span>
                    @endforeach
                </span>
                <span class="hidden text-xs text-zinc-600 lg:block dark:text-zinc-400" title="{{ $person->created_at->isoFormat('LLL') }}">{{ $person->created_at->isoFormat('DD/MM/YY HH:mm') }}</span>
                <span class="hidden min-w-0 truncate text-xs text-zinc-600 xl:block dark:text-zinc-400">{{ collect([$person->country, $person->language ? strtoupper($person->language) : null, $person->timezone])->filter()->implode(' · ') ?: '—' }}</span>
                <span class="absolute top-3 right-3 z-10 lg:static">
                    <x-ui.dropdown width="w-60">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="ghost" size="icon" icon="more" class="size-8"><span class="sr-only">{{ __('Actions') }}</span></x-ui.button>
                        </x-slot:trigger>
                        <x-ui.dropdown-item :href="$profile" icon="eye">{{ __('View the profile') }}</x-ui.dropdown-item>
                        @can('send', $project)
                            <form method="POST" action="{{ route('projects.test-devices.store', $project) }}">
                                @csrf
                                <input type="hidden" name="identifier" value="{{ $person->id }}">
                                <x-ui.dropdown-item icon="smartphone">{{ __('Add as test devices') }}</x-ui.dropdown-item>
                            </form>
                        @endcan
                        <div x-data="copy(@js($person->id))"><x-ui.dropdown-item type="button" icon="copy" x-on:click="copy()"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy the id'))">{{ __('Copy the id') }}</span></x-ui.dropdown-item></div>
                        <x-ui.dropdown-item :href="route('projects.subscribers.index', [$project, 'view' => 'devices', 'q' => $person->id])" icon="layers">{{ __('See their devices') }}</x-ui.dropdown-item>
                        @can('send', $project)
                            <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                            <form method="POST" action="{{ route('projects.subscribers.destroy', [$project, $person]) }}" data-confirm-title="{{ __('Delete this person?') }}" data-confirm="{{ __('Their devices stop receiving notifications until the app registers them again.') }}" data-confirm-action="{{ __('Delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.dropdown-item icon="trash" danger>{{ __('Delete the person') }}</x-ui.dropdown-item>
                            </form>
                        @endcan
                    </x-ui.dropdown>
                </span>
            </div>
        @empty
            <x-ui.empty icon="users" :title="$search || $platform || $state ? __('Nobody matches') : __('No device yet')"
                :description="$search || $platform || $state ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this App ID.')" />
        @endforelse
    @else
        <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] gap-4 border-b border-zinc-100 px-5 py-2.5 text-xs font-medium text-zinc-500 lg:grid dark:border-zinc-800">
            <span>{{ __('Device') }}</span><span>{{ __('Person') }}</span><span>{{ __('Tags') }}</span><span>{!! $sortLink('last_active', __('Last seen')) !!}</span><span>{{ __('State') }}</span>
        </div>
        @forelse ($results as $subscription)
            @php $person = $subscription->subscriber; @endphp
            <a href="{{ route('projects.subscribers.show', [$project, $person]) }}" class="grid gap-3 border-t border-zinc-100 px-4 py-3.5 transition first:border-0 hover:bg-zinc-50/80 sm:px-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] lg:items-center lg:gap-4 dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="flex min-w-0 items-center gap-3">
                    <x-audience.device :subscription="$subscription" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ in_array($subscription->type, ['email', 'sms', 'whatsapp'], true) ? $subscription->token : ($subscription->device_model ?: ucfirst($subscription->type)) }}</p>
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
    @endif
        @if ($results->hasPages())
            <div data-live-pages class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800">{{ $results->links() }}</div>
        @endif
    </div>
    </div>
</x-layouts.app>
