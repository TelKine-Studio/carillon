@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $apns = $project->credential('apns')?->isUsable();
    $fcm = $project->credential('fcm')?->isUsable();
    $reasons = [
        'web' => __('web subscriptions (tied to the keys of OneSignal)'),
        'unsupported' => __('other channels (SMS, Amazon, Huawei)'),
        'no_token' => __('without push token'),
        'invalid_token' => __('invalid tokens'),
    ];
    $statuses = ['queued' => ['zinc', __('Waiting')], 'running' => ['blue', __('In progress')], 'done' => ['green', __('Done')], 'failed' => ['red', __('Failed')]];
@endphp
<x-layouts.app :title="__('Import from OneSignal').' · '.$project->name">
    <x-ui.page-header :title="__('Import from OneSignal')" :description="__('Bring your subscribers: they keep receiving notifications, without reinstalling your app.')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.subscribers.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Audience') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ __('Import') }}</span>
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('Import a file')" icon="upload">
                <ol class="mb-6 space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-white/5 dark:text-zinc-300">1</span>
                        <span>{!! __('In OneSignal, open :path and export the subscriptions. You receive a :file file by email.', ['path' => '<strong class="font-medium text-zinc-900 dark:text-white">Audience → Subscriptions → Export</strong>', 'file' => '<code class="font-mono text-xs">.csv.gz</code>']) !!}</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-white/5 dark:text-zinc-300">2</span>
                        <span>
                            {!! __('Add the same keys as in OneSignal in :settings: the Apple key of the same team and bundle id, the service account of the same Firebase project.', ['settings' => '<a href="'.route('projects.settings', [$project, 'platforms']).'" class="font-medium text-brand-700 hover:underline dark:text-brand-300">'.e(__('Platforms')).'</a>']) !!}
                            <span class="mt-2 flex flex-wrap gap-2">
                                <x-ui.badge :color="$apns ? 'green' : 'amber'" dot>Apple · {{ $apns ? __('ready') : __('no keys') }}</x-ui.badge>
                                <x-ui.badge :color="$fcm ? 'green' : 'amber'" dot>Firebase · {{ $fcm ? __('ready') : __('no keys') }}</x-ui.badge>
                            </span>
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-white/5 dark:text-zinc-300">3</span>
                        <span>{{ __('Send the file here. People keep their external id and tags; importing again updates them.') }}</span>
                    </li>
                </ol>

                <form method="POST" action="{{ route('projects.imports.store', $project) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ sending: false }" x-on:submit="sending = true">
                    @csrf
                    <x-ui.file name="file" accept=".csv,.gz,text/csv,application/gzip" :label="__('OneSignal export')" :placeholder="__('Choose the .csv or .csv.gz file')" :hint="__('100 MB maximum.')" required />
                    <x-ui.button icon="upload" ::disabled="sending">
                        <span x-text="sending ? @js(__('Sending…')) : @js(__('Import'))">{{ __('Import') }}</span>
                    </x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('Imports')" icon="refresh" :padding="false">
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($imports as $import)
                        <li class="px-5 py-4 sm:px-6"
                            @if ($import->running()) x-data="{ s: @js($import->only(['status', 'rows', 'created', 'updated', 'skipped'])), timer: null }"
                                x-init="timer = setInterval(async () => { s = await (await fetch(@js(route('projects.imports.show', [$project, $import])), { headers: { Accept: 'application/json' } })).json(); if (! ['queued', 'running'].includes(s.status)) { clearInterval(timer); location.reload() } }, 2000)" @endif>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <p class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $import->filename }}</p>
                                <x-ui.badge :color="$statuses[$import->status][0]" dot>{{ $statuses[$import->status][1] }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $import->created_at->diffForHumans() }}@if ($import->user) · {{ $import->user->name }}@endif
                            </p>
                            @if ($import->running())
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <span x-text="new Intl.NumberFormat().format(s.rows)">{{ $n($import->rows) }}</span> {{ __('rows read') }}…
                                </p>
                            @else
                                <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    @foreach (['rows' => __('Rows'), 'created' => __('New devices'), 'updated' => __('Updated'), 'skipped' => __('Skipped')] as $key => $label)
                                        <div>
                                            <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                                            <dd class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $n($import->{$key}) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                @if ($import->notes)
                                    <p class="mt-3 text-xs text-zinc-500">{{ __('Skipped:') }} {{ collect($import->notes)->map(fn ($count, $reason) => $n($count).' '.($reasons[$reason] ?? $reason))->implode(' · ') }}</p>
                                @endif
                                @if ($import->error)
                                    <p class="mt-3 flex items-start gap-2 text-sm text-red-700 dark:text-red-400"><x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $import->error }}</p>
                                @endif
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty icon="upload" :title="__('No import yet')" :description="__('The result of each import shows here.')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <aside class="space-y-4">
            <x-ui.card :title="__('Good to know')" icon="alert">
                <ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>{{ __('iOS and Android devices keep working right away: their tokens are sent with your own keys.') }}</li>
                    <li>{{ __('Web subscriptions cannot be moved (they belong to the keys of OneSignal): the web SDK subscribes the visitors again on their next visit.') }}</li>
                    <li>{{ __('When the app with the Carillon SDK opens, the device finds its imported profile: nothing is duplicated.') }}</li>
                    <li>{{ __('Keep OneSignal until the new version of your app is published, then switch your server to the Carillon API.') }}</li>
                </ul>
            </x-ui.card>
        </aside>
    </div>
</x-layouts.app>
