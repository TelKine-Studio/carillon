@props(['series' => [], 'label', 'height' => 'h-40', 'empty' => null, 'tickFormat' => 'D MMM', 'tooltip' => null])
@php
    // Several daily series as 2px lines on a recessive grid, a legend, and a
    // tooltip listing every series for the day under the cursor.
    // $series: [['label' => …, 'color' => '#…', 'points' => [['date' => Carbon, 'value' => int], …]], …]
    $series = array_values(array_filter($series, fn ($s) => ! empty($s['points'])));
    $all = collect($series)->flatMap(fn ($s) => array_column($s['points'], 'value'))->all();
    $max = max(1, ...($all ?: [0]));
    $magnitude = 10 ** floor(log10($max));
    $scale = max(2, collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $max));
    $count = count($series[0]['points'] ?? []);
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $tooltip ??= $count > 31 ? 'D MMM' : 'ddd D MMM';
    $x = fn (int $i) => $count > 1 ? round($i / ($count - 1) * 100, 3) : 50;
    $y = fn ($v) => round(100 - ($v / $scale) * 100, 3);
    $days = collect($series[0]['points'] ?? [])->map(fn ($p, $i) => [
        'd' => $p['date']->isoFormat($tooltip),
        'v' => collect($series)->map(fn ($s) => $n($s['points'][$i]['value'] ?? 0))->all(),
    ])->all();
    $ticks = $count > 1 ? array_unique([0, intdiv($count - 1, 2), $count - 1]) : [0];
@endphp
<figure {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="{ active: null, days: @js($days) }" x-on:mouseleave="active = null">
    <figcaption class="sr-only">{{ $label }}</figcaption>
    @if (array_sum($all) === 0 && $empty)
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
            <div @class(['relative', $height])>
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">
                    @foreach ($series as $s)
                        <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"
                            points="{{ collect($s['points'])->map(fn ($p, $i) => $x($i).','.$y($p['value']))->implode(' ') }}" />
                    @endforeach
                </svg>
                {{-- Hover columns: the whole height of the day, wider than the line --}}
                <div class="absolute inset-0 flex">
                    @foreach (range(0, max(0, $count - 1)) as $i)
                        <div class="relative h-full flex-1" x-on:mouseenter="active = {{ $i }}">
                            <span x-show="active === {{ $i }}" x-cloak class="pointer-events-none absolute inset-y-0 left-1/2 w-px bg-zinc-300 dark:bg-zinc-600"></span>
                        </div>
                    @endforeach
                </div>
                <template x-if="active !== null">
                    <div class="pointer-events-none absolute -top-2 z-10 -translate-y-full rounded-lg bg-zinc-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg dark:bg-zinc-700"
                        :style="`left: clamp(0%, ${(active + 0.5) / {{ max(1, $count) }} * 100}%, 100%); transform: translate(-50%, -100%)`">
                        <p class="mb-0.5 font-medium" x-text="days[active].d"></p>
                        @foreach ($series as $k => $s)
                            <p class="flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }} <span class="ml-auto pl-3 font-medium tabular-nums" x-text="days[active].v[{{ $k }}]"></span></p>
                        @endforeach
                    </div>
                </template>
            </div>
            <div class="relative mt-2 flex justify-between text-[10px] text-zinc-400">
                @foreach ($ticks as $i)
                    <span>{{ ($series[0]['points'][$i]['date'] ?? null)?->isoFormat($tickFormat) }}</span>
                @endforeach
            </div>
        </div>
        @if (count($series) > 1)
            <div class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                @foreach ($series as $s)
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-3 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span>
                @endforeach
            </div>
        @endif
    @endif
</figure>
