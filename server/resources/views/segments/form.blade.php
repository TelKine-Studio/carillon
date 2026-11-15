@php
    $text = [['=', __('is')], ['!=', __('is not')], ['exists', __('is set')], ['not_exists', __('is not set')]];
    $number = [['=', '='], ['!=', '≠'], ['>', '>'], ['<', '<']];
    $config = [
        'countUrl' => route('projects.segments.count', $project),
        'filters' => old('filters') ? json_decode(old('filters'), true) : ($segment->filters ?? []),
        'relations' => [
            'tag' => [['=', __('is')], ['!=', __('is not')], ['>', __('greater than')], ['<', __('less than')], ['exists', __('is set')], ['not_exists', __('is not set')], ['time_elapsed_gt', __('older than (s)')], ['time_elapsed_lt', __('more recent than (s)')]],
            'language' => $text, 'country' => $text, 'app_version' => $number, 'session_count' => $number,
            'external_id' => $text, 'device_type' => [['=', __('is')], ['!=', __('is not')]],
            'last_session' => [['>', __('more than … hours ago')], ['<', __('less than … hours ago')]],
            'first_session' => [['>', __('more than … hours ago')], ['<', __('less than … hours ago')]],
        ],
    ];
    $fields = ['tag' => __('Tag'), 'language' => __('Language'), 'country' => __('Country'), 'app_version' => __('App version'), 'session_count' => __('Sessions'), 'last_session' => __('Last session'), 'first_session' => __('First session'), 'external_id' => __('External id'), 'device_type' => __('Platform')];
@endphp
<x-layouts.app :title="($segment->exists ? $segment->name : __('New segment')).' · '.$project->name">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header :title="$segment->exists ? $segment->name : __('New segment')" :description="__('Devices matching every condition of a group; several groups widen the audience (or).')">
            <x-slot:breadcrumb>
                <a href="{{ route('projects.segments.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Segments') }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span>{{ $segment->exists ? __('Edit') : __('New') }}</span>
            </x-slot:breadcrumb>
        </x-ui.page-header>

        <form method="POST" action="{{ $segment->exists ? route('projects.segments.update', [$project, $segment]) : route('projects.segments.store', $project) }}" x-data="segmentBuilder(@js($config))" class="space-y-6">
            @csrf
            @if ($segment->exists) @method('PUT') @endif
            <input type="hidden" name="filters" :value="json">

            <x-ui.card>
                <x-ui.input name="name" :label="__('Name')" :value="$segment->name" required maxlength="100" :placeholder="__('e.g. VIP customers')" />
            </x-ui.card>

            <template x-for="(group, g) in groups" :key="g">
                <div>
                    <div class="my-3 flex items-center gap-3 text-xs font-semibold tracking-wide text-zinc-400 uppercase" x-show="g > 0"><span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>{{ __('or') }}<span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span></div>
                    <div class="card p-4 sm:p-5">
                        <template x-for="(condition, c) in group" :key="c">
                            <div>
                                <p class="my-2 text-xs font-semibold tracking-wide text-zinc-400 uppercase" x-show="c > 0">{{ __('and') }}</p>
                                <div class="grid gap-2 sm:grid-cols-[150px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <x-ui.dynamic-select :options="json_encode(collect($fields)->map(fn ($l, $v) => [$v, $l])->values())" model="condition.field" x-on:change="$nextTick(() => fieldChanged(condition))" />
                                    <input x-show="usesKey(condition.field)" x-model="condition.key" class="form-control font-mono text-sm" placeholder="{{ __('tag key') }}">
                                    <span x-show="!usesKey(condition.field)" class="hidden sm:block"></span>
                                    <x-ui.dynamic-select options="relations(condition.field)" model="condition.relation" />
                                    <input x-show="needsValue(condition)" x-model="condition.value" class="form-control" placeholder="{{ __('value') }}">
                                    <input x-show="usesHours(condition.field)" x-model="condition.hours_ago" type="number" min="0" step="any" class="form-control" placeholder="{{ __('hours') }}">
                                    <span x-show="!needsValue(condition) && !usesHours(condition.field)" class="hidden sm:block"></span>
                                    <x-ui.button type="button" variant="ghost" size="icon" icon="x" class="size-[42px] justify-self-end" x-on:click="group.splice(c, 1); if (!group.length) groups.splice(g, 1); if (!groups.length) groups.push([blank()])"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="mt-4 text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="group.push(blank())">+ {{ __('And…') }}</button>
                    </div>
                </div>
            </template>
            <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="groups.push([blank()])">+ {{ __('Or another group') }}</button>

            @error('filters')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Matching devices') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white"><span x-text="count === null ? '—' : new Intl.NumberFormat(document.documentElement.lang).format(count)"></span></p>
                    <p class="text-xs text-amber-700 dark:text-amber-400" x-show="error" x-text="error" x-cloak></p>
                </div>
                <div class="flex gap-3">
                    <x-ui.button variant="secondary" :href="route('projects.segments.index', $project)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="check">{{ $segment->exists ? __('Save') : __('Create the segment') }}</x-ui.button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
