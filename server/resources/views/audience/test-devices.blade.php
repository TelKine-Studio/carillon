<x-layouts.app :title="__('Test devices').' · '.$project->name">
    <x-ui.page-header :title="__('Test devices')" :description="__('They receive the tests sent from the composer, and the segment “Test Devices”. Mark your own phones here.')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.subscribers.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Audience') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ __('Test devices') }}</span>
        </x-slot:breadcrumb>
        @can('send', $project)
            <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-test-device')">{{ __('Add a test device') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card">
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($devices as $device)
                <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 sm:px-6" x-data="{ editing: false }">
                    <x-audience.device :subscription="$device" />
                    <div class="min-w-0 flex-1 basis-[calc(100%-3.5rem)] sm:basis-0">
                        <div x-show="! editing">
                            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $device->test_name ?: ($device->device_model ?: __('Unnamed device')) }}</p>
                            <p class="truncate text-xs text-zinc-500">
                                {{ collect([$device->device_model, $device->device_os, $device->app_version ? 'v'.$device->app_version : null, $device->subscriber?->external_id])->filter()->implode(' · ') }}
                            </p>
                        </div>
                        @can('send', $project)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('projects.test-devices.update', [$project, $device->id]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input name="test_name" value="{{ $device->test_name }}" maxlength="64" class="form-control !py-1.5 text-sm" placeholder="{{ __('e.g. My iPhone') }}" aria-label="{{ __('Name') }}">
                                <x-ui.button size="sm">{{ __('Save') }}</x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">{{ __('Cancel') }}</x-ui.button>
                            </form>
                        @endcan
                    </div>
                    <x-audience.state :subscription="$device" />
                    <div class="ml-auto flex items-center gap-1" x-show="! editing">
                        <x-ui.button variant="ghost" size="sm" :href="route('projects.subscribers.show', [$project, $device->subscriber_id])">{{ __('Profile') }}</x-ui.button>
                        @can('send', $project)
                            <x-ui.button type="button" variant="ghost" size="icon" icon="pencil" class="size-8" x-on:click="editing = true"><span class="sr-only">{{ __('Rename') }}</span></x-ui.button>
                            <form method="POST" action="{{ route('projects.test-devices.destroy', [$project, $device->id]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost-danger" size="icon" icon="x" class="size-8" title="{{ __('Stop using as a test device') }}"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                            </form>
                        @endcan
                    </div>
                </li>
            @empty
                <li><x-ui.empty icon="smartphone" :title="__('No test device yet')" :description="__('Add your own phone with its device id or the external id of your account in the app.')" /></li>
            @endforelse
        </ul>
    </div>

    @can('send', $project)
        <x-ui.modal name="add-test-device" :title="__('Add a test device')" :description="__('Every device of an external id is added at once.')" :open="$errors->has('identifier')">
            <form method="POST" action="{{ route('projects.test-devices.store', $project) }}" class="space-y-4">
                @csrf
                <x-ui.input name="identifier" :label="__('Device id, external id or OneSignal id')" :hint="__('In OneSignal, the Subscription ID of the device (Audience → Test subscriptions).')" required autofocus autocomplete="off" :placeholder="__('e.g. user-42')" />
                <x-ui.input name="test_name" :label="__('Name')" :placeholder="__('e.g. My iPhone')" maxlength="64" optional />
                <div class="flex justify-end gap-3 pt-2">
                    <x-ui.button type="button" variant="ghost" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="plus">{{ __('Add') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</x-layouts.app>
