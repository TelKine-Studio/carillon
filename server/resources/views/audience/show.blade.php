@php
    $tags = collect($subscriber->tags ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all();
@endphp
<x-layouts.app :title="($subscriber->external_id ?? __('Anonymous')).' · '.$project->name">
    <x-ui.page-header :title="$subscriber->external_id ?? __('Anonymous person')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.subscribers.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Audience') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($subscriber->id, 8, '') }}</span>
        </x-slot:breadcrumb>
        @can('send', $project)
            <form method="POST" action="{{ route('projects.subscribers.destroy', [$project, $subscriber]) }}" data-confirm-title="{{ __('Delete this person?') }}" data-confirm="{{ __('Their devices stop receiving notifications until the app registers them again.') }}" data-confirm-action="{{ __('Delete') }}">
                @csrf
                @method('DELETE')
                <x-ui.button variant="secondary" icon="trash">{{ __('Delete') }}</x-ui.button>
            </form>
        @endcan
    </x-ui.page-header>

    <dl class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ([
            __('Language') => $subscriber->language ? strtoupper($subscriber->language) : '—',
            __('Time zone') => $subscriber->timezone ?? '—',
            __('Country') => $subscriber->country ?? '—',
            __('Last seen') => $subscriber->last_active_at?->diffForHumans() ?? '—',
        ] as $label => $value)
            <div class="card px-4 py-3">
                <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                <dd class="mt-0.5 truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('Devices')" icon="smartphone" :padding="false">
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($subscriber->subscriptions as $subscription)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6">
                            <x-audience.device :subscription="$subscription" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $subscription->type === 'email' ? $subscription->token : ($subscription->device_model ?: ucfirst($subscription->type)) }}
                                    @if ($subscription->environment === 'sandbox')<x-ui.badge color="violet" class="ml-1">{{ __('Development') }}</x-ui.badge>@endif
                                </p>
                                <p class="text-xs text-zinc-500">{{ collect([$subscription->device_os, $subscription->app_version ? 'v'.$subscription->app_version : null, $subscription->usesOneSignalSdk() ? __('OneSignal SDK (app not updated yet)') : $subscription->sdk, trans_choice(':count session|:count sessions', $subscription->sessions)])->filter()->implode(' · ') }}</p>
                                <p class="mt-1 font-mono text-[11px] text-zinc-400" x-data="copy(@js($subscription->id))"><button type="button" x-on:click="copy()" class="hover:text-zinc-700 dark:hover:text-zinc-200">{{ $subscription->id }}</button> <span x-show="copied" x-cloak class="text-emerald-600">✓</span></p>
                            </div>
                            <x-audience.state :subscription="$subscription" />
                        </li>
                    @endforeach
                </ul>
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

        <aside class="space-y-6">
            <form method="POST" action="{{ route('projects.subscribers.tags', [$project, $subscriber]) }}" x-data="{ tags: @js($tags) }">
                @csrf
                @method('PUT')
                <x-ui.card :title="__('Tags')" :description="__('Set by your app or the API; used to target messages.')" icon="file">
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
                        <x-slot:footer>
                            <button type="button" class="mr-auto text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="tags.push({ key: '', value: '' })">+ {{ __('Add a tag') }}</button>
                            <x-ui.button size="sm">{{ __('Save') }}</x-ui.button>
                        </x-slot:footer>
                    @endcan
                </x-ui.card>
            </form>

            @can('send', $project)
                <form method="POST" action="{{ route('projects.subscribers.test', [$project, $subscriber]) }}">
                    @csrf
                    <x-ui.card :title="__('Send a test')" :description="__('To the devices of this person only.')" icon="send">
                        <div class="space-y-4">
                            <x-ui.input name="title" :label="__('Title')" optional />
                            <x-ui.textarea name="body" rows="2" :label="__('Message')" required :value="__('This is a test notification.')" />
                        </div>
                        <x-slot:footer><x-ui.button size="sm" icon="send">{{ __('Send the test') }}</x-ui.button></x-slot:footer>
                    </x-ui.card>
                </form>
            @endcan
        </aside>
    </div>
</x-layouts.app>
