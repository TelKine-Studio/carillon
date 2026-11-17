{{-- Menu of the step kinds, opened by a "+" of the flow; inserts at :at (a JS expression). --}}
<div x-show="menu" x-cloak x-transition.opacity.duration.100ms class="absolute top-full left-1/2 z-30 mt-1 w-72 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white p-1.5 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900">
    @foreach ($groups as $groupLabel => $groupKinds)
        <p class="px-2 pt-1.5 pb-1 text-[11px] font-semibold tracking-wide text-zinc-400 uppercase">{{ $groupLabel }}</p>
        @foreach ($groupKinds as $kind)
            <button type="button" x-on:click="add('{{ $kind }}', {{ $at }}); menu = false" class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">
                <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-md {{ $kinds[$kind][2] }}"><x-ui.icon :name="$kinds[$kind][0]" class="size-3.5" /></span>{{ $kinds[$kind][1] }}
            </button>
        @endforeach
    @endforeach
</div>
