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

    {{-- One table, as OneSignal: your segments first, then the built-in ones. --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                        <th class="px-4 py-3 font-medium sm:px-5">{{ __('Name') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('Type') }}</th>
                        <th class="px-3 py-3 text-right font-medium">{{ __('Devices') }}</th>
                        <th class="w-24 px-4 py-3 sm:px-5"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($segments as $segment)
                        <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                            <td class="max-w-0 px-4 py-3.5 sm:px-5" style="width: 55%">
                                <p class="font-medium break-words text-zinc-900 dark:text-white">{{ $segment->name }}</p>
                                <p class="mt-0.5 font-mono text-xs break-words text-zinc-500">
                                    {{ collect($segment->filters)->map(fn ($f) => ($f['operator'] ?? null) === 'OR' ? __('or') : trim(($f['field'] === 'tag' ? ($f['key'] ?? '') : __(str_replace('_', ' ', $f['field']))).' '.($f['relation'] ?? '').' '.($f['value'] ?? ($f['hours_ago'] ?? '').(isset($f['hours_ago']) ? ' h' : ''))))->implode(' · ') }}
                                </p>
                            </td>
                            <td class="px-3 py-3.5"><x-ui.badge color="brand">{{ __('Custom') }}</x-ui.badge></td>
                            <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($counts[$segment->name] ?? null) }}</td>
                            <td class="px-4 py-3.5 sm:px-5">
                                @can('send', $project)
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.button variant="ghost" size="icon" :href="route('projects.segments.edit', [$project, $segment])" icon="pencil" class="size-8" title="{{ __('Edit') }}"><span class="sr-only">{{ __('Edit') }}</span></x-ui.button>
                                        <form method="POST" action="{{ route('projects.segments.destroy', [$project, $segment]) }}" data-confirm-title="{{ __('Delete this segment?') }}" data-confirm="{{ __('Messages already sent are not affected.') }}" data-confirm-action="{{ __('Delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button variant="ghost-danger" size="icon" icon="trash" class="size-8" title="{{ __('Delete') }}"><span class="sr-only">{{ __('Delete') }}</span></x-ui.button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($builtIn as $name => $text)
                        <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                            <td class="max-w-0 px-4 py-3.5 sm:px-5">
                                <p class="font-medium break-words text-zinc-900 dark:text-white">{{ __($name) }}</p>
                                <p class="mt-0.5 text-xs text-zinc-500">{{ $text }}</p>
                            </td>
                            <td class="px-3 py-3.5"><x-ui.badge>{{ __('Built-in') }}</x-ui.badge></td>
                            <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($counts[$name] ?? 0) }}</td>
                            <td class="px-4 py-3.5 sm:px-5"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($segments->isEmpty())
            <p class="flex items-center gap-2 border-t border-zinc-100 px-4 py-3 text-xs text-zinc-500 sm:px-5 dark:border-zinc-800">
                <x-ui.icon name="info" class="size-3.5 shrink-0" />{{ __('Create your own: for example the customers with the tag plan = vip, or the people inactive for 30 days.') }}
            </p>
        @endif
    </div>
</x-layouts.app>
