@props(['subscription'])
@php
    [$icon, $label] = match ($subscription->type) {
        'ios' => ['apple', 'iOS'],
        'android' => ['android', 'Android'],
        default => ['globe-web', 'Web'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300']) }} title="{{ $label }}">
    <x-ui.icon :name="$icon" class="size-[18px]" />
</span>
