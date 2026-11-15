<?php if($paginator->hasPages()): ?>
    <nav role="navigation" aria-label="<?php echo e(__('Pagination')); ?>" class="flex items-center justify-between gap-3">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            <?php if(method_exists($paginator, 'total')): ?>
                <?php echo e(__('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()])); ?>

            <?php endif; ?>
        </p>
        <div class="flex gap-2">
            <?php if($paginator->onFirstPage()): ?>
                <span class="rounded-xl border border-zinc-200 px-3.5 py-2 text-sm text-zinc-300 dark:border-zinc-800 dark:text-zinc-600"><?php echo e(__('Previous')); ?></span>
            <?php else: ?>
                <a href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev" class="rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"><?php echo e(__('Previous')); ?></a>
            <?php endif; ?>
            <?php if($paginator->hasMorePages()): ?>
                <a href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next" class="rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"><?php echo e(__('Next')); ?></a>
            <?php else: ?>
                <span class="rounded-xl border border-zinc-200 px-3.5 py-2 text-sm text-zinc-300 dark:border-zinc-800 dark:text-zinc-600"><?php echo e(__('Next')); ?></span>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/pagination/default.blade.php ENDPATH**/ ?>