<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['color' => 'zinc', 'icon' => null, 'dot' => false]));

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

foreach (array_filter((['color' => 'zinc', 'icon' => null, 'dot' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $colors = [
        'zinc' => 'bg-zinc-100 text-zinc-700 dark:bg-white/5 dark:text-zinc-300',
        'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
        'green' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        'red' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        'blue' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
        'amber' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
        'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    ];
    $dots = ['zinc' => 'bg-zinc-400', 'brand' => 'bg-brand-500', 'green' => 'bg-emerald-500', 'red' => 'bg-red-500', 'blue' => 'bg-sky-500', 'amber' => 'bg-amber-500', 'violet' => 'bg-violet-500'];
?>
<span <?php echo e($attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap '.$colors[$color]])); ?>>
    <?php if($dot): ?><span class="size-1.5 rounded-full <?php echo e($dots[$color]); ?>"></span><?php endif; ?>
    <?php if($icon): ?><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><?php endif; ?>
    <?php echo e($slot); ?>

</span>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/badge.blade.php ENDPATH**/ ?>