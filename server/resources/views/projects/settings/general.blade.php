<x-projects.settings :project="$project" :section="$section">
    <form method="POST" action="{{ route('projects.update', $project) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-ui.card :title="__('Project')" icon="settings">
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
        </x-ui.card>

        <x-ui.card class="mt-6" :title="__('Defaults')" :description="__('Used when a message does not specify them.')" icon="sliders">
            <fieldset @disabled(! $canConfigure) class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="default_url" type="url" :label="__('Default link')" :value="$project->setting('default_url')" placeholder="https://" :hint="__('Opened when a notification without link is tapped (web).')" optional />
                <x-ui.input name="default_ttl" type="number" min="0" max="2419200" :label="__('Time to live (seconds)')" :value="$project->setting('default_ttl')" placeholder="259200" :hint="__('How long Apple and Google keep trying to deliver to a device that is offline. 3 days by default.')" optional />
            </fieldset>
            @if ($canConfigure)
                <x-slot:footer><x-ui.button>{{ __('Save') }}</x-ui.button></x-slot:footer>
            @endif
        </x-ui.card>
    </form>

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
