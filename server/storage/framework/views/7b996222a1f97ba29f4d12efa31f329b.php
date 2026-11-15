<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label', 'description' => null, 'checked' => false]));

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

foreach (array_filter((['name', 'label', 'description' => null, 'checked' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php $isChecked = session()->hasOldInput() ? (bool) old($name) : $checked; ?>
<label class="group flex cursor-pointer items-start justify-between gap-6">
    <span class="space-y-0.5">
        <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200"><?php echo e($label); ?></span>
        <?php if($description): ?>
            <span class="block text-sm leading-relaxed text-zinc-500 dark:text-zinc-400"><?php echo e($description); ?></span>
        <?php endif; ?>
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" name="<?php echo e($name); ?>" value="1" class="peer sr-only" <?php if($isChecked): echo 'checked'; endif; ?> <?php echo e($attributes); ?>>
        <span class="h-6 w-11 rounded-full bg-zinc-200 transition peer-checked:bg-brand-600 peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/25 dark:bg-zinc-700"></span>
        <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-sm ring-1 ring-black/5 transition peer-checked:translate-x-5"></span>
    </span>
</label>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/toggle.blade.php ENDPATH**/ ?>