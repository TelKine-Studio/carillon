@if ($paginator->hasPages())
    @php
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $button = 'inline-flex h-8 items-center gap-1 rounded-md border px-2.5 text-sm font-medium transition-colors';
        $enabled = $button.' border-zinc-300 bg-white text-zinc-800 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800';
        $disabled = $button.' cursor-default border-zinc-200 text-zinc-300 dark:border-zinc-800 dark:text-zinc-600';
    @endphp
    {{-- Thin like the other buttons: count on the left, pages and arrows on the right. --}}
    <nav role="navigation" aria-label="{{ __('Pagination') }}" class="flex items-center justify-between gap-3">
        <p class="text-sm text-zinc-500 tabular-nums dark:text-zinc-400">
            @if (method_exists($paginator, 'total'))
                {{ __('Showing :from–:to of :total', ['from' => $n($paginator->firstItem()), 'to' => $n($paginator->lastItem()), 'total' => $n($paginator->total())]) }}
            @endif
        </p>
        <div class="flex items-center gap-2">
            @if (method_exists($paginator, 'lastPage'))
                <span class="hidden text-sm text-zinc-500 tabular-nums sm:inline dark:text-zinc-400">{{ __('Page :page of :pages', ['page' => $n($paginator->currentPage()), 'pages' => $n($paginator->lastPage())]) }}</span>
            @endif
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true"><x-ui.icon name="chevron-left" class="size-3.5" /><span class="hidden sm:inline">{{ __('Previous') }}</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $enabled }}"><x-ui.icon name="chevron-left" class="size-3.5" /><span class="hidden sm:inline">{{ __('Previous') }}</span></a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $enabled }}"><span class="hidden sm:inline">{{ __('Next') }}</span><x-ui.icon name="chevron-right" class="size-3.5" /></a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true"><span class="hidden sm:inline">{{ __('Next') }}</span><x-ui.icon name="chevron-right" class="size-3.5" /></span>
            @endif
        </div>
    </nav>
@endif
