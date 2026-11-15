<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label' => null, 'hint' => null, 'options' => [], 'value' => null, 'size' => 'md']));

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

foreach (array_filter((['name', 'label' => null, 'hint' => null, 'options' => [], 'value' => null, 'size' => 'md']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $selected = (string) old($name, $value);
    $current = $options[$selected] ?? (count($options) ? reset($options) : '');
    $id = $attributes->get('id', $name);
?>

<?php if (isset($component)) { $__componentOriginald816e58425c8bb369623a9433739178c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald816e58425c8bb369623a9433739178c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.field','data' => ['name' => $name,'label' => $label,'hint' => $hint,'for' => $id,'class' => $attributes->get('class')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hint),'for' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id),'class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes->get('class'))]); ?>
    <div x-data="selectBox" class="relative" @click.outside="close()" @keydown.escape.stop="close()">
        <select x-ref="native" name="<?php echo e($name); ?>" class="sr-only" tabindex="-1" aria-hidden="true" @change="sync()"
            <?php echo e($attributes->except(['id', 'class'])); ?>>
            <?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionValue => $optionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($optionValue); ?>" <?php if($selected === (string) $optionValue): echo 'selected'; endif; ?>><?php echo e($optionLabel); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <button type="button" id="<?php echo e($id); ?>" x-ref="button" @click="toggle()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pick()" @keydown.space.prevent="pick()"
            aria-haspopup="listbox" :aria-expanded="open.toString()"
            class="<?php echo \Illuminate\Support\Arr::toCssClasses(['form-control flex items-center gap-2 pr-10 text-left', '!py-1.5 !pr-8 text-xs' => $size === 'sm', 'form-control-invalid' => $errors->has($name)]); ?>"
            :class="open && '!border-brand-500 ring-4 ring-brand-500/15'">
            <span class="min-w-0 flex-1 truncate" x-text="label"><?php echo e($current); ?></span>
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevron-down','class' => 'pointer-events-none absolute top-1/2 '.e($size === 'sm' ? 'right-2.5 size-3.5' : 'right-3.5 size-4').' -translate-y-1/2 text-zinc-400 transition',':class' => 'open && \'rotate-180\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','class' => 'pointer-events-none absolute top-1/2 '.e($size === 'sm' ? 'right-2.5 size-3.5' : 'right-3.5 size-4').' -translate-y-1/2 text-zinc-400 transition',':class' => 'open && \'rotate-180\'']); ?>
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
        </button>
        <ul x-show="open" x-cloak x-ref="list" role="listbox"
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
            class="absolute z-40 mt-1.5 max-h-64 w-full min-w-48 overflow-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900">
            <template x-for="(option, index) in options" :key="option.value">
                <li role="option" :aria-selected="(option.value === value).toString()" @click="choose(option.value)" @mouseenter="active = index"
                    class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-700 dark:text-zinc-200"
                    :class="{ 'bg-zinc-100 dark:bg-white/5': active === index, 'font-semibold text-zinc-900 dark:text-white': option.value === value }">
                    <span class="min-w-0 flex-1" x-text="option.label"></span>
                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'check','class' => 'size-4 shrink-0 text-brand-600','xShow' => 'option.value === value']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'size-4 shrink-0 text-brand-600','x-show' => 'option.value === value']); ?>
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
                </li>
            </template>
        </ul>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/select.blade.php ENDPATH**/ ?>