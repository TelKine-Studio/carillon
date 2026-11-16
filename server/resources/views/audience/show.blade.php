@php
    $tags = collect($subscriber->tags ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all();
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $reachable = $subscriber->subscriptions->contains(fn ($s) => $s->enabled && ! $s->invalid_at);
    $country = $subscriber->country ? (locale_get_display_region('-'.$subscriber->country, app()->getLocale()) ?: $subscriber->country) : null;
    $language = $subscriber->language ? (locale_get_display_language($subscriber->language, app()->getLocale()) ?: $subscriber->language) : null;
    $deviceLabel = fn ($d) => $d->type === 'email' ? $d->token : collect([['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'][$d->type] ?? $d->type, $d->device_model, $d->device_os ? '('.$d->device_os.')' : null])->filter()->implode(' · ');
@endphp
<x-layouts.app :title="($subscriber->external_id ?? __('Anonymous')).' · '.$project->name">
    <x-ui.page-header :title="$subscriber->external_id ?? __('Anonymous person')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.subscribers.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Audience') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($subscriber->id, 8, '') }}</span>
        </x-slot:breadcrumb>
        <x-ui.dropdown width="w-64">
            <x-slot:trigger>
                <x-ui.button type="button" variant="secondary" icon-right="chevron-down">{{ __('Actions') }}</x-ui.button>
            </x-slot:trigger>
            @can('send', $project)
                <x-ui.dropdown-item type="button" icon="send" x-on:click="open = false; $dispatch('open-modal', 'send-test')">{{ __('Send a test') }}</x-ui.dropdown-item>
                <form method="POST" action="{{ route('projects.test-devices.store', $project) }}">
                    @csrf
                    <input type="hidden" name="identifier" value="{{ $subscriber->id }}">
                    <x-ui.dropdown-item icon="smartphone">{{ __('Add as test devices') }}</x-ui.dropdown-item>
                </form>
            @endcan
            <div x-data="copy(@js($subscriber->id))"><x-ui.dropdown-item type="button" icon="copy" x-on:click="copy()"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy the id'))">{{ __('Copy the id') }}</span></x-ui.dropdown-item></div>
            @can('send', $project)
                <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                <form method="POST" action="{{ route('projects.subscribers.destroy', [$project, $subscriber]) }}" data-confirm-title="{{ __('Delete this person?') }}" data-confirm="{{ __('Their devices stop receiving notifications until the app registers them again.') }}" data-confirm-action="{{ __('Delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.dropdown-item icon="trash" danger>{{ __('Delete the person') }}</x-ui.dropdown-item>
                </form>
            @endcan
        </x-ui.dropdown>
    </x-ui.page-header>

    <div class="-mt-3 mb-6 flex flex-wrap items-center gap-2 text-sm">
        <span x-data="copy(@js($subscriber->id))" class="inline-flex items-center gap-1.5 font-mono text-xs text-zinc-500">
            {{ $subscriber->id }}
            <button type="button" x-on:click="copy()" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"><x-ui.icon name="copy" class="size-3.5" x-show="!copied" /><x-ui.icon name="check" class="size-3.5 text-emerald-600" x-show="copied" x-cloak /></button>
        </span>
        @if ($reachable)<x-ui.badge color="green" dot>{{ __('Subscribed') }}</x-ui.badge>@else<x-ui.badge>{{ __('Not reachable') }}</x-ui.badge>@endif
    </div>

    <div x-data="{ tab: ['overview', 'devices', 'tags'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview' }" x-init="$watch('tab', t => history.replaceState(null, '', '#' + t))">
        <nav class="mb-6 flex gap-6 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none] dark:border-zinc-800">
            @foreach (['overview' => [__('Overview'), null], 'devices' => [__('Devices'), $subscriber->subscriptions->count()], 'tags' => [__('Tags'), count($tags)]] as $key => [$label, $count])
                <button type="button" x-on:click="tab = '{{ $key }}'" class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-0.5 pb-3 text-sm font-medium transition"
                    :class="tab === '{{ $key }}' ? 'border-brand-600 text-zinc-900 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:hover:text-white'">
                    {{ $label }}@if ($count !== null)<span class="rounded bg-zinc-100 px-1.5 text-xs tabular-nums text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $count }}</span>@endif
                </button>
            @endforeach
        </nav>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)] lg:items-start">
            <x-ui.card :title="__('Profile')" icon="user">
                <dl class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    @foreach ([
                        ['key', __('External id'), $subscriber->external_id, true],
                        ['globe', __('Country'), $country ? $country.' ('.$subscriber->country.')' : null, false],
                        ['message', __('Language'), $language, false],
                        ['clock', __('Time zone'), $subscriber->timezone, false],
                    ] as [$icon, $label, $value, $mono])
                        <div class="flex items-center gap-3 py-2.5 first:pt-0">
                            <dt class="flex w-32 shrink-0 items-center gap-2 text-zinc-500"><x-ui.icon :name="$icon" class="size-4 text-zinc-400" />{{ $label }}</dt>
                            <dd @class(['min-w-0 truncate text-zinc-900 dark:text-zinc-100', 'font-mono text-xs' => $mono && $value])>{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <h3 class="mt-6 mb-2 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Devices') }}</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($subscriber->subscriptions as $device)
                        <li class="flex items-center gap-2 text-zinc-700 dark:text-zinc-300">
                            <x-ui.icon :name="['ios' => 'apple', 'android' => 'android', 'email' => 'mail'][$device->type] ?? 'globe-web'" class="size-4 shrink-0 text-zinc-400" />
                            <span class="min-w-0 truncate">{{ $deviceLabel($device) }}</span>
                            <span @class(['ml-auto size-2 shrink-0 rounded-full', 'bg-emerald-500' => $device->enabled && ! $device->invalid_at, 'bg-red-500' => (bool) $device->invalid_at, 'bg-zinc-300 dark:bg-zinc-600' => ! $device->enabled && ! $device->invalid_at])></span>
                        </li>
                    @empty
                        <li class="text-zinc-500">{{ __('No device.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            <div class="min-w-0 space-y-6">
                <x-ui.card :title="__('Activity')" icon="activity">
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ([
                            [__('First seen'), $subscriber->created_at->diffForHumans(), $subscriber->created_at->isoFormat('LLL')],
                            [__('Last active'), $subscriber->last_active_at?->diffForHumans() ?? '—', $subscriber->last_active_at?->isoFormat('LLL')],
                            [__('Sessions'), $n($activity['sessions']), null],
                            [__('Received in 7 days'), $n($activity['received']), trans_choice(':count click|:count clicks', $activity['clicked'], ['count' => $activity['clicked']])],
                        ] as [$label, $value, $hint])
                            <div class="min-w-0">
                                <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                                <dd class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-white" @if ($hint) title="{{ $hint }}" @endif>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>

                <x-ui.card :title="__('Recent notifications')" icon="send" :padding="false">
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($deliveries as $delivery)
                        <li>
                            <a href="{{ route('projects.messages.show', [$project, $delivery->message_id]) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-zinc-50/80 sm:px-6 dark:hover:bg-white/[.02]">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $delivery->message?->label() }}</p>
                                    <p class="text-xs text-zinc-500">{{ $delivery->sent_at?->diffForHumans() }}@if ($delivery->error) · <span class="font-mono">{{ $delivery->error }}</span>@endif</p>
                                </div>
                                @if ($delivery->clicked_at)
                                    <x-ui.badge color="green">{{ __('Clicked') }}</x-ui.badge>
                                @elseif ($delivery->received_at)
                                    <x-ui.badge color="blue">{{ __('Received') }}</x-ui.badge>
                                @elseif ($delivery->status === 'sent')
                                    <x-ui.badge>{{ __('Delivered') }}</x-ui.badge>
                                @else
                                    <x-ui.badge color="red">{{ __('Failed') }}</x-ui.badge>
                                @endif
                            </a>
                        </li>
                    @empty
                        <li><x-ui.empty icon="send" :title="__('No notification yet')" :description="__('Notifications sent to this person in the last :days days appear here.', ['days' => app(\App\Support\Settings::class)->integer('messages.retention_days', 90)])" /></li>
                    @endforelse
                </ul>
                </x-ui.card>
            </div>
        </div>

        {{-- Devices --}}
        <div x-show="tab === 'devices'" x-cloak class="space-y-6">
            @forelse ($subscriber->subscriptions as $device)
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center gap-3 px-5 pt-5 sm:px-6">
                        <x-audience.device :subscription="$device" />
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                                {{ $device->type === 'email' ? __('Email') : __('Push') }}
                                <x-audience.state :subscription="$device" />
                                @if ($device->is_test)<x-ui.badge color="violet">{{ $device->test_name ?: __('Test device') }}</x-ui.badge>@endif
                                @if ($device->environment === 'sandbox')<x-ui.badge color="violet">{{ __('Development') }}</x-ui.badge>@endif
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-500" x-data="copy(@js($device->id))">
                                {{ __('Device id') }} <button type="button" x-on:click="copy()" class="font-mono text-brand-700 hover:underline dark:text-brand-300">{{ $device->id }}</button>
                                <span x-show="copied" x-cloak class="text-emerald-600">· {{ __('Copied') }}</span>
                            </p>
                        </div>
                        @if ($device->type !== 'email')
                            @can('send', $project)
                                <form method="POST" action="{{ route('projects.subscribers.test-device', [$project, $subscriber, $device->id]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_test" value="{{ $device->is_test ? 0 : 1 }}">
                                    <x-ui.button variant="ghost" size="sm" :icon="$device->is_test ? 'x' : 'plus'">{{ $device->is_test ? __('Stop using for tests') : __('Use for tests') }}</x-ui.button>
                                </form>
                            @endcan
                        @endif
                    </div>
                    <dl class="grid gap-x-8 px-5 pt-4 pb-5 text-sm sm:grid-cols-2 sm:px-6">
                        @foreach ([
                            [__('Device'), $device->type === 'email' ? $device->token : collect([$device->device_model, $device->device_os])->filter()->implode(' · '), false],
                            [__('Sessions'), $n($device->sessions), false],
                            [__('Created at'), $device->created_at?->isoFormat('DD/MM/YY HH:mm'), false],
                            [__('App version'), $device->app_version, false],
                            [__('Last active'), $device->last_active_at?->isoFormat('DD/MM/YY HH:mm'), false],
                            [__('SDK'), $device->usesOneSignalSdk() ? __('OneSignal SDK (app not updated yet)') : $device->sdk, false],
                            [__('Push token'), $device->type === 'email' ? null : $device->token, true],
                        ] as [$label, $value, $mono])
                            <div class="flex min-w-0 items-center gap-4 border-t border-zinc-100 py-2.5 dark:border-zinc-800">
                                <dt class="w-32 shrink-0 text-zinc-500">{{ $label }}</dt>
                                <dd @class(['min-w-0 truncate text-zinc-900 dark:text-zinc-100', 'font-mono text-xs' => $mono]) @if ($mono && $value) title="{{ $value }}" @endif>{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>
            @empty
                <div class="card"><x-ui.empty icon="smartphone" :title="__('No device.')" /></div>
            @endforelse
        </div>

        {{-- Tags --}}
        <div x-show="tab === 'tags'" x-cloak class="max-w-2xl">
            <form method="POST" action="{{ route('projects.subscribers.tags', [$project, $subscriber]) }}" x-data="{ tags: @js($tags), editing: false, reset() { this.tags = @js($tags); this.editing = false } }">
                @csrf
                @method('PUT')
                <x-ui.card :title="__('Tags')" :description="__('Set by your app or the API; used to target messages.')" icon="file">
                    @can('send', $project)
                        <x-slot:actions>
                            <x-ui.button type="button" variant="secondary" size="sm" icon="pencil" x-show="! editing" x-on:click="editing = true">{{ __('Edit') }}</x-ui.button>
                        </x-slot:actions>
                    @endcan
                    <div x-show="! editing">
                        @if ($tags)
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($tags as $tag)
                                    <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs dark:bg-white/5">
                                        <span class="text-zinc-500">{{ $tag['key'] }}</span><span class="truncate text-zinc-900 dark:text-zinc-100">{{ $tag['value'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-zinc-500">{{ __('No tag.') }}</p>
                        @endif
                    </div>
                    <div x-show="editing" x-cloak>
                    <template x-for="(tag, index) in tags" :key="index">
                        <div class="mb-2 flex gap-2">
                            <input :name="`tags[${index}][key]`" x-model="tag.key" class="form-control w-32 font-mono text-sm" placeholder="{{ __('key') }}" @cannot('send', $project) disabled @endcannot>
                            <input :name="`tags[${index}][value]`" x-model="tag.value" class="form-control min-w-0 font-mono text-sm" placeholder="{{ __('value') }}" @cannot('send', $project) disabled @endcannot>
                            @can('send', $project)
                                <x-ui.button type="button" variant="ghost" size="icon" icon="x" class="size-[42px] shrink-0" x-on:click="tags.splice(index, 1)"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                            @endcan
                        </div>
                    </template>
                    <p class="text-sm text-zinc-500" x-show="!tags.length">{{ __('No tag.') }}</p>
                    @can('send', $project)
                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <button type="button" class="mr-auto text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="tags.push({ key: '', value: '' })">+ {{ __('Add a tag') }}</button>
                            <x-ui.button type="button" variant="ghost" size="sm" x-on:click="reset()">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button size="sm">{{ __('Save') }}</x-ui.button>
                        </div>
                    @endcan
                    </div>
                </x-ui.card>
            </form>
        </div>
    </div>

    @can('send', $project)
        <x-ui.modal name="send-test" :title="__('Send a test')" :description="__('To the devices of this person only.')" :open="$errors->has('body')">
            <form method="POST" action="{{ route('projects.subscribers.test', [$project, $subscriber]) }}" class="space-y-4">
                @csrf
                <x-ui.input name="title" :label="__('Title')" optional />
                <x-ui.textarea name="body" rows="2" :label="__('Message')" required :value="__('This is a test notification.')" />
                <div class="flex justify-end gap-3 pt-2">
                    <x-ui.button type="button" variant="ghost" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="send">{{ __('Send the test') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</x-layouts.app>
