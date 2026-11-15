@props(['title' => null])
<x-layouts.base :title="$title">

    <div x-data="{ drawer: false }" class="min-h-full">
        {{-- Desktop sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-[272px] border-r border-zinc-200/80 bg-white/80 backdrop-blur-xl lg:block dark:border-zinc-800/80 dark:bg-zinc-950/80">
            @include('layouts.navigation')
        </aside>

        {{-- Mobile drawer --}}
        <div x-cloak x-show="drawer" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-show="drawer" x-transition.opacity @click="drawer = false" class="absolute inset-0 bg-zinc-950/40 backdrop-blur-sm"></div>
            <aside x-show="drawer" x-trap.noscroll="drawer"
                x-transition:enter="transition ease-out duration-250" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                class="relative h-full w-[86%] max-w-xs bg-white shadow-2xl dark:bg-zinc-950">
                <button type="button" @click="drawer = false" class="absolute top-6 right-4 rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/5">
                    <x-ui.icon name="x" class="size-5" /><span class="sr-only">{{ __('Close') }}</span>
                </button>
                @include('layouts.navigation')
            </aside>
        </div>

        <div class="lg:pl-[272px]">
            {{-- Top bar --}}
            {{-- On large screens the bar only appears when the page has actions. --}}
            <header @class(['sticky top-0 z-20 border-b border-zinc-200/70 bg-zinc-50/80 backdrop-blur-xl dark:border-zinc-800/70 dark:bg-zinc-950/80', 'lg:hidden' => ! isset($actions)])>
                <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-10">
                    <button type="button" @click="drawer = true" class="-ml-1 rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden dark:text-zinc-300 dark:hover:bg-white/5">
                        <x-ui.icon name="menu" class="size-5" /><span class="sr-only">{{ __('Menu') }}</span>
                    </button>
                    <a href="{{ route('projects.index') }}" class="flex items-center gap-2 lg:hidden"><x-ui.logo class="size-8" /></a>

                    <div class="ml-auto flex items-center gap-2">
                        {{ $actions ?? '' }}
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 pt-8 pb-16 sm:px-6 lg:px-10">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
