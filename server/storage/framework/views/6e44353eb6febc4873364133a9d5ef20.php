<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'title' => null, 'description' => null, 'maxWidth' => 'max-w-lg', 'open' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['name', 'title' => null, 'description' => null, 'maxWidth' => 'max-w-lg', 'open' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div x-data="{ open: <?php echo e($open ? 'true' : 'false'); ?> }"
    x-on:open-modal.window="if ($event.detail === <?php echo \Illuminate\Support\Js::from($name)->toHtml() ?>) open = true"
    x-on:close-modal.window="if ($event.detail === <?php echo \Illuminate\Support\Js::from($name)->toHtml() ?>) open = false"
    x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
            <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-zinc-950/40 backdrop-blur-sm"></div>
            <div x-show="open" x-trap.noscroll="open"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                class="relative w-full <?php echo e($maxWidth); ?> rounded-t-3xl border border-zinc-200 bg-white p-6 shadow-2xl sm:rounded-2xl dark:border-zinc-800 dark:bg-zinc-900">
                <button type="button" @click="open = false" class="absolute top-4 right-4 rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/5 dark:hover:text-zinc-200">
                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'size-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><span class="sr-only"><?php echo e(__('Close')); ?></span>
                </button>
                <?php if($title): ?>
                    <h2 class="pr-8 text-lg font-semibold text-zinc-900 dark:text-white"><?php echo e($title); ?></h2>
                    <?php if($description): ?><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"><?php echo e($description); ?></p><?php endif; ?>
                    <div class="mt-5"><?php echo e($slot); ?></div>
                <?php else: ?>
                    <?php echo e($slot); ?>

                <?php endif; ?>
            </div>
        </div>
    </template>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/modal.blade.php ENDPATH**/ ?>