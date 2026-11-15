<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['user' => null, 'name' => null, 'size' => 'md']));

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

foreach (array_filter((['user' => null, 'name' => null, 'size' => 'md']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $label = $user?->name ?? $name ?? '?';
    $initials = $user?->initials() ?? collect(preg_split('/\s+/', trim($label)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $palette = ['from-orange-400 to-rose-500', 'from-sky-400 to-indigo-500', 'from-emerald-400 to-teal-600', 'from-violet-400 to-fuchsia-500', 'from-amber-400 to-orange-600', 'from-cyan-400 to-blue-600'];
    $gradient = $palette[crc32($label) % count($palette)];
    $sizes = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-9 text-xs', 'lg' => 'size-12 text-base'];
?>
<span <?php echo e($attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-linear-to-br font-semibold text-white ring-2 ring-white dark:ring-zinc-900 {$gradient} {$sizes[$size]}"])); ?>><?php echo e($initials ?: '?'); ?></span>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/avatar.blade.php ENDPATH**/ ?>