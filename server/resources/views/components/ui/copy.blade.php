@props(['value', 'label' => null])
{{-- Small button copying a value (ids, tokens...): works inside links and clickable rows. --}}
<span x-data="copy({{ \Illuminate\Support\Js::from((string) $value) }})" {{ $attributes->merge(['class' => 'relative z-10 inline-flex shrink-0 align-middle']) }}>
    <button type="button" x-on:click.stop.prevent="copy()"
        class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:text-zinc-500 dark:hover:bg-white/10 dark:hover:text-zinc-200"
        :title="copied ? @js(__('Copied')) : @js($label ?? __('Copy'))" aria-label="{{ $label ?? __('Copy') }}">
        <x-ui.icon name="copy" class="size-3.5" x-show="! copied" />
        <x-ui.icon name="check" class="size-3.5 text-emerald-500" x-show="copied" x-cloak />
    </button>
</span>
