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

    @php
        // Quick starts, by goal (OneSignal "quickstart Journeys").
        $recipes = [
            'onboarding' => [__('Onboarding'), [
                'welcome' => ['sparkles', __('Welcome'), __('A message a few minutes after the app is installed, then a tip two days later.')],
                'welcome_ab' => ['columns', __('Welcome, A/B test'), __('Two welcome messages, half of the new users each: keep the one that gets more clicks.')],
            ]],
            'promotional' => [__('Promotional'), [
                'abandoned' => ['zap', __('Abandoned cart'), __('An item added without purchase: a reminder an hour later, then a little extra the next day.')],
                'post_purchase' => ['check-circle', __('After a purchase'), __('A thank you right away, then a suggestion three days later.')],
            ]],
            're-engagement' => [__('Re-engagement'), [
                'winback' => ['refresh', __('Win back inactive people'), __('After 7 days without opening the app, a reminder, then a second one 3 days later.')],
                'winback_ab' => ['columns', __('Win back, A/B test'), __('After 14 days away, two different messages to see which brings people back.')],
            ]],
        ];
    @endphp
    @can('send', $project)
        @if ($journeys->isEmpty())
            {{-- Introduction, as OneSignal, with a small flow drawn on the right. --}}
            <section class="card mb-6 grid gap-8 overflow-hidden p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center">
                <div>
                    <x-ui.badge color="brand">{{ __('Journeys') }}</x-ui.badge>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Turn one-time campaigns into always-on journeys') }}</h2>
                    <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ __('Welcome series, win-back, after a purchase: the campaigns you already send, automated across push, email, SMS, WhatsApp and in-app.') }}</p>
                    <ul class="mt-4 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                        @foreach ([__('Every journey reacts to what users do: opening, purchases, going quiet.'), __('Launch once, it runs on its own while you work on what is next.'), __('A/B test messages, timing and channels to see what converts.')] as $point)
                            <li class="flex gap-2"><x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" />{{ $point }}</li>
                        @endforeach
                    </ul>
                    <x-ui.button class="mt-6" :href="route('projects.journeys.create', $project)" icon="plus">{{ __('New journey') }}</x-ui.button>
                </div>
                <div class="hidden rounded-lg bg-[radial-gradient(circle,var(--color-zinc-200)_1px,transparent_1px)] [background-size:14px_14px] p-5 lg:block dark:bg-[radial-gradient(circle,var(--color-zinc-800)_1px,transparent_1px)]" aria-hidden="true">
                    <div class="mx-auto w-fit rounded-full bg-brand-600 px-3 py-1 text-xs font-medium text-white">{{ __('A device subscribes') }}</div>
                    <div class="mx-auto h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                    <div class="card mx-auto flex w-56 items-center gap-2 p-2.5 text-xs font-medium"><span class="inline-flex size-6 items-center justify-center rounded-md bg-zinc-100 dark:bg-white/5"><x-ui.icon name="columns" class="size-3.5" /></span>{{ __('A/B split') }} · 50/50</div>
                    <div class="mx-auto h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="card flex items-center gap-2 p-2.5 text-xs font-medium"><span class="inline-flex size-6 items-center justify-center rounded-md bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon name="bell" class="size-3.5" /></span>{{ __('Notification') }}</div>
                        <div class="card flex items-center gap-2 p-2.5 text-xs font-medium"><span class="inline-flex size-6 items-center justify-center rounded-md bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-300"><x-ui.icon name="chat" class="size-3.5" /></span>WhatsApp</div>
                    </div>
                    <div class="mx-auto h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                    <div class="card mx-auto flex w-56 items-center gap-2 p-2.5 text-xs font-medium"><span class="inline-flex size-6 items-center justify-center rounded-md bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"><x-ui.icon name="clock" class="size-3.5" /></span>{{ __('Wait') }} · 2 {{ __('days') }}</div>
                    <div class="mx-auto h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                    <div class="card mx-auto flex w-56 items-center gap-2 p-2.5 text-xs font-medium"><span class="inline-flex size-6 items-center justify-center rounded-md bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300"><x-ui.icon name="mail" class="size-3.5" /></span>{{ __('Email') }}</div>
                </div>
            </section>
        @endif

        <section x-data="{ open: {{ $journeys->isEmpty() ? 'true' : 'false' }}, goal: 'onboarding' }" class="mb-6">
            <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200">
                <x-ui.icon name="chevron-right" class="size-4 transition" ::class="open && 'rotate-90'" />{{ __('Or start from a quick journey') }}
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-3">
                <nav class="scrollbar-none mb-4 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800">
                    @foreach ($recipes as $goal => [$goalLabel])
                        <button type="button" x-on:click="goal = '{{ $goal }}'" class="-mb-px inline-flex shrink-0 items-center border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition"
                            :class="goal === '{{ $goal }}' ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">{{ $goalLabel }}</button>
                    @endforeach
                </nav>
                @foreach ($recipes as $goal => [, $items])
                    <div x-show="goal === '{{ $goal }}'" class="grid gap-4 md:grid-cols-3">
                        @foreach ($items as $recipe => [$icon, $title, $text])
                            <a href="{{ route('projects.journeys.create', [$project, 'recipe' => $recipe]) }}" class="card card-hover group flex flex-col p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $title }}</p>
                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$icon" class="size-5" /></span>
                                </div>
                                <p class="mt-1 flex-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</p>
                                <span class="mt-4 inline-flex h-7 w-fit items-center gap-1.5 rounded-md border border-zinc-300 px-2.5 text-[13px] font-medium text-zinc-800 transition-colors group-hover:border-zinc-400 group-hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:group-hover:bg-zinc-800"><x-ui.icon name="plus" class="size-3.5" />{{ __('Use') }}</span>
                            </a>
                        @endforeach
                        <a href="{{ route('projects.journeys.create', $project) }}" class="card card-hover group flex flex-col items-start p-5">
                            <span class="inline-flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><x-ui.icon name="plus" class="size-5" /></span>
                            <p class="mt-3 font-semibold text-zinc-900 dark:text-white">{{ __('From scratch') }}</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose the trigger and the steps yourself.') }}</p>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endcan

    @if ($journeys->isNotEmpty())
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
                                    <a href="{{ route('projects.journeys.show', [$project, $journey]) }}" class="block font-medium break-words text-zinc-900 after:absolute after:inset-0 dark:text-white">{{ $journey->name }}</a>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $journey->triggerLabel() }} · {{ trans_choice(':count step|:count steps', count($journey->steps ?? [])) }}</p>
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
