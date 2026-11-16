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
        <div class="card">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($journeys as $journey)
                    @php
                        [$color, $label] = $statusBadge($journey->status);
                        $byStatus = collect($counts[$journey->id] ?? [])->pluck('total', 'status');
                    @endphp
                    <li>
                        <a href="{{ route('projects.journeys.show', [$project, $journey]) }}" class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4 transition first:rounded-t-2xl last:rounded-b-2xl hover:bg-zinc-50/80 sm:px-6 dark:hover:bg-white/[0.02]">
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><x-ui.icon name="zap" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="truncate font-semibold text-zinc-900 dark:text-white">{{ $journey->name }}</span>
                                    <x-ui.badge :color="$color" :dot="$journey->status === 'active'">{{ $label }}</x-ui.badge>
                                </span>
                                <span class="mt-0.5 block truncate text-sm text-zinc-500">{{ $journey->triggerLabel() }} · {{ trans_choice(':count step|:count steps', count($journey->steps ?? [])) }}</span>
                            </span>
                            <span class="grid grid-cols-3 gap-6 text-center">
                                @foreach ([[__('In progress'), $byStatus['active'] ?? 0], [__('Completed'), $byStatus['completed'] ?? 0], [__('Entered'), $byStatus->sum()]] as [$what, $count])
                                    <span>
                                        <span class="block text-base font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $n($count) }}</span>
                                        <span class="block text-xs text-zinc-500">{{ $what }}</span>
                                    </span>
                                @endforeach
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts.app>
