@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $statusBadge = fn (string $status) => match ($status) {
        'active' => ['green', __('Active')],
        'paused' => ['amber', __('Paused')],
        default => ['zinc', __('Draft')],
    };
@endphp
<x-layouts.app :title="__('Journeys').' · '.$project->name">
    <x-ui.page-header :title="__('Journeys')" :description="__('Automatic messages: a trigger, then steps that each person goes through at their own pace.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.journeys.create', $project)" icon="plus">{{ __('New journey') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    @if ($journeys->isEmpty())
        <div class="stagger grid gap-4 md:grid-cols-3">
            @foreach ([
                'welcome' => ['sparkles', __('Welcome'), __('A message a few minutes after the app is installed, then a tip two days later.')],
                'winback' => ['refresh', __('Win back inactive people'), __('After 7 days without opening the app, a reminder, then a second one 3 days later.')],
                '' => ['plus', __('From scratch'), __('Choose the trigger and the steps yourself.')],
            ] as $recipe => [$icon, $title, $text])
                <a href="{{ route('projects.journeys.create', [$project, 'recipe' => $recipe ?: null]) }}" class="card card-hover group p-5">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:scale-110 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$icon" class="size-5" /></span>
                    <p class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ $title }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</p>
                </a>
            @endforeach
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium sm:px-5">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-3 py-3 text-right font-medium whitespace-nowrap">{{ __('In progress') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Completed') }}</th>
                            <th class="px-4 py-3 text-right font-medium sm:px-5">{{ __('Entered') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($journeys as $journey)
                            @php
                                [$color, $label] = $statusBadge($journey->status);
                                $dot = ['green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'zinc' => 'bg-zinc-400'][$color];
                                $byStatus = collect($counts[$journey->id] ?? [])->pluck('total', 'status');
                            @endphp
                            <tr class="relative transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="max-w-0 px-4 py-3.5 sm:px-5" style="width: 50%">
                                    <a href="{{ route('projects.journeys.show', [$project, $journey]) }}" class="block truncate font-medium text-zinc-900 after:absolute after:inset-0 dark:text-white">{{ $journey->name }}</a>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $journey->triggerLabel() }} · {{ trans_choice(':count step|:count steps', count($journey->steps ?? [])) }}</p>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap"><span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300"><span @class(['size-2 rounded-full', $dot, 'animate-pulse' => $journey->status === 'active'])></span>{{ $label }}</span></td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($byStatus['active'] ?? 0) }}</td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($byStatus['completed'] ?? 0) }}</td>
                                <td class="px-4 py-3.5 text-right font-medium text-zinc-900 tabular-nums sm:px-5 dark:text-white">{{ $n($byStatus->sum()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
