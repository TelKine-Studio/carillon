@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $rate = fn ($m) => $m && $m->successful > 0 ? $m->clicked / $m->successful * 100 : null;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', ' ').' %';
    $leader = $test->leader();
    $winner = $test->messages->firstWhere('ab_variant', 'W');
    $title = $test->name ?: __('A/B test');
@endphp
<x-layouts.app :title="$title.' · '.$project->name">
    <x-ui.page-header :title="$title" :description="__('Sample of :percent % of the audience, half for each version. :count devices wait for the winner.', ['percent' => $test->sample, 'count' => $n(count($test->remaining))])">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.messages.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Messages') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ __('A/B test') }}</span>
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if ($winner)
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">
            <x-ui.icon name="check-circle" class="size-4 shrink-0" />
            <span class="flex-1">{{ __('Version :letter was sent to the rest of the audience.', ['letter' => $test->messages->firstWhere('id', $test->winner_id)?->ab_variant]) }}</span>
            <a href="{{ route('projects.messages.show', [$project, $winner]) }}" class="font-medium underline">{{ __('See the report') }}</a>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        @foreach (['A', 'B'] as $letter)
            @php($message = $test->variant($letter))
            @continue(! $message)
            @php($isLeader = $leader?->is($message))
            <section @class(['card p-5 sm:p-6', 'ring-2 ring-emerald-500/60' => $isLeader])>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span @class(['inline-flex size-9 items-center justify-center rounded-xl text-sm font-bold', 'bg-emerald-600 text-white' => $isLeader, 'bg-zinc-100 text-zinc-700 dark:bg-white/5 dark:text-zinc-200' => ! $isLeader])>{{ $letter }}</span>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Version :letter', ['letter' => $letter]) }}</p>
                            <x-messages.status :status="$message->status" />
                        </div>
                    </div>
                    @if ($isLeader)<x-ui.badge color="green" icon="sparkles">{{ __('Leading') }}</x-ui.badge>@endif
                </div>

                <div class="mt-5 rounded-xl border border-zinc-100 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-white/[0.02]">
                    @if ($heading = App\Models\Message::localized($message->headings, null))
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $heading }}</p>
                    @endif
                    <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">{{ App\Models\Message::localized($message->contents, null) }}</p>
                </div>

                <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                    @foreach ([[__('Delivered'), $n($message->successful)], [__('Clicks'), $n($message->clicked)], [__('Click rate'), $pct($rate($message))]] as [$label, $value])
                        <div>
                            <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <x-ui.button variant="ghost" size="sm" :href="route('projects.messages.show', [$project, $message])" icon="arrow-right">{{ __('Report') }}</x-ui.button>
                    @can('send', $project)
                        @if (! $test->winner_id && count($test->remaining))
                            <form method="POST" action="{{ route('projects.ab-tests.winner', [$project, $test]) }}" class="ml-auto"
                                data-confirm-title="{{ __('Send version :letter to the rest?', ['letter' => $letter]) }}" data-confirm="{{ trans_choice(':count device will receive it.|:count devices will receive it.', count($test->remaining), ['count' => $n(count($test->remaining))]) }}" data-confirm-action="{{ __('Send') }}">
                                @csrf
                                <input type="hidden" name="variant" value="{{ $letter }}">
                                <x-ui.button size="sm" :variant="$isLeader ? 'primary' : 'secondary'" icon="send">{{ __('Send :letter to the rest', ['letter' => $letter]) }}</x-ui.button>
                            </form>
                        @endif
                    @endcan
                </div>
            </section>
        @endforeach
    </div>

    @unless ($test->winner_id)
        <p class="mt-6 flex items-start gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            {{ __('Clicks keep arriving for a few hours: wait until the difference is clear before choosing. With few devices, a small difference may be chance.') }}
        </p>
    @endunless
</x-layouts.app>
