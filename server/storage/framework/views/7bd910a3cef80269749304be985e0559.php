<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['align' => 'right', 'width' => 'w-56']));

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

foreach (array_filter((['align' => 'right', 'width' => 'w-56']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false" <?php echo e($attributes->merge(['class' => 'relative'])); ?>>
    <div @click="open = ! open"><?php echo e($trigger); ?></div>
    <div x-cloak x-show="open"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            'absolute z-40 mt-2 origin-top rounded-xl border border-zinc-200 bg-white p-1.5 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
            'bottom-full mb-2 origin-bottom' => $align === 'top',
        ]); ?>">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/dropdown.blade.php ENDPATH**/ ?>