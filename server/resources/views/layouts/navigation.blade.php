{{-- Sidebar content, shared by the desktop sidebar and the mobile drawer. --}}
@php
    $user = auth()->user();
    $current = request()->route('project');
    $current = $current instanceof \App\Models\Project ? $current : null;
    // Sections of a project; each appears once its pages exist.
    $sections = collect([
        ['route' => 'projects.show', 'match' => 'projects.show', 'icon' => 'home', 'label' => __('Overview')],
        ['route' => 'projects.stats', 'match' => 'projects.stats', 'icon' => 'bar-chart', 'label' => __('Statistics')],
        ['route' => 'projects.messages.index', 'match' => 'projects.messages.*', 'icon' => 'send', 'label' => __('Messages')],
        ['route' => 'projects.subscribers.index', 'match' => 'projects.subscribers.*', 'icon' => 'users', 'label' => __('Audience')],
        ['route' => 'projects.segments.index', 'match' => 'projects.segments.*', 'icon' => 'layers', 'label' => __('Segments')],
        ['route' => 'projects.templates.index', 'match' => 'projects.templates.*', 'icon' => 'file', 'label' => __('Templates')],
        ['route' => 'projects.settings', 'match' => 'projects.settings*', 'icon' => 'settings', 'label' => __('Settings')],
    ])->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']));
    $admin = [
        ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'icon' => 'users', 'label' => __('Users'), 'count' => $nav['invitations'] ?: null],
        ['route' => 'admin.audit', 'match' => 'admin.audit', 'icon' => 'shield', 'label' => __('Audit log')],
        ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'icon' => 'settings', 'label' => __('Settings')],
    ];
@endphp
<div class="flex h-full flex-col">
    <a href="{{ route('projects.index') }}" class="flex items-center gap-3 px-5 pt-6 pb-5">
        <x-ui.logo class="size-9" />
        <span class="min-w-0">
            <span class="block truncate text-[15px] font-semibold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name') }}</span>
            <span class="block text-xs text-zinc-500">{{ __('Push notifications') }}</span>
        </span>
    </a>

    <nav class="flex-1 space-y-7 overflow-y-auto px-3 pb-4">
        {{-- Project switcher --}}
        <div class="space-y-1">
            <x-ui.dropdown width="w-full">
                <x-slot:trigger>
                    <button type="button" class="flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white p-2 text-left shadow-xs transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                        @if ($current)
                            <x-ui.project-icon :project="$current" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $current->name }}</span>
                                <span class="block text-xs text-zinc-500">{{ __('Project') }}</span>
                            </span>
                        @else
                            <span class="grid size-8 place-items-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5"><x-ui.icon name="grid" class="size-4" /></span>
                            <span class="min-w-0 flex-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('All projects') }}</span>
                        @endif
                        <x-ui.icon name="chevrons-up-down" class="size-4 text-zinc-400" />
                    </button>
                </x-slot:trigger>

                @foreach ($nav['projects'] as $project)
                    <x-ui.dropdown-item :href="route('projects.show', $project)">
                        <span class="flex items-center gap-2.5">
                            <x-ui.project-icon :project="$project" size="xs" />
                            <span class="truncate">{{ $project->name }}</span>
                            @if ($current?->is($project))<x-ui.icon name="check" class="ml-auto size-4 text-brand-600" />@endif
                        </span>
                    </x-ui.dropdown-item>
                @endforeach
                @if ($nav['projects']->isNotEmpty())<div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div>@endif
                <x-ui.dropdown-item :href="route('projects.index')" icon="grid">{{ __('All projects') }}</x-ui.dropdown-item>
                @can('create', \App\Models\Project::class)
                    <x-ui.dropdown-item :href="route('projects.create')" icon="plus">{{ __('New project') }}</x-ui.dropdown-item>
                @endcan
            </x-ui.dropdown>
        </div>

        @if ($current)
            <div class="space-y-1">
                @foreach ($sections as $item)
                    <a href="{{ route($item['route'], $current) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])])>
                        <x-ui.icon :name="$item['icon']" class="size-[18px]" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @can('admin')
            <div class="space-y-1">
                <p class="eyebrow px-3 pb-1.5">{{ __('Administration') }}</p>
                @foreach ($admin as $item)
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])])>
                        <x-ui.icon :name="$item['icon']" class="size-[18px]" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if ($item['count'] ?? null)
                            <span class="rounded-md bg-brand-100 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700 tabular-nums dark:bg-brand-500/15 dark:text-brand-300">{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endcan
    </nav>

    <div class="space-y-3 p-3">
        <p class="px-2 text-[11px] text-zinc-400 dark:text-zinc-600">
            Carillon {{ config('carillon.version') }} · {{ __('a project by') }} <a href="{{ config('carillon.publisher_url') }}" class="font-medium hover:text-zinc-600 dark:hover:text-zinc-400">{{ config('carillon.publisher') }}</a>
        </p>

        <x-ui.dropdown align="top" width="w-full">
            <x-slot:trigger>
                <button type="button" class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-zinc-100 dark:hover:bg-white/5">
                    <x-ui.avatar :user="$user" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ $user->email }}</span>
                    </span>
                    <x-ui.icon name="chevrons-up-down" class="size-4 text-zinc-400" />
                </button>
            </x-slot:trigger>

            <x-ui.dropdown-item :href="route('profile.edit')" icon="user">{{ __('Profile') }}</x-ui.dropdown-item>
            <div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div>
            <div class="px-2.5 py-1.5">
                <p class="mb-2 text-xs font-medium text-zinc-500">{{ __('Theme') }}</p>
                <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-white/5">
                    @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $theme => $icon)
                        <button type="button" @click="$store.theme.set('{{ $theme }}')" title="{{ __(ucfirst($theme)) }}"
                            class="inline-flex items-center justify-center rounded-md py-1.5 text-zinc-500 transition"
                            :class="$store.theme.value === '{{ $theme }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'hover:text-zinc-800 dark:hover:text-zinc-200'">
                            <x-ui.icon :name="$icon" class="size-4" />
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div>
            <x-ui.dropdown-item :href="config('carillon.repository').'/blob/main/docs/api.md'" icon="book" target="_blank" rel="noopener">{{ __('API documentation') }}</x-ui.dropdown-item>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.dropdown-item icon="logout" danger>{{ __('Log out') }}</x-ui.dropdown-item>
            </form>
        </x-ui.dropdown>
    </div>
</div>
