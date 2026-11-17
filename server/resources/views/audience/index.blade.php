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
        @include('audience.partials.people-table')
    @else
        @include('audience.partials.devices-table', ['test' => $view === 'test'])
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
