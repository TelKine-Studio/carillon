{{-- Tile of an integration: its official logo, else a monogram in its colour, else an icon. --}}
@php
    $size ??= 'size-10';
    $svg = isset($card['logo']) ? \App\Integrations\Catalog::logo($card['logo']) : null;
@endphp
@if ($svg)
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-lg bg-white p-[18%] ring-1 ring-zinc-200 dark:ring-white/10" aria-hidden="true">{!! $svg !!}</span>
@elseif ($card['monogram'] ?? '')
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-lg text-[13px] font-semibold tracking-tight text-white" style="background: {{ $card['color'] }}" aria-hidden="true">{{ $card['monogram'] }}</span>
@else
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300" aria-hidden="true"><x-ui.icon :name="$card['icon']" class="size-[18px]" /></span>
@endif
