<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null, 'description' => null, 'padding' => true, 'icon' => null]));

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

foreach (array_filter((['title' => null, 'description' => null, 'padding' => true, 'icon' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<section <?php echo e($attributes->merge(['class' => 'card'])); ?>>
    <?php if($title || isset($actions)): ?>
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <?php if($icon): ?>
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300">
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-[18px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-[18px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                    </span>
                <?php endif; ?>
                <div class="min-w-0">
                    <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-zinc-50"><?php echo e($title); ?></h2>
                    <?php if($description): ?>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400"><?php echo e($description); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if(isset($actions)): ?>
                <div class="flex shrink-0 items-center gap-2"><?php echo e($actions); ?></div>
            <?php endif; ?>
        </header>
    <?php endif; ?>
    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['px-5 py-5 sm:px-6' => $padding, 'pt-4' => $padding && $title, 'mt-4' => ! $padding && $title]); ?>">
        <?php echo e($slot); ?>

    </div>
    <?php if(isset($footer)): ?>
        <footer class="flex flex-wrap items-center justify-end gap-3 rounded-b-2xl border-t border-zinc-100 bg-zinc-50/70 px-5 py-3.5 sm:px-6 dark:border-zinc-800 dark:bg-white/[.02]">
            <?php echo e($footer); ?>

        </footer>
    <?php endif; ?>
</section>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/card.blade.php ENDPATH**/ ?>