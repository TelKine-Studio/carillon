<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label' => null, 'hint' => null, 'optional' => false, 'placeholder' => null]));

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

foreach (array_filter((['name', 'label' => null, 'hint' => null, 'optional' => false, 'placeholder' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
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
    <label for="<?php echo e($id); ?>" x-data="{ file: null, over: false,
            pick(files) { this.file = files && files[0] ? { name: files[0].name, size: files[0].size } : null },
            clear() { this.$refs.input.value = ''; this.file = null },
            size(bytes) { return bytes < 1024 ? bytes + ' B' : bytes < 1048576 ? (bytes / 1024).toFixed(0) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB' } }"
        @dragover.prevent="over = true" @dragleave.prevent="over = false"
        @drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; pick($event.dataTransfer.files)"
        class="<?php echo \Illuminate\Support\Arr::toCssClasses(['flex cursor-pointer items-center gap-3 rounded-xl border border-dashed bg-white px-3.5 py-2.5 transition hover:border-brand-400 hover:bg-brand-50/30 dark:bg-zinc-900 dark:hover:bg-brand-500/5', 'border-red-400' => $errors->has($name), 'border-zinc-300 dark:border-zinc-700' => ! $errors->has($name)]); ?>"
        :class="over && '!border-brand-500 bg-brand-50/50'">
        <input type="file" id="<?php echo e($id); ?>" name="<?php echo e($name); ?>" x-ref="input" class="sr-only" @change="pick($event.target.files)" <?php echo e($attributes->except(['id', 'class'])); ?>>
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'upload','class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'upload','class' => 'size-4']); ?>
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
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="file ? file.name : <?php echo \Illuminate\Support\Js::from($placeholder ?? __('Choose a file'))->toHtml() ?>"><?php echo e($placeholder ?? __('Choose a file')); ?></span>
            <span class="block text-xs text-zinc-500" x-text="file ? size(file.size) : <?php echo \Illuminate\Support\Js::from(__('or drop it here'))->toHtml() ?>"><?php echo e(__('or drop it here')); ?></span>
        </span>
        <button type="button" x-show="file" x-cloak @click.prevent="clear()" class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10">
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
<?php endif; ?><span class="sr-only"><?php echo e(__('Remove')); ?></span>
        </button>
    </label>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/file.blade.php ENDPATH**/ ?>