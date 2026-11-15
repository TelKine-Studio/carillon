@props(['project', 'section'])
{{-- Two-column layout of the project settings. --}}
@php
    $tabs = [
        'general' => ['icon' => 'settings', 'label' => __('General'), 'text' => __('Name, icon, defaults')],
        'platforms' => ['icon' => 'smartphone', 'label' => __('Platforms'), 'text' => __('Apple, Firebase, web push')],
        'api-keys' => ['icon' => 'key', 'label' => __('API keys'), 'text' => __('Send from your server')],
        'webhooks' => ['icon' => 'link', 'label' => __('Webhooks'), 'text' => __('Events sent to your server')],
        'members' => ['icon' => 'users', 'label' => __('Members'), 'text' => __('Who works on this project')],
    ];
@endphp
<x-layouts.app :title="__('Settings').' · '.$project->name">
    <x-ui.page-header :title="__('Settings')" :description="$project->name" />

    <nav class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] xl:hidden">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route('projects.settings', [$project, $key]) }}" @class(['inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition', 'bg-white text-zinc-900 shadow-(--shadow-soft) ring-1 ring-zinc-300 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700' => $section === $key, 'bg-white text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800' => $section !== $key])>
                <x-ui.icon :name="$tab['icon']" class="size-4" />{{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="grid gap-8 xl:grid-cols-[260px_minmax(0,1fr)]">
        <nav class="hidden xl:block">
            <ul class="sticky top-24 space-y-1">
                @foreach ($tabs as $key => $tab)
                    <li>
                        <a href="{{ route('projects.settings', [$project, $key]) }}" @class(['flex items-start gap-3 rounded-xl p-3 transition', 'bg-white shadow-(--shadow-soft) ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800' => $section === $key, 'hover:bg-zinc-100 dark:hover:bg-white/5' => $section !== $key])>
                            <span @class(['inline-flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-brand-600 text-white' => $section === $key, 'bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400' => $section !== $key])><x-ui.icon :name="$tab['icon']" class="size-4" /></span>
                            <span>
                                <span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $tab['label'] }}</span>
                                <span class="block text-xs text-zinc-500">{{ $tab['text'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <div class="min-w-0 max-w-3xl space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
