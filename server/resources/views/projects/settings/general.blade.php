<x-projects.settings :project="$project" :section="$section">
    <form method="POST" action="{{ route('projects.update', $project) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-settings.section :title="__('Project')" icon="settings" :editable="$canConfigure" :fields="['name', 'icon']">
            <x-slot:summary>
                <div class="flex items-center gap-4">
                    <x-ui.project-icon :project="$project" size="lg" />
                    <x-settings.summary class="flex-1">
                        <x-settings.value :label="__('Name')" :value="$project->name" />
                        <x-settings.value :label="__('App ID')" :value="$project->app_id" mono />
                    </x-settings.summary>
                </div>
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="space-y-6">
                <x-ui.input name="name" :label="__('Name')" :value="$project->name" required maxlength="80" />
                <div class="flex items-start gap-4">
                    <x-ui.project-icon :project="$project" size="lg" />
                    <div class="min-w-0 flex-1 space-y-3">
                        <x-ui.file name="icon" accept="image/png,image/jpeg,image/webp" :label="__('Icon')" :hint="__('PNG, JPEG or WebP, 1 MB maximum.')" optional />
                        @if ($project->icon_path)
                            <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400"><input type="checkbox" name="remove_icon" value="1"> {{ __('Remove the icon') }}</label>
                        @endif
                    </div>
                </div>
            </fieldset>
        </x-settings.section>

        <x-settings.section class="mt-6" :title="__('Defaults')" :description="__('Used when a message does not specify them.')" icon="sliders" :editable="$canConfigure" :fields="['default_url', 'default_ttl', 'frequency_cap']">
            <x-slot:summary>
                <x-settings.summary>
                    <x-settings.value :label="__('Default link')" :value="$project->setting('default_url')" mono />
                    <x-settings.value :label="__('Daily cap per device')" :value="$project->setting('frequency_cap') ? trans_choice(':count notification per 24 hours|:count notifications per 24 hours', (int) $project->setting('frequency_cap')) : null" :empty="__('No cap')" />
                    <x-settings.value :label="__('Time to live')" :value="$project->setting('default_ttl') ? trans_choice(':count second|:count seconds', (int) $project->setting('default_ttl')) : null" :empty="__('3 days (default)')" />
                </x-settings.summary>
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="default_url" type="url" :label="__('Default link')" :value="$project->setting('default_url')" placeholder="https://" :hint="__('Opened when a notification without link is tapped (web).')" optional />
                <x-ui.input name="default_ttl" type="number" min="0" max="2419200" :label="__('Time to live (seconds)')" :value="$project->setting('default_ttl')" placeholder="259200" :hint="__('How long Apple and Google keep trying to deliver to a device that is offline. 3 days by default.')" optional />
                <x-ui.input name="frequency_cap" type="number" min="0" max="100" :label="__('Daily cap per device')" :value="$project->setting('frequency_cap')" :placeholder="__('No cap')" optional
                    :hint="__('A device that already received this many notifications in the last 24 hours is skipped. Tests are not capped.')" />
            </fieldset>
        </x-settings.section>
    </form>

    @php
        $located = $project->subscriptions()->whereNotNull('latitude')->count();
    @endphp
    <form method="POST" action="{{ route('projects.location.update', $project) }}">
        @csrf
        @method('PUT')
        <x-settings.section class="mt-6" :title="__('Device location')" :description="__('The SDK reads the last known position only when your app already has the location permission. It never asks for it.')" icon="map-pin" :editable="$canConfigure" :fields="['collect_location']">
            <x-slot:summary>
                <x-settings.summary>
                    <x-settings.value :label="__('Collect the position')" :on="$project->collectsLocation()" :value="__('On, when the app has the permission')" :empty="__('Collection turned off')" />
                    <x-settings.value :label="__('Devices with a position')" :value="number_format($located, 0, ',', ' ')" />
                </x-settings.summary>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Rounded to about 1 km and refreshed at most once a day. Target it in segments with the condition Location (within a radius).') }}</p>
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="space-y-4">
                <input type="hidden" name="collect_location" value="0">
                <x-ui.toggle name="collect_location" :checked="$project->collectsLocation()" :label="__('Collect the position of devices')"
                    :description="__('Turning it off erases the stored positions and tells the SDK to stop. Mention it in the privacy policy of your app.')" />
            </fieldset>
            @if ($canConfigure && $located > 0)
                <x-slot:danger>
                    <x-ui.button type="submit" form="erase-locations" variant="ghost-danger" class="mr-auto" icon="trash">{{ __('Erase the stored positions') }}</x-ui.button>
                </x-slot:danger>
            @endif
        </x-settings.section>
    </form>
    @if ($canConfigure && $located > 0)
        <form id="erase-locations" method="POST" action="{{ route('projects.location.destroy', $project) }}" class="hidden"
            data-confirm-title="{{ __('Erase the stored positions?') }}" data-confirm="{{ __(':count devices lose their position until the SDK sends it again.', ['count' => number_format($located, 0, ',', ' ')]) }}" data-confirm-action="{{ __('Erase') }}">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @can('delete', $project)
        <section class="rounded-2xl border border-red-200 bg-red-50/40 p-5 sm:p-6 dark:border-red-500/20 dark:bg-red-500/5" x-data="{ open: {{ $errors->has('confirm_name') ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-red-900 dark:text-red-200">{{ __('Delete this project') }}</h2>
                    <p class="mt-1 text-sm text-red-800/80 dark:text-red-200/70">{{ __('Its subscribers, messages, statistics and keys are deleted permanently. Apps using its App ID stop receiving notifications.') }}</p>
                </div>
                <x-ui.button type="button" variant="danger" icon="trash" x-on:click="open = true" x-show="!open">{{ __('Delete the project') }}</x-ui.button>
            </div>
            <form method="POST" action="{{ route('projects.destroy', $project) }}" x-show="open" x-cloak class="mt-5 flex flex-wrap items-end gap-3">
                @csrf
                @method('DELETE')
                <x-ui.input name="confirm_name" :label="__('Type :name to confirm', ['name' => $project->name])" class="min-w-60 flex-1" autocomplete="off" />
                <x-ui.button variant="danger" icon="trash">{{ __('Delete permanently') }}</x-ui.button>
            </form>
        </section>
    @endcan
</x-projects.settings>
