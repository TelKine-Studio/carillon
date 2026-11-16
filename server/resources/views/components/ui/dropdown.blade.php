@props(['align' => 'right', 'width' => 'w-56'])
{{-- Menus aligned left or right float above the page, so cards with hidden overflow do not clip them. --}}
<div x-data="dropdownMenu(@js($align !== 'top'))" @keydown.escape.window="open = false" @click.outside="open = false" {{ $attributes->merge(['class' => 'relative']) }}>
    <div x-ref="trigger" @click="toggle()">{{ $trigger }}</div>
    <div x-ref="menu" x-cloak x-show="open"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
        @class([
            'absolute z-40 mt-2 origin-top rounded-xl border border-zinc-200 bg-white p-1.5 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
            'bottom-full mb-2 origin-bottom' => $align === 'top',
        ])>
        {{ $slot }}
    </div>
</div>
