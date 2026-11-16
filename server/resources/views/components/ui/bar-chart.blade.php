@props(['points' => [], 'label', 'height' => 'h-40', 'empty' => null])
@php
    // One series of daily values: bars from the baseline, a recessive grid,
    // the maximum on the axis and a tooltip on hover.
    $values = array_column($points, 'value');
    $max = max(1, ...($values ?: [0]));
    // Round top of the axis: 1, 2 or 5 times a power of ten (at least 2, so the middle line is a whole number).
    $magnitude = 10 ** floor(log10($max));
    $scale = max(2, collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $max));
    $count = count($points);
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $data = array_map(fn ($p) => ['d' => $p['date']->isoFormat($count > 31 ? 'D MMM' : 'ddd D MMM'), 'v' => $n($p['value'])], $points);
    $ticks = $count > 1 ? array_unique([0, intdiv($count - 1, 2), $count - 1]) : [0];
@endphp
<figure {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="{ active: null, points: @js($data) }" x-on:mouseleave="active = null">
    <figcaption class="sr-only">{{ $label }}</figcaption>
    @if (array_sum($values) === 0 && $empty)
        <div @class(['flex items-center justify-center rounded-xl border border-dashed border-zinc-200 text-sm text-zinc-400 dark:border-zinc-800', $height])>{{ $empty }}</div>
    @else
        <div class="relative pl-9">
            {{-- Grid and axis --}}
            <div @class(['pointer-events-none absolute inset-y-0 right-0 left-9 flex flex-col justify-between', $height])>
                @foreach ([$scale, $scale / 2, 0] as $tick)
                    <div class="relative border-t border-zinc-100 dark:border-zinc-800/80">
                        <span class="absolute -top-2 -left-9 w-7 text-right text-[10px] text-zinc-400 tabular-nums">{{ $tick >= 1000 ? round($tick / 1000, 1).'k' : $n($tick) }}</span>
                    </div>
                @endforeach
            </div>
            {{-- Bars: the hit area is the whole column, wider than the bar --}}
            <div @class(['relative flex items-end', $height, 'gap-[2px]' => $count <= 45, 'gap-px' => $count > 45])>
                @foreach ($points as $i => $point)
                    <div class="group flex h-full min-w-0 flex-1 items-end" x-on:mouseenter="active = {{ $i }}">
                        <div class="w-full rounded-t-[4px] bg-brand-500/85 transition-colors dark:bg-brand-400/85"
                            :class="active === {{ $i }} ? '!bg-brand-600 dark:!bg-brand-300' : ''"
                            style="height: {{ $point['value'] > 0 ? max(2, $point['value'] / $scale * 100) : 0 }}%"></div>
                    </div>
                @endforeach
            </div>
            {{-- Tooltip --}}
            <div x-show="active !== null" x-cloak class="pointer-events-none absolute -top-2 z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-zinc-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg dark:bg-white dark:text-zinc-900"
                :style="active !== null && `left: calc(2.25rem + (100% - 2.25rem) * ${(active + 0.5) / points.length})`">
                <span class="font-semibold tabular-nums" x-text="active !== null && points[active].v"></span>
                <span class="opacity-70" x-text="active !== null && points[active].d"></span>
            </div>
        </div>
        <div class="relative mt-2 ml-9 h-4 text-[10px] text-zinc-400">
            @foreach ($ticks as $tick)
                <span @class(['absolute', '-translate-x-1/2' => $tick !== 0 && $tick !== $count - 1, 'right-0' => $tick === $count - 1 && $count > 1])
                    @if ($tick !== $count - 1 || $count === 1) style="left: {{ $tick === 0 ? 0 : ($tick + 0.5) / $count * 100 }}%" @endif>{{ $points[$tick]['date']->isoFormat('D MMM') }}</span>
            @endforeach
        </div>
    @endif
</figure>
