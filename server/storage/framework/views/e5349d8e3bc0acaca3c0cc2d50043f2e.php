<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['section']));

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

foreach (array_filter((['section']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="grid gap-8 lg:grid-cols-[260px_minmax(0,1fr)]">
    <nav class="hidden lg:block">
        <ul class="sticky top-24 space-y-1">
            <?php $__currentLoopData = [
                'general' => ['icon' => 'settings', 'label' => __('General'), 'text' => __('Name, URL, access')],
                'security' => ['icon' => 'shield', 'label' => __('Security'), 'text' => __('2FA, single sign-on')],
                'mail' => ['icon' => 'mail', 'label' => __('Email'), 'text' => __('Outgoing mail server')],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li>
                    <a href="<?php echo e(route('admin.settings.edit', $key)); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['flex items-start gap-3 rounded-xl p-3 transition', 'bg-white shadow-(--shadow-soft) ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800' => $section === $key, 'hover:bg-zinc-100 dark:hover:bg-white/5' => $section !== $key]); ?>">
                        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['inline-flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-brand-600 text-white' => $section === $key, 'bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400' => $section !== $key]); ?>"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $tab['icon'],'class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tab['icon']),'class' => 'size-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?></span>
                        <span>
                            <span class="block text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($tab['label']); ?></span>
                            <span class="block text-xs text-zinc-500"><?php echo e($tab['text']); ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </nav>
    <div class="min-w-0 max-w-3xl space-y-6">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/settings/layout.blade.php ENDPATH**/ ?>