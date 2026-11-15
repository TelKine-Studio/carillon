<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'value', 'icon', 'hint' => null, 'series' => null, 'href' => null]));

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

foreach (array_filter((['label', 'value', 'icon', 'hint' => null, 'series' => null, 'href' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $tag = $href ? 'a' : 'div';
    $points = null;
    if ($series && count($series) > 1) {
        $max = max(max($series), 1);
        $step = 100 / (count($series) - 1);
        $coords = collect($series)->values()->map(fn ($v, $i) => round($i * $step, 2).','.round(28 - ($v / $max) * 24, 2));
        $points = $coords->implode(' ');
        $area = '0,30 '.$points.' 100,30';
    }
?>
<<?php echo e($tag); ?> <?php if($href): ?> href="<?php echo e($href); ?>" <?php endif; ?> <?php echo e($attributes->merge(['class' => 'card relative overflow-hidden p-5 '.($href ? 'card-hover' : '')])); ?>>
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400"><?php echo e($label); ?></p>
        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-4']); ?>
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
    <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white"><?php echo e($value); ?></p>
    <?php if($hint): ?>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><?php echo e($hint); ?></p>
    <?php endif; ?>
    <?php if($points): ?>
        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-3 h-10 w-full text-brand-500" aria-hidden="true">
            <defs><linearGradient id="spark-<?php echo e(md5($label)); ?>" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".25"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
            <polygon points="<?php echo e($area); ?>" fill="url(#spark-<?php echo e(md5($label)); ?>)" />
            <polyline points="<?php echo e($points); ?>" fill="none" stroke="currentColor" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
        </svg>
    <?php endif; ?>
</<?php echo e($tag); ?>>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/stat.blade.php ENDPATH**/ ?>