<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['icon' => 'inbox', 'title', 'description' => null]));

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

foreach (array_filter((['icon' => 'inbox', 'title', 'description' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div <?php echo e($attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center'])); ?>>
    <div class="relative mb-5">
        <span class="absolute inset-0 -m-3 rounded-full bg-brand-500/10 blur-xl"></span>
        <span class="relative inline-flex size-14 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-brand-600 shadow-(--shadow-soft) dark:border-zinc-800 dark:bg-zinc-900 dark:text-brand-400">
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-6']); ?>
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
    </div>
    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100"><?php echo e($title); ?></h3>
    <?php if($description): ?>
        <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-zinc-500 dark:text-zinc-400"><?php echo e($description); ?></p>
    <?php endif; ?>
    <?php if($slot->isNotEmpty()): ?>
        <div class="mt-6 flex flex-wrap justify-center gap-2"><?php echo e($slot); ?></div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/empty.blade.php ENDPATH**/ ?>