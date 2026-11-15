@props(['class' => 'size-8'])
{{-- Carillon mark: a "C" and its clapper (sources in docs/assets/brand). --}}
<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-brand-700 text-white shadow-sm shadow-brand-700/30 ring-1 ring-inset ring-white/15 $class"]) }}>
    <svg viewBox="0 0 32 32" class="size-full" aria-hidden="true">
        <path d="M23 8.6A10 10 0 1 0 23 23.4" fill="none" stroke="currentColor" stroke-width="4.2" stroke-linecap="round"/>
        <circle cx="17.2" cy="16" r="3.3" fill="currentColor"/>
    </svg>
</span>
