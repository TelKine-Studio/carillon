@php
    $n = fn ($v) => $v === null ? '—' : number_format((int) $v, 0, ',', ' ');
    $builtIn = [
        'Subscribed Users' => __('Every device that accepts notifications.'),
        'Active Users' => __('Opened the app in the last 7 days.'),
        'Engaged Users' => __('5 sessions or more, active in the last 7 days.'),
        'Inactive Users' => __('Not seen for more than 7 days.'),
        'iOS Users' => __('iPhone and iPad.'),
        'Android Users' => __('Android phones and tablets.'),
        'Web Users' => __('Browsers subscribed to web push.'),
        'Test Devices' => __('Your test devices, to try a message.'),
    ];
@endphp
<x-layouts.app :title="__('Segments').' · '.$project->name">
    <x-ui.page-header :title="__('Segments')" :description="__('Audiences you can target in one click: by tags, language, country, app version or activity.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.segments.create', $project)" icon="plus">{{ __('New segment') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="space-y-6">
        <x-ui.card :title="__('Your segments')" icon="layers" :padding="false">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($segments as $segment)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $segment->name }}</p>
                            <p class="mt-0.5 truncate font-mono text-xs text-zinc-500">
                                {{ collect($segment->filters)->map(fn ($f) => ($f['operator'] ?? null) === 'OR' ? __('or') : trim(($f['field'] === 'tag' ? ($f['key'] ?? '') : __(str_replace('_', ' ', $f['field']))).' '.($f['relation'] ?? '').' '.($f['value'] ?? ($f['hours_ago'] ?? '').(isset($f['hours_ago']) ? ' h' : ''))))->implode(' · ') }}
                            </p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $n($counts[$segment->name] ?? null) }}<span class="ml-1 text-xs font-normal text-zinc-500">{{ __('devices') }}</span></span>
                        @can('send', $project)
                            <div class="flex items-center gap-1">
                                <x-ui.button variant="ghost" size="sm" :href="route('projects.segments.edit', [$project, $segment])" icon="settings">{{ __('Edit') }}</x-ui.button>
                                <form method="POST" action="{{ route('projects.segments.destroy', [$project, $segment]) }}" data-confirm-title="{{ __('Delete this segment?') }}" data-confirm="{{ __('Messages already sent are not affected.') }}" data-confirm-action="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="ghost-danger" size="icon" icon="trash" class="size-8"><span class="sr-only">{{ __('Delete') }}</span></x-ui.button>
                                </form>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li><x-ui.empty icon="layers" :title="__('No segment yet')" :description="__('For example: customers with the tag plan = vip, or people inactive for 30 days.')" /></li>
                @endforelse
            </ul>
        </x-ui.card>

        <x-ui.card :title="__('Built-in segments')" icon="users" :padding="false">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($builtIn as $name => $text)
                    <li class="flex items-center gap-4 px-5 py-3.5 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __($name) }}</p>
                            <p class="text-xs text-zinc-500">{{ $text }}</p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $n($counts[$name] ?? 0) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-layouts.app>
