@php
    $n = fn ($v) => $v === null ? '—' : number_format((int) $v, 0, ',', ' ');
    // Built-in segments, described with the same chips as the conditions of a custom one.
    $builtIn = [
        'Subscribed Users' => [['bell', __('Subscribed')]],
        'Active Users' => [['clock', __('Last session less than :hours hours ago', ['hours' => 168])]],
        'Engaged Users' => [['clock', __('Last session less than :hours hours ago', ['hours' => 168])], 'and', ['activity', __('Sessions greater than :count', ['count' => 4])]],
        'Inactive Users' => [['clock', __('Last session more than :hours hours ago', ['hours' => 168])]],
        'iOS Users' => [['apple', __('Platform is :platform', ['platform' => 'iOS'])]],
        'Android Users' => [['android', __('Platform is :platform', ['platform' => 'Android'])]],
        'Web Users' => [['globe-web', __('Platform is :platform', ['platform' => 'Web'])]],
        'Test Devices' => [['smartphone', __('Test device')]],
    ];
    $relations = ['=' => __('is'), '!=' => __('is not'), '>' => __('greater than'), '<' => __('less than'), 'exists' => __('is set'), 'not_exists' => __('is not set'), 'time_elapsed_gt' => __('older than (s)'), 'time_elapsed_lt' => __('more recent than (s)')];
    $fields = ['tag' => ['tag', null], 'language' => ['message', __('Language')], 'country' => ['globe', __('Country')], 'app_version' => ['smartphone', __('App version')], 'session_count' => ['activity', __('Sessions')], 'external_id' => ['key', __('External id')], 'device_type' => ['smartphone', __('Platform')], 'last_session' => ['clock', __('Last session')], 'first_session' => ['clock', __('First session')], 'event' => ['zap', __('Event')], 'event_value' => ['zap', __('Event total (value)')], 'last_click' => ['eye', __('Last click')], 'location' => ['map-pin', __('Location')]];
    // One readable chip per condition of a custom segment.
    $chip = function (array $f) use ($relations, $fields) {
        if (($f['operator'] ?? null) === 'OR') {
            return 'or';
        }
        [$icon, $label] = $fields[$f['field'] ?? ''] ?? ['sliders', $f['field'] ?? ''];
        $relation = $f['relation'] ?? '=';
        $text = match ($f['field'] ?? null) {
            'tag' => trim(($f['key'] ?? '').' '.($relations[$relation] ?? $relation).' '.($f['value'] ?? '')),
            'last_session', 'first_session', 'last_click' => $label.' '.($relation === '>' ? __('more than :hours hours ago', ['hours' => $f['hours_ago'] ?? $f['value'] ?? '']) : __('less than :hours hours ago', ['hours' => $f['hours_ago'] ?? $f['value'] ?? ''])),
            'event', 'event_value' => trim($label.' '.($f['key'] ?? '').' '.($relations[$relation] ?? $relation).' '.($f['value'] ?? '').(isset($f['hours_ago']) ? ' · '.__(':hours h', ['hours' => $f['hours_ago']]) : '')),
            'location' => __('Within :radius m of :lat, :long', ['radius' => number_format((float) ($f['radius'] ?? 0), 0, ',', ' '), 'lat' => $f['lat'] ?? '', 'long' => $f['long'] ?? '']),
            default => trim($label.' '.($relations[$relation] ?? $relation).' '.($f['value'] ?? '')),
        };

        return [$icon, $text];
    };
    $quickstarts = [
        ['new', 'user-plus', __('New users'), __('Reach the users who joined in the last 3 hours.')],
        ['country', 'globe', __('By country'), __('Your audience of one country, with its language.')],
        ['location', 'map-pin', __('Around a place'), __('Devices last seen within a radius of a point.')],
        ['tag', 'tag', __('By tags'), __('Users with a property sent by your app or your server.')],
    ];
    $customCount = $segments->count();
@endphp
<x-layouts.app :title="__('Segments').' · '.$project->name">
    <x-ui.page-header :title="__('Segments')" :description="__('Audiences you can target in one click: by tags, language, country, app version, activity or location.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.segments.create', $project)" icon="plus">{{ __('New segment') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    @can('send', $project)
        {{-- Quick starts, as OneSignal. --}}
        <section x-data="{ open: {{ $customCount === 0 ? 'true' : 'false' }} }" class="mb-6">
            <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200">
                <x-ui.icon name="chevron-right" class="size-4 transition" ::class="open && 'rotate-90'" />{{ __('Start from a quick segment') }}
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($quickstarts as [$recipe, $icon, $title, $text])
                    <a href="{{ route('projects.segments.create', [$project, 'recipe' => $recipe]) }}" class="card card-hover group flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ $title }}</h3>
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$icon" class="size-5" /></span>
                        </div>
                        <p class="mt-2 flex-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</p>
                        <span class="mt-4 inline-flex h-7 w-fit items-center gap-1.5 rounded-md border border-zinc-300 px-2.5 text-[13px] font-medium text-zinc-800 transition-colors group-hover:border-zinc-400 group-hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:group-hover:bg-zinc-800"><x-ui.icon name="plus" class="size-3.5" />{{ __('Create') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endcan

    <div x-data="{ tab: 'all' }">
        <nav class="scrollbar-none mb-4 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800" aria-label="{{ __('Segments') }}">
            @foreach (['all' => [__('All'), $customCount + count($builtIn)], 'custom' => [__('Custom'), $customCount], 'built-in' => [__('Built-in'), count($builtIn)]] as $key => [$tabLabel, $tabCount])
                <button type="button" x-on:click="tab = '{{ $key }}'" class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition"
                    :class="tab === '{{ $key }}' ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">
                    {{ $tabLabel }}<span class="min-w-5 rounded bg-zinc-100 px-1 text-center text-xs tabular-nums text-zinc-500 dark:bg-white/10 dark:text-zinc-400">{{ $tabCount }}</span>
                </button>
            @endforeach
        </nav>

        <div class="card">
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                            <th class="px-4 py-2.5 font-medium sm:px-5">{{ __('Name') }}</th>
                            <th class="px-3 py-2.5 font-medium">{{ __('Type') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Push subscribers') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Email subscribers') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('SMS subscribers') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Users') }}</th>
                            <th class="w-12 px-4 py-2.5 sm:px-5"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ([...$segments->map(fn ($s) => [$s->name, $s, collect($s->filters)->map($chip)->all()])->all(), ...collect($builtIn)->map(fn ($chips, $name) => [$name, null, $chips])->values()->all()] as [$name, $segment, $chips])
                            @php $count = $counts[$name] ?? null; @endphp
                            <tr x-show="tab === 'all' || tab === '{{ $segment ? 'custom' : 'built-in' }}'" class="transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="px-4 py-3 sm:px-5">
                                    @if ($segment)
                                        <a href="{{ route('projects.segments.edit', [$project, $segment]) }}" class="font-medium break-words text-zinc-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-300">{{ $name }}</a>
                                    @else
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ __($name) }}</p>
                                    @endif
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        @foreach ($chips as $item)
                                            @if ($item === 'and' || $item === 'or')
                                                <span class="text-xs text-zinc-400">{{ $item === 'and' ? __('and') : __('or') }}</span>
                                            @else
                                                <span class="inline-flex max-w-full items-center gap-1 rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><x-ui.icon :name="$item[0]" class="size-3 shrink-0 text-zinc-400" /><span class="break-all">{{ $item[1] }}</span></span>
                                            @endif
                                        @endforeach
                                        @if ($segment && $count === null)
                                            <x-ui.badge color="amber">{{ __('Invalid condition') }}</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">@if ($segment)<x-ui.badge color="brand">{{ __('Custom') }}</x-ui.badge>@else<x-ui.badge>{{ __('Built-in') }}</x-ui.badge>@endif</td>
                                <td class="px-3 py-3 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($count['push'] ?? null) }}</td>
                                <td class="px-3 py-3 text-right text-zinc-600 tabular-nums dark:text-zinc-400">{{ $n($count['email'] ?? null) }}</td>
                                <td class="px-3 py-3 text-right text-zinc-600 tabular-nums dark:text-zinc-400">{{ $n($count['sms'] ?? null) }}</td>
                                <td class="px-3 py-3 text-right text-zinc-600 tabular-nums dark:text-zinc-400">{{ $n($count['users'] ?? null) }}</td>
                                <td class="px-4 py-3 text-right sm:px-5">
                                    <x-ui.dropdown width="w-56">
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="ghost" size="icon" icon="more"><span class="sr-only">{{ __('Actions') }}</span></x-ui.button>
                                        </x-slot:trigger>
                                        @can('send', $project)
                                            <x-ui.dropdown-item :href="route('projects.messages.create', [$project, 'segment' => $name])" icon="send">{{ __('Send a message') }}</x-ui.dropdown-item>
                                        @endcan
                                        <x-ui.dropdown-item :href="route('projects.subscribers.index', [$project, 'view' => 'devices', 'segment' => $name])" icon="smartphone">{{ __('See the devices') }}</x-ui.dropdown-item>
                                        @if ($segment)
                                            @can('send', $project)
                                                <x-ui.dropdown-item :href="route('projects.segments.edit', [$project, $segment])" icon="pencil">{{ __('Edit') }}</x-ui.dropdown-item>
                                                <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                                                <form method="POST" action="{{ route('projects.segments.destroy', [$project, $segment]) }}" data-confirm-title="{{ __('Delete this segment?') }}" data-confirm="{{ __('Messages already sent are not affected.') }}" data-confirm-action="{{ __('Delete') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.dropdown-item icon="trash" danger>{{ __('Delete') }}</x-ui.dropdown-item>
                                                </form>
                                            @endcan
                                        @endif
                                    </x-ui.dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p x-show="tab === 'custom' && {{ $customCount }} === 0" x-cloak class="flex items-center gap-2 border-t border-zinc-100 px-4 py-3 text-xs text-zinc-500 sm:px-5 dark:border-zinc-800">
                <x-ui.icon name="info" class="size-3.5 shrink-0" />{{ __('Create your own: for example the customers with the tag plan = vip, or the people inactive for 30 days.') }}
            </p>
        </div>
    </div>
</x-layouts.app>
