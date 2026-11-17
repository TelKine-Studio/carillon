@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $entered = (int) $totals->sum();
    [$color, $label] = match ($journey->status) {
        'active' => ['green', __('Active')],
        'paused' => ['amber', __('Paused')],
        default => ['zinc', __('Draft')],
    };
    $units = ['minutes' => [':count minute|:count minutes'], 'hours' => [':count hour|:count hours'], 'days' => [':count day|:count days']];
    $icons = ['push' => 'bell', 'email' => 'mail', 'sms' => 'message', 'whatsapp' => 'chat', 'in_app' => 'image', 'wait' => 'clock', 'wait_event' => 'activity', 'condition' => 'sliders', 'split' => 'columns', 'exit' => 'check-circle'];
    $tiles = ['push' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300', 'email' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300', 'sms' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300', 'whatsapp' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-300', 'in_app' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300', 'wait' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', 'wait_event' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'];
@endphp
<x-layouts.app :title="$journey->name.' · '.$project->name">
    <x-ui.page-header :title="$journey->name">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.journeys.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Journeys') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ $journey->name }}</span>
        </x-slot:breadcrumb>
        @can('send', $project)
            <x-ui.button variant="secondary" :href="route('projects.journeys.edit', [$project, $journey])" icon="pencil">{{ __('Edit') }}</x-ui.button>
            <form method="POST" action="{{ route('projects.journeys.status', [$project, $journey]) }}">
                @csrf
                @method('PUT')
                @if ($journey->isActive())
                    <input type="hidden" name="status" value="paused">
                    <x-ui.button variant="secondary" icon="pause">{{ __('Pause') }}</x-ui.button>
                @else
                    <input type="hidden" name="status" value="active">
                    <x-ui.button icon="play">{{ __('Activate') }}</x-ui.button>
                @endif
            </form>
        @endcan
    </x-ui.page-header>

    <div class="-mt-3 mb-6 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
        <x-ui.badge :color="$color" :dot="$journey->isActive()">{{ $label }}</x-ui.badge>
        <span>{{ $journey->triggerLabel() }}</span>
        @if ($journey->segment)<span>· {{ __('Segment: :name', ['name' => __($journey->segment)]) }}</span>@endif
        @if ($journey->reentry)<span>· {{ __('Can enter again') }}</span>@endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Entered')" :value="$n($entered)" icon="users" />
        <x-ui.stat :label="__('In progress')" :value="$n($totals['active'] ?? 0)" icon="clock" />
        <x-ui.stat :label="__('Completed')" :value="$n($totals['completed'] ?? 0)" icon="check-circle" />
        <x-ui.stat :label="__('Left at a condition')" :value="$n($totals['exited'] ?? 0)" icon="sliders" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <section>
            <h2 class="mb-3 text-[15px] font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Steps') }}</h2>
            <ol>
                <li class="card flex items-center gap-3 p-4">
                    <span class="inline-flex size-9 items-center justify-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="zap" class="size-4" /></span>
                    <span class="flex-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $journey->triggerLabel() }}</span>
                    <span class="text-sm tabular-nums text-zinc-500">{{ trans_choice(':count person|:count people', $entered, ['count' => $n($entered)]) }}</span>
                </li>
                @foreach ($journey->steps as $index => $step)
                    <li class="mx-auto h-5 w-px bg-zinc-200 dark:bg-zinc-800"></li>
                    <li class="card p-4">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tiles[$step['type']] ?? 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300' }}"><x-ui.icon :name="$icons[$step['type']] ?? 'circle-dot'" class="size-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    @switch($step['type'])
                                        @case('wait') {{ __('Wait :duration', ['duration' => trans_choice($units[$step['unit'] ?? 'hours'][0], $step['amount'] ?? 1, ['count' => $step['amount'] ?? 1])]) }} @break
                                        @case('condition')
                                            @php($not = ($step['operator'] ?? 'is') === 'is_not')
                                            @switch($step['check'] ?? 'tag')
                                                @case('clicked') {{ $not ? __('Continue if the person did not click') : __('Continue if the person clicked a message of the journey') }} @break
                                                @case('event') {{ __($not ? 'Continue if the person did not do :event' : 'Continue if the person did :event', ['event' => $step['event'] ?? '']) }} @break
                                                @default {{ __('Continue if :key :operator :value', ['key' => $step['key'] ?? '', 'operator' => $not ? __('is not') : __('is'), 'value' => ($step['value'] ?? '') !== '' ? $step['value'] : __('set')]) }}
                                            @endswitch
                                            <span class="block text-xs font-normal text-zinc-500">{{ ($step['otherwise'] ?? 'exit') === 'goto' && isset($step['else_step']) ? __('Otherwise: go to step :number', ['number' => $step['else_step'] + 1]) : __('Otherwise: leave the journey') }}</span>
                                            @break
                                        @case('wait_event') {{ __('Wait for :event (at most :duration)', ['event' => $step['event'] ?? '', 'duration' => trans_choice($units[$step['unit'] ?? 'days'][0], $step['amount'] ?? 1, ['count' => $step['amount'] ?? 1])]) }} @break
                                        @case('email') {{ __('Email') }} · {{ $step['subject'] ?? '' }} @break
                                        @case('sms') SMS @break
                                        @case('whatsapp') WhatsApp @if (! empty($step['whatsapp_template']))· {{ $step['whatsapp_template'] }}@endif @break
                                        @case('in_app') {{ __('In-app message') }} · {{ $inApps[$step['in_app_message_id'] ?? ''] ?? '—' }} @break
                                        @case('exit') {{ __('End of the journey') }} @break
                                        @case('split')
                                            {{ __('A/B split') }}
                                            <span class="mt-1.5 flex flex-wrap gap-2 text-xs font-normal">
                                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">A · {{ (int) ($step['percent'] ?? 50) }} % → {{ __('next step') }}</span>
                                                <span class="rounded-md bg-zinc-100 px-2 py-0.5 font-medium text-zinc-700 dark:bg-white/5 dark:text-zinc-300">B · {{ 100 - (int) ($step['percent'] ?? 50) }} % → {{ ($step['b_target'] ?? 'goto') === 'goto' && isset($step['b_step']) ? __('step :number', ['number' => $step['b_step'] + 1]) : __('leave the journey') }}</span>
                                            </span>
                                            @break
                                        @default {{ __('Notification') }}@if (! empty($step['title'])) · {{ $step['title'] }}@endif
                                    @endswitch
                                </p>
                                @if ($step['type'] === 'in_app')
                                    <p class="mt-2 text-xs text-zinc-500"><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($inAppGiven[$step['in_app_message_id'] ?? ''] ?? 0) }}</strong> {{ __('users received it for their next opening') }}</p>
                                @endif
                                @if (in_array($step['type'], ['push', 'email', 'sms', 'whatsapp'], true))
                                    <p class="mt-0.5 line-clamp-2 text-sm text-zinc-500">{{ ! empty($step['template_id']) ? __('From a template') : strip_tags($step['body'] ?? ($step['whatsapp_template'] ?? '')) }}</p>
                                    @php($stats = $sent[$index] ?? null)
                                    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($stats->delivered ?? 0) }}</strong> {{ __('delivered') }}</span>
                                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($stats->clicked ?? 0) }}</strong> {{ __('clicks') }}</span>
                                    </p>
                                @endif
                            </div>
                            @if ($step['type'] === 'wait' && ($waiting[$index + 1] ?? 0))
                                <x-ui.badge color="blue">{{ trans_choice(':count waiting|:count waiting', $waiting[$index + 1], ['count' => $n($waiting[$index + 1])]) }}</x-ui.badge>
                            @endif
                        </div>
                    </li>
                @endforeach
                <li class="mx-auto h-5 w-px bg-zinc-200 dark:bg-zinc-800"></li>
                <li class="flex items-center justify-center gap-2 text-sm text-zinc-500"><x-ui.icon name="check-circle" class="size-4" />{{ __('End: :count completed', ['count' => $n($totals['completed'] ?? 0)]) }}</li>
            </ol>
        </section>

        <aside class="space-y-6">
            <x-ui.card :title="__('Latest entries')" icon="users" :padding="false">
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recent as $entry)
                        <li>
                            <a href="{{ route('projects.subscribers.show', [$project, $entry->subscriber_id]) }}" class="flex items-center gap-3 px-5 py-3 text-sm transition hover:bg-zinc-50/80 dark:hover:bg-white/[0.02]">
                                <span class="min-w-0 flex-1 font-mono text-xs break-all text-zinc-700 dark:text-zinc-300">{{ $entry->subscriber?->external_id ?: $entry->subscriber_id }}</span>
                                <span class="text-xs text-zinc-500">{{ $entry->created_at->diffForHumans() }}</span>
                                <x-ui.badge :color="['active' => 'blue', 'completed' => 'green'][$entry->status] ?? 'zinc'">{{ ['active' => __('In progress'), 'completed' => __('Completed'), 'exited' => __('Left')][$entry->status] ?? $entry->status }}</x-ui.badge>
                            </a>
                        </li>
                    @empty
                        <li><x-ui.empty icon="users" :title="__('Nobody yet')" :description="$journey->isActive() ? __('People enter when the trigger happens.') : __('Activate the journey to let people in.')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
            @can('send', $project)
                <form method="POST" action="{{ route('projects.journeys.destroy', [$project, $journey]) }}" data-confirm-title="{{ __('Delete this journey?') }}" data-confirm="{{ __('People in progress leave it. Messages already sent stay in the statistics.') }}" data-confirm-action="{{ __('Delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="ghost-danger" size="sm" icon="trash">{{ __('Delete the journey') }}</x-ui.button>
                </form>
            @endcan
        </aside>
    </div>
</x-layouts.app>
