<x-layouts.app :title="__('Projects')">
    <x-ui.page-header :title="__('Projects')" :description="__('One project per app (or per group of apps sharing their users): its push keys, subscribers and messages.')">
        @can('create', \App\Models\Project::class)
            <x-ui.button :href="route('projects.create')" icon="plus">{{ __('New project') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    @if ($projects->isEmpty())
        <x-ui.card>
            <x-ui.empty icon="grid" :title="__('No project yet')" :description="auth()->user()->can('create', \App\Models\Project::class) ? __('Create a project for your first app, then add its push keys.') : __('Ask an administrator to add you to a project.')">
                @can('create', \App\Models\Project::class)
                    <x-ui.button :href="route('projects.create')" icon="plus">{{ __('Create a project') }}</x-ui.button>
                @endcan
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-(--shadow-lift)">
                    <div class="flex items-start gap-4">
                        <x-ui.project-icon :project="$project" />
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-semibold text-zinc-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $project->name }}</h2>
                            <p class="mt-0.5 truncate font-mono text-xs text-zinc-400">{{ $project->app_id }}</p>
                        </div>
                        <x-ui.icon name="chevron-right" class="size-4 text-zinc-300 transition group-hover:translate-x-0.5" />
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        @php $providers = $project->configuredProviders(); @endphp
                        @forelse ([['apns', 'apple', 'iOS'], ['fcm', 'android', 'Android'], ['webpush', 'globe-web', 'Web']] as [$provider, $icon, $label])
                            @if (in_array($provider, $providers, true))
                                <x-ui.badge color="green" :icon="$icon">{{ $label }}</x-ui.badge>
                            @endif
                        @empty
                        @endforelse
                        @if ($providers === [])
                            <x-ui.badge color="amber" icon="alert">{{ __('No push keys yet') }}</x-ui.badge>
                        @endif
                        <span class="ml-auto text-xs text-zinc-500"><x-ui.icon name="users" class="mr-1 inline size-3.5" />{{ trans_choice(':count member|:count members', $project->members_count) }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
