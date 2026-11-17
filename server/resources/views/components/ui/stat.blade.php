@props(['label', 'value', 'icon', 'hint' => null, 'series' => null, 'href' => null, 'delta' => null, 'deltaLabel' => null])
@php
    $tag = $href ? 'a' : 'div';
    $points = null;
    if ($series && count($series) > 1) {
        $max = max(max($series), 1);
        $step = 100 / (count($series) - 1);
        $coords = collect($series)->values()->map(fn ($v, $i) => round($i * $step, 2).','.round(28 - ($v / $max) * 24, 2));
        $points = $coords->implode(' ');
        $area = '0,30 '.$points.' 100,30';
    }
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card relative overflow-hidden p-5 '.($href ? 'card-hover' : '')]) }}>
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
            <x-ui.icon :name="$icon" class="size-4" />
        </span>
    </div>
    <p data-count class="mt-3 origin-left text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">{{ $value }}</p>
    @if ($delta !== null)
        {{-- Trend against the previous period, as OneSignal: green up, red down. --}}
        <p class="mt-1 flex items-center gap-1 text-xs">
            <span @class(['inline-flex items-center gap-0.5 font-medium tabular-nums', 'text-emerald-600 dark:text-emerald-400' => $delta >= 0, 'text-red-600 dark:text-red-400' => $delta < 0])>
                <svg viewBox="0 0 12 12" class="size-3" aria-hidden="true"><path d="{{ $delta >= 0 ? 'M2 9l3.5-3.5 2 2L10 4M7 4h3v3' : 'M2 3l3.5 3.5 2-2L10 8M7 8h3V5' }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ ($delta >= 0 ? '+' : '').number_format($delta, 1, ',', ' ') }} %
            </span>
            <span class="text-zinc-500 dark:text-zinc-400">{{ $deltaLabel ?? __('vs previous 30 days') }}</span>
        </p>
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
    @endif
    @if ($points)
        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-3 h-10 w-full text-brand-500" aria-hidden="true">
            <defs><linearGradient id="spark-{{ md5($label) }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".25"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
            <polygon points="{{ $area }}" fill="url(#spark-{{ md5($label) }})" />
            <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
        </svg>
    @endif
</{{ $tag }}>
