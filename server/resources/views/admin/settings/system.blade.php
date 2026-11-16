@php
    $checks = $health['checks'];
    $bytes = fn ($value) => $value === null ? '—' : number_format($value / 1024 ** 3, 1, ',', ' ').' Go';
    $rows = [
        'database' => [__('Database'), 'layers', strtoupper((string) $checks['database']['driver']), $checks['database']['error'] ?? null],
        'queue' => [__('Sending queue'), 'send', __(':pending waiting · :failed failed', ['pending' => $checks['queue']['pending'] ?? '—', 'failed' => $checks['queue']['failed'] ?? '—']), $checks['queue']['ok'] ? null : __('Jobs have been waiting for more than 5 minutes: is the queue worker running?')],
        'scheduler' => [__('Scheduler'), 'clock', $checks['scheduler']['last_run'] ? __('Last run :date', ['date' => \Carbon\Carbon::parse($checks['scheduler']['last_run'])->diffForHumans()]) : __('Never seen'), $checks['scheduler']['ok'] ? null : __('Scheduled messages, journeys and syncs need "php artisan schedule:work" (included in the Docker image).')],
        'storage' => [__('Disk'), 'file', __(':free free of :total', ['free' => $bytes($checks['storage']['free']), 'total' => $bytes($checks['storage']['total'])]), $checks['storage']['ok'] ? null : __('Less than 500 MB left.')],
        'mail' => [__('Outgoing mail'), 'mail', strtoupper((string) $checks['mail']['driver']), $checks['mail']['ok'] ? null : __('Emails (invitations, reports) are written to the logs: configure a mail server in Email.')],
    ];
@endphp
<x-layouts.app :title="__('Settings')">
    @include('admin.settings.partials.nav')
    <x-settings.layout :section="$section">
        <x-ui.card :title="__('Health of the instance')" :description="__('Carillon :version · PHP :php', ['version' => $health['version'], 'php' => $health['php']])" icon="activity" :padding="false">
            <x-slot:actions>
                @if ($health['ok'])
                    <x-ui.badge color="green" dot>{{ __('Everything works') }}</x-ui.badge>
                @else
                    <x-ui.badge color="amber" dot>{{ __('Needs attention') }}</x-ui.badge>
                @endif
            </x-slot:actions>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($rows as $key => [$label, $icon, $value, $problem])
                    <li class="flex items-start gap-4 px-5 py-4 sm:px-6">
                        <span @class(['inline-flex size-9 shrink-0 items-center justify-center rounded-xl', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $checks[$key]['ok'], 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' => ! $checks[$key]['ok']])><x-ui.icon :name="$icon" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $value }}</p>
                            @if ($problem)<p class="mt-1 text-sm text-amber-700 dark:text-amber-400">{{ $problem }}</p>@endif
                        </div>
                        <x-ui.icon :name="$checks[$key]['ok'] ? 'check-circle' : 'alert'" @class(['mt-1 size-5 shrink-0', 'text-emerald-500' => $checks[$key]['ok'], 'text-amber-500' => ! $checks[$key]['ok']]) />
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
        <p class="mt-4 text-xs text-zinc-500">{{ __('For an external monitoring tool: GET /up answers 200 when the application runs.') }}</p>
    </x-settings.layout>
</x-layouts.app>
