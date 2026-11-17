@props(['options', 'model', 'placeholder' => ''])
{{-- Custom dropdown whose options come from Alpine (JS expression returning [[value, label], …]). --}}
<div x-data="dropdownSelect(() => {{ $options }})" x-modelable="value" x-model="{{ $model }}" @click.outside="open = false" @keydown.escape.stop="open = false" {{ $attributes->merge(['class' => 'relative']) }}>
    <button type="button" x-ref="button" @click="toggle()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pick()" @keydown.space.prevent="pick()"
        aria-haspopup="listbox" :aria-expanded="open.toString()" class="form-control flex items-center gap-2 pr-9 text-left" :class="open && '!border-brand-500 ring-2 ring-brand-500/20'">
        <span class="min-w-0 flex-1 truncate" x-text="label || @js($placeholder)"></span>
        <x-ui.icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-zinc-400 transition" ::class="open && 'rotate-180'" />
    </button>
    <ul x-show="open" x-cloak role="listbox" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute z-40 mt-1.5 max-h-64 w-full min-w-44 overflow-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900">
        <template x-for="(option, index) in options" :key="option.value">
            <li role="option" @click="choose(option.value)" @mouseenter="active = index"
                class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-700 dark:text-zinc-200"
                :class="{ 'bg-zinc-100 dark:bg-white/5': active === index, 'font-semibold text-zinc-900 dark:text-white': option.value === String(value) }">
                <span class="min-w-0 flex-1" x-text="option.label"></span>
                <x-ui.icon name="check" class="size-4 shrink-0 text-brand-600" x-show="option.value === String(value)" />
            </li>
        </template>
    </ul>
</div>
