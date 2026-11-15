<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['project', 'size' => 'md']));

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

foreach (array_filter((['project', 'size' => 'md']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $palette = [
        'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
    ];
    $color = $palette[crc32($project->app_id) % count($palette)];
    $sizes = ['xs' => 'size-6 rounded-md text-[11px]', 'sm' => 'size-8 rounded-lg text-sm', 'md' => 'size-10 rounded-xl text-base', 'lg' => 'size-14 rounded-2xl text-xl'];
?>
<span <?php echo e($attributes->merge(['class' => "relative inline-flex shrink-0 items-center justify-center overflow-hidden font-semibold {$color} {$sizes[$size]}"])); ?>>
    <?php if($project->icon_path): ?>
        <img src="<?php echo e(route('projects.icon', ['project' => $project, 'v' => substr(md5($project->icon_path), 0, 8)])); ?>" alt="" class="size-full object-cover" loading="lazy">
    <?php else: ?>
        <?php echo e($project->initials() ?: '#'); ?>

    <?php endif; ?>
</span>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/ui/project-icon.blade.php ENDPATH**/ ?>