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
        <x-ui.stat :label="__('Identified')" :value="$n($totals['identified'])" icon="key" :hint="$totals['duplicates'] ? trans_choice(':count with several devices on one platform|:count with several devices on one platform', $totals['duplicates'], ['count' => $n($totals['duplicates'])]) : __('With an external id')" />
        <x-ui.stat :label="__('Active this week')" :value="$n($totals['active'])" icon="clock" />
    </div>

    @php
        // Sortable column header: a click sorts, a second click reverses.
        $sortLink = function (string $key, string $label) use ($project, $sort, $direction) {
            $active = $sort === $key;
            $url = route('projects.subscribers.index', [$project, ...array_filter(request()->only(['q', 'platform', 'state', 'reason', 'segment', 'view'])), 'sort' => $key, 'dir' => $active && $direction === 'desc' ? 'asc' : 'desc']);
            $arrow = $active ? ($direction === 'desc' ? '↓' : '↑') : '↕';

            return new \Illuminate\Support\HtmlString('<a data-live-link href="'.e($url).'" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-zinc-900 dark:hover:text-white'.($active ? ' text-zinc-900 dark:text-white' : '').'">'.e($label).' <span aria-hidden="true" class="'.($active ? '' : 'opacity-40').'">'.$arrow.'</span></a>');
        };
        $views = ['people' => [__('People'), $totals['people']], 'devices' => [__('Devices'), $totals['devices']], 'test' => [__('Test devices'), $totals['test']]];
    @endphp
    <div x-data="liveList('audience-results')" data-live-also="audience-tabs audience-view audience-actions">
    <nav id="audience-tabs" class="scrollbar-none mb-4 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800" aria-label="{{ __('View') }}">
        @foreach ($views as $key => [$label, $count])
            <a data-live-link href="{{ route('projects.subscribers.index', [$project, ...array_filter(request()->only(['q', 'platform', 'state', 'reason', 'segment'])), 'view' => $key === 'people' ? null : $key]) }}" @if ($view === $key) aria-current="page" @endif
                @class(['-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition',
                    'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' => $view === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $view !== $key])>
                {{ $label }}<span class="min-w-5 rounded bg-zinc-100 px-1 text-center text-xs tabular-nums text-zinc-500 dark:bg-white/10 dark:text-zinc-400">{{ $n($count) }}</span>
            </a>
        @endforeach
    </nav>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <form method="GET" data-live class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            <span id="audience-view">@if ($view !== 'people')<input type="hidden" name="view" value="{{ $view }}">@endif</span>
            <div class="relative min-w-56 flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                <x-ui.icon name="refresh" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-zinc-400" x-show="loading" x-cloak />
                <input type="search" name="q" value="{{ $search }}" autocomplete="off" placeholder="{{ __('External id, tag value, id…') }}" class="form-control pl-9">
            </div>
            <div class="grid grid-cols-2 gap-3 sm:flex">
                <x-ui.select name="platform" size="sm" class="sm:w-36" :value="$platform" :options="['' => __('All platforms'), 'ios' => 'iOS', 'android' => 'Android', 'huawei' => 'Huawei', 'amazon' => 'Amazon', 'web' => 'Web', 'email' => __('Email'), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp']" />
                <x-ui.select name="state" size="sm" class="sm:w-36" :value="$state" :options="['' => __('All states'), 'subscribed' => __('Subscribed'), 'unsubscribed' => __('Unsubscribed'), 'never' => __('Never subscribed'), 'invalid' => __('Unreachable')]" />
                <x-ui.select name="reason" size="sm" class="sm:w-40" :value="$reason" :options="['' => __('All status details'), 'rejected' => __('Refused by the provider'), 'token_reused' => __('Token taken by another registration'), 'permission_denied' => __('Turned off on the device'), 'permission_not_asked' => __('Permission never asked'), 'opted_out' => __('Unsubscribed by the app'), 'disabled_on_device' => __('Disabled on the device'), 'email_bounced' => __('Email bounced'), 'email_complaint' => __('Reported as spam'), 'email_unsubscribed' => __('Email unsubscribe link'), 'disabled_by_api' => __('Disabled through the API'), 'imported_unsubscribed' => __('Unsubscribed in OneSignal'), 'unknown' => __('Reason not recorded')]" />
                <x-ui.select name="segment" size="sm" class="sm:w-40" :value="$segment" :options="['' => __('All segments'), ...collect($segments)->mapWithKeys(fn ($name) => [$name => __($name)])->all()]" />
            </div>
        </form>
        <div id="audience-actions" class="contents">
            @if ($view === 'test')
                @can('send', $project)
                    <x-ui.button type="button" icon="plus" class="shrink-0" x-on:click="$dispatch('open-modal', 'add-test-device')">{{ __('Add a test device') }}</x-ui.button>
                @endcan
            @endif
        </div>
    </div>

    <div x-data="columnPicker('carillon.audience.columns')">
    <div id="audience-results" class="card transition-opacity" :class="loading && 'is-loading'">
    @if ($view === 'people')
        <div class="hidden gap-4 rounded-t-2xl border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-xs font-medium text-zinc-500 lg:grid dark:bg-white/[.03] lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.2fr)_130px_110px_130px_36px] xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1.1fr)_130px_110px_130px_minmax(0,1fr)_36px] dark:border-zinc-800">
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
            <x-ui.empty icon="users" :title="$search || $platform || $state || $reason ? __('Nobody matches') : __('No device yet')"
                :description="$search || $platform || $state || $reason ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this App ID.')" />
        @endforelse
    @elseif ($view === 'test')
        {{-- Same columns as the devices, with the name of the test device first (like OneSignal). --}}
        @php $testCols = 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.1fr)_150px_110px_36px]'; @endphp
        <div class="hidden gap-4 rounded-t-2xl border-b border-zinc-200 bg-zinc-50 px-5 py-3 text-xs font-medium text-zinc-500 lg:grid {{ $testCols }} dark:border-zinc-800 dark:bg-white/[.03]">
            <span>{{ __('Name') }}</span><span>{{ __('Device') }}</span><span>{{ __('Person') }}</span><span>{{ __('Tags') }}</span><span>{!! $sortLink('last_active', __('Last seen')) !!}</span><span>{{ __('State') }}</span><span></span>
        </div>
        @forelse ($results as $device)
            @php $person = $device->subscriber; $profile = route('projects.subscribers.show', [$project, $person]); @endphp
            <div class="relative grid gap-x-4 gap-y-2 border-t border-zinc-100 px-4 py-3 transition first:border-0 last:rounded-b-2xl hover:bg-zinc-50/80 sm:px-5 lg:items-center {{ $testCols }} dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="flex min-w-0 items-center gap-3 pr-10 lg:pr-0">
                    <span class="lg:hidden"><x-audience.device :subscription="$device" /></span>
                    <div class="min-w-0">
                        {{-- The link covers the whole row; the menu stays above it. --}}
                        <a href="{{ $profile }}" class="block truncate text-sm font-medium text-zinc-900 after:absolute after:inset-0 dark:text-white">{{ $device->test_name ?: ($device->device_model ?: __('Unnamed device')) }}</a>
                        <p class="truncate text-xs text-zinc-500 lg:hidden">{{ collect([$device->device_model, $device->device_os, $device->app_version ? 'v'.$device->app_version : null])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                </div>
                <div class="hidden min-w-0 items-center gap-3 lg:flex">
                    <x-audience.device :subscription="$device" />
                    <div class="min-w-0">
                        <p class="truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $device->device_model ?: ucfirst($device->type) }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ collect([$device->device_os, $device->app_version ? 'v'.$device->app_version : null])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                </div>
                <p class="min-w-0 truncate pl-12 text-sm lg:pl-0">
                    @if ($person->external_id)
                        <span class="font-mono text-xs text-zinc-800 dark:text-zinc-200">{{ $person->external_id }}</span>
                    @else
                        <span class="text-zinc-400">{{ __('Anonymous') }}</span>
                    @endif
                </p>
                <div class="hidden min-w-0 flex-wrap gap-1 lg:flex">
                    @forelse (array_slice($person->tags ?? [], 0, 3, true) as $key => $value)
                        <span class="inline-flex max-w-full truncate rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ $key }}={{ $value }}</span>
                    @empty
                        <span class="text-xs text-zinc-400">—</span>
                    @endforelse
                    @if (count($person->tags ?? []) > 3)<span class="text-[11px] text-zinc-400">+{{ count($person->tags) - 3 }}</span>@endif
                </div>
                <p class="hidden text-xs text-zinc-500 lg:block" title="{{ $device->last_active_at?->isoFormat('LLL') }}">{{ $device->last_active_at?->diffForHumans() ?? '—' }}</p>
                <span class="min-w-0 pl-12 lg:pl-0"><x-audience.state :subscription="$device" details /></span>
                <span class="absolute top-3 right-3 z-10 lg:static">
                    <x-ui.dropdown width="w-60">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="ghost" size="icon" icon="more"><span class="sr-only">{{ __('Actions') }}</span></x-ui.button>
                        </x-slot:trigger>
                        <x-ui.dropdown-item :href="$profile" icon="eye">{{ __('View the profile') }}</x-ui.dropdown-item>
                        <div x-data="copy({{ \Illuminate\Support\Js::from($device->id) }})"><x-ui.dropdown-item type="button" icon="copy" x-on:click="copy()"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy the id'))">{{ __('Copy the id') }}</span></x-ui.dropdown-item></div>
                        @can('send', $project)
                            <x-ui.dropdown-item type="button" icon="pencil"
                                x-on:click="$dispatch('rename-test-device', { url: {{ \Illuminate\Support\Js::from(route('projects.test-devices.update', [$project, $device->id])) }}, name: {{ \Illuminate\Support\Js::from($device->test_name ?? '') }} })">{{ __('Rename') }}</x-ui.dropdown-item>
                            <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                            <form method="POST" action="{{ route('projects.test-devices.destroy', [$project, $device->id]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.dropdown-item icon="x" danger>{{ __('Stop using as a test device') }}</x-ui.dropdown-item>
                            </form>
                        @endcan
                    </x-ui.dropdown>
                </span>
            </div>
        @empty
            <x-ui.empty icon="smartphone" :title="$search || $platform || $state || $reason ? __('Nobody matches') : __('No test device yet')"
                :description="$search || $platform || $state || $reason ? __('Try another search or filter.') : __('They receive the tests sent from the composer, and the segment “Test Devices”. Add your own phone with its device id or the external id of your account in the app.')" />
        @endforelse
    @else
        @php
            $columns = [
                'status' => __('State'), 'reason' => __('Status details'), 'unsubscribed' => __('Last unsubscribed'),
                'last_session' => __('Last session'), 'usage' => __('Usage duration'), 'sessions' => __('Sessions'),
                'first_session' => __('First session'), 'id' => __('Device id'), 'person' => __('Person'),
                'address' => __('Email or phone'), 'app_version' => __('App version'), 'timezone' => __('Timezone'),
                'country' => __('Country'), 'language' => __('Language'), 'tags' => __('Tags'),
            ];
            $sorts = ['unsubscribed' => 'unsubscribed', 'last_session' => 'last_active', 'usage' => 'usage', 'sessions' => 'sessions', 'first_session' => 'first_active'];
            $duration = function (int $seconds): string {
                if ($seconds < 60) {
                    return $seconds.' s';
                }
                if ($seconds < 3600) {
                    return intdiv($seconds, 60).' min';
                }

                return intdiv($seconds, 3600).' h '.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
            };
            $languageName = fn (?string $code) => $code ? (class_exists(\Locale::class) ? ucfirst(\Locale::getDisplayLanguage($code, app()->getLocale())) : strtoupper($code)) : null;
            $date = fn ($at) => $at?->isoFormat('DD/MM/YY HH:mm');
        @endphp
        <style>
            @foreach (array_keys($columns) as $key)
                [data-hidden~="{{ $key }}"] [data-col="{{ $key }}"] { display: none; }
            @endforeach
        </style>
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-2 sm:px-5 dark:border-zinc-800">
            <p class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count device|:count devices', $results->total(), ['count' => number_format($results->total(), 0, ',', ' ')]) }}</p>
            <x-ui.dropdown width="w-64">
                <x-slot:trigger>
                    <x-ui.button type="button" variant="ghost" size="sm" icon="columns">{{ __('Columns') }}</x-ui.button>
                </x-slot:trigger>
                <div class="max-h-80 overflow-y-auto p-1" x-on:click.stop>
                    @foreach ($columns as $key => $columnLabel)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">
                            <input type="checkbox" class="size-3.5 rounded border-zinc-300 text-brand-600 focus:ring-brand-500/30 dark:border-zinc-600" :checked="shown('{{ $key }}')" x-on:change="toggle('{{ $key }}')">
                            {{ $columnLabel }}
                        </label>
                    @endforeach
                </div>
            </x-ui.dropdown>
        </div>
        @if ($results->isEmpty())
            <x-ui.empty icon="users" :title="$search || $platform || $state || $reason || $segment ? __('Nobody matches') : __('No device yet')"
                :description="$search || $platform || $state || $reason || $segment ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this project’s App ID.')" />
        @else
            <div class="overflow-x-auto" :data-hidden="hidden.join(' ')">
                <table class="w-full min-w-max text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03]">
                            <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-2.5 font-medium sm:px-5 dark:bg-zinc-900">{{ __('Device') }}</th>
                            @foreach ($columns as $key => $columnLabel)
                                <th data-col="{{ $key }}" class="px-3 py-2.5 font-medium">{!! isset($sorts[$key]) ? $sortLink($sorts[$key], $columnLabel) : e($columnLabel) !!}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($results as $subscription)
                            @php
                                $person = $subscription->subscriber;
                                $profile = route('projects.subscribers.show', [$project, $person]);
                                $isAddress = in_array($subscription->type, ['email', 'sms', 'whatsapp'], true);
                            @endphp
                            <tr class="group whitespace-nowrap transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="sticky left-0 z-10 bg-white px-4 py-2.5 group-hover:bg-zinc-50 sm:px-5 dark:bg-zinc-900 dark:group-hover:bg-zinc-800/60">
                                    <a href="{{ $profile }}" class="flex max-w-60 min-w-0 items-center gap-3">
                                        <x-audience.device :subscription="$subscription" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $isAddress ? ucfirst($subscription->type) : ($subscription->device_model ?: ucfirst($subscription->type)) }}</span>
                                            <span class="block truncate text-xs text-zinc-500">{{ $isAddress ? '' : ($subscription->device_os ?: '—') }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td data-col="status" class="px-3 py-2.5"><x-audience.state :subscription="$subscription" /></td>
                                <td data-col="reason" class="max-w-64 truncate px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400" title="{{ $subscription->statusDetails() }}">{{ $subscription->statusDetails() ?? '—' }}</td>
                                <td data-col="unsubscribed" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->unsubscribed_at) ?? '—' }}</td>
                                <td data-col="last_session" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->last_active_at) ?? '—' }}</td>
                                <td data-col="usage" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $subscription->usage_seconds ? $duration($subscription->usage_seconds) : '—' }}</td>
                                <td data-col="sessions" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ number_format($subscription->sessions, 0, ',', ' ') }}</td>
                                <td data-col="first_session" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->created_at) }}</td>
                                <td data-col="id" class="px-3 py-2.5 font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $subscription->id }}</td>
                                <td data-col="person" class="max-w-52 truncate px-3 py-2.5 text-xs">@if ($person->external_id)<span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $person->external_id }}</span>@else<span class="text-zinc-400">{{ __('Anonymous') }}</span>@endif</td>
                                <td data-col="address" class="max-w-56 truncate px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $isAddress ? $subscription->token : '—' }}</td>
                                <td data-col="app_version" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $subscription->app_version ?? '—' }}</td>
                                <td data-col="timezone" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->timezone ?? '—' }}</td>
                                <td data-col="country" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->country ?? '—' }}</td>
                                <td data-col="language" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $languageName($person->language) ?? '—' }}</td>
                                <td data-col="tags" class="px-3 py-2.5">
                                    <span class="flex max-w-72 gap-1 overflow-hidden">
                                        @forelse (array_slice($person->tags ?? [], 0, 3, true) as $key => $value)
                                            <span class="inline-flex shrink-0 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ $key }}={{ \Illuminate\Support\Str::limit((string) $value, 18) }}</span>
                                        @empty
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endforelse
                                        @if (count($person->tags ?? []) > 3)<span class="text-[11px] text-zinc-400">+{{ count($person->tags) - 3 }}</span>@endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
        @if ($results->hasPages())
            <div data-live-pages class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800">{{ $results->links() }}</div>
        @endif
    </div>
    </div>
    </div>
    @can('send', $project)
        <x-ui.modal name="add-test-device" :title="__('Add a test device')" :description="__('Every device of an external id is added at once.')" :open="$errors->has('identifier')">
            <form method="POST" action="{{ route('projects.test-devices.store', $project) }}" class="space-y-4">
                @csrf
                <x-ui.input name="identifier" :label="__('Device id, external id or OneSignal id')" :hint="__('In OneSignal, the Subscription ID of the device (Audience → Test subscriptions).')" required autocomplete="off" :placeholder="__('e.g. user-42')" />
                <x-ui.input name="test_name" :label="__('Name')" :placeholder="__('e.g. My iPhone')" maxlength="64" optional />
                <div class="flex justify-end gap-3 pt-2">
                    <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', 'add-test-device')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="plus">{{ __('Add') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <div x-data="{ url: '', name: '' }" x-on:rename-test-device.window="url = $event.detail.url; name = $event.detail.name; $dispatch('open-modal', 'rename-test-device')">
            <x-ui.modal name="rename-test-device" :title="__('Rename the test device')">
                <form method="POST" :action="url" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="rename_test_name" class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Name') }}</label>
                        <input id="rename_test_name" name="test_name" x-model="name" maxlength="64" class="form-control" placeholder="{{ __('e.g. My iPhone') }}">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', 'rename-test-device')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button>{{ __('Save') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        </div>
    @endcan
</x-layouts.app>
