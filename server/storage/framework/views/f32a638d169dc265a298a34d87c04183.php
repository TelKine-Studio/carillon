<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label' => null, 'hint' => null, 'type' => 'text', 'value' => null, 'prefix' => null, 'icon' => null, 'optional' => false]));

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

foreach (array_filter((['name', 'label' => null, 'hint' => null, 'type' => 'text', 'value' => null, 'prefix' => null, 'icon' => null, 'optional' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php $id = $attributes->get('id', $name); ?>
<?php if (isset($component)) { $__componentOriginald816e58425c8bb369623a9433739178c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald816e58425c8bb369623a9433739178c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.field','data' => ['name' => $name,'label' => $label,'hint' => $hint,'for' => $id,'optional' => $optional,'class' => $attributes->get('class')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hint),'for' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id),'optional' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($optional),'class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes->get('class'))]); ?>
    <div class="relative flex">
        <?php if($prefix): ?>
            <span class="inline-flex items-center rounded-l-xl border border-r-0 border-zinc-200 bg-zinc-50 px-3 font-mono text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400"><?php echo e($prefix); ?></span>
        <?php endif; ?>
        <?php if($icon): ?>
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400']); ?>
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
        <?php endif; ?>
        <?php $isDate = in_array($type, ['date', 'datetime-local'], true); ?>
        <?php if($isDate): ?>
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'calendar','class' => 'pointer-events-none absolute top-1/2 right-3.5 z-10 size-4 -translate-y-1/2 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'calendar','class' => 'pointer-events-none absolute top-1/2 right-3.5 z-10 size-4 -translate-y-1/2 text-zinc-400']); ?>
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
        <?php endif; ?>
        <input id="<?php echo e($id); ?>" name="<?php echo e($name); ?>" type="<?php echo e($isDate ? 'text' : $type); ?>" value="<?php echo e($type === 'password' ? '' : old($name, $value)); ?>"
            <?php if($isDate): ?> x-data="datePicker(<?php echo e($type === 'datetime-local' ? 'true' : 'false'); ?>, <?php echo \Illuminate\Support\Js::from($attributes->get('min'))->toHtml() ?>)" placeholder="<?php echo e($attributes->get('placeholder', $type === 'datetime-local' ? __('Choose a date and time') : __('Choose a date'))); ?>" <?php endif; ?>
            <?php echo e($attributes->except($isDate ? ['id', 'class', 'min', 'placeholder'] : ['id', 'class'])->class(['form-control', 'rounded-l-none' => $prefix, 'pl-10' => $icon, 'pr-10' => $isDate, 'form-control-invalid' => $errors->has($name)])); ?>>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald816e58425c8bb369623a9433739178c)): ?>
<?php $attributes = $__attributesOriginald816e58425c8bb369623a9433739178c; ?>
<?php unset($__attributesOriginald816e58425c8bb369623a9433739178c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald816e58425c8bb369623a9433739178c)): ?>
<?php $component = $__componentOriginald816e58425c8bb369623a9433739178c; ?>
<?php unset($__componentOriginald816e58425c8bb369623a9433739178c); ?>
<?php endif; ?>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/input.blade.php ENDPATH**/ ?>