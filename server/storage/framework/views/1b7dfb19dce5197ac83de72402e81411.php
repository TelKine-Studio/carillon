<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['project']));

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

foreach (array_filter((['project']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ platform: 'ios' }" <?php echo e($attributes->merge(['class' => 'space-y-4'])); ?>>
    <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
        <?php $__currentLoopData = ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button type="button" x-on:click="platform = '<?php echo e($key); ?>'" class="rounded-md py-1.5 font-medium transition"
                :class="platform === '<?php echo e($key); ?>' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <div x-show="platform === 'ios'" class="overflow-hidden rounded-lg bg-linear-to-br from-slate-700 via-slate-800 to-zinc-900 p-4 pt-10">
        <p class="text-center text-5xl font-light tracking-tight text-white/90 tabular-nums">9:41</p>
        <div class="mt-8 rounded-[20px] bg-white/75 p-3 shadow-lg backdrop-blur-xl dark:bg-zinc-800/80">
            <div class="flex gap-2.5">
                <?php if (isset($component)) { $__componentOriginal36407694a283dbdf6dc29360724d3fd0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36407694a283dbdf6dc29360724d3fd0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.project-icon','data' => ['project' => $project,'size' => 'sm','class' => '!rounded-[8px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.project-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project),'size' => 'sm','class' => '!rounded-[8px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36407694a283dbdf6dc29360724d3fd0)): ?>
<?php $attributes = $__attributesOriginal36407694a283dbdf6dc29360724d3fd0; ?>
<?php unset($__attributesOriginal36407694a283dbdf6dc29360724d3fd0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36407694a283dbdf6dc29360724d3fd0)): ?>
<?php $component = $__componentOriginal36407694a283dbdf6dc29360724d3fd0; ?>
<?php unset($__componentOriginal36407694a283dbdf6dc29360724d3fd0); ?>
<?php endif; ?>
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || <?php echo \Illuminate\Support\Js::from($project->name)->toHtml() ?>"></p>
                        <span class="shrink-0 text-[11px] text-zinc-500"><?php echo e(__('now')); ?></span>
                    </div>
                    <p class="truncate font-medium text-zinc-800 dark:text-zinc-100" x-show="preview.subtitle" x-text="preview.subtitle"></p>
                    <p class="line-clamp-4 text-zinc-800 dark:text-zinc-200" x-text="preview.body || <?php echo \Illuminate\Support\Js::from(__('Your message'))->toHtml() ?>"></p>
                </div>
                <template x-if="preview.image">
                    <img :src="preview.image" alt="" class="size-10 shrink-0 rounded-md object-cover" x-on:error="$el.style.display = 'none'">
                </template>
            </div>
        </div>
        <template x-if="preview.buttons.length">
            <div class="mt-1.5 overflow-hidden rounded-[14px] bg-white/75 backdrop-blur-xl dark:bg-zinc-800/80">
                <template x-for="button in preview.buttons" :key="button">
                    <p class="border-t border-zinc-900/10 px-3 py-2 text-[13px] text-zinc-900 first:border-0 dark:border-white/10 dark:text-white" x-text="button"></p>
                </template>
            </div>
        </template>
    </div>

    
    <div x-show="platform === 'android'" x-cloak class="rounded-lg bg-zinc-200 p-3 dark:bg-zinc-800">
        <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'bell','class' => 'size-3 text-brand-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell','class' => 'size-3 text-brand-600']); ?>
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
                <span class="truncate"><?php echo e($project->name); ?></span><span>· <?php echo e(__('now')); ?></span>
            </div>
            <div class="mt-1.5 flex gap-3">
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || <?php echo \Illuminate\Support\Js::from($project->name)->toHtml() ?>"></p>
                    <p class="line-clamp-3 text-zinc-600 dark:text-zinc-300" x-text="preview.body || <?php echo \Illuminate\Support\Js::from(__('Your message'))->toHtml() ?>"></p>
                </div>
                <template x-if="preview.image">
                    <img :src="preview.image" alt="" class="size-10 shrink-0 rounded object-cover" x-on:error="$el.style.display = 'none'">
                </template>
            </div>
            <template x-if="preview.buttons.length">
                <div class="mt-2.5 flex gap-4">
                    <template x-for="button in preview.buttons" :key="button">
                        <span class="text-[12px] font-semibold text-brand-700 uppercase dark:text-brand-300" x-text="button"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>

    
    <div x-show="platform === 'web'" x-cloak class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800/60">
        <div class="ml-auto max-w-xs rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex gap-3">
                <?php if (isset($component)) { $__componentOriginal36407694a283dbdf6dc29360724d3fd0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36407694a283dbdf6dc29360724d3fd0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.project-icon','data' => ['project' => $project,'size' => 'md','class' => '!rounded-md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.project-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project),'size' => 'md','class' => '!rounded-md']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36407694a283dbdf6dc29360724d3fd0)): ?>
<?php $attributes = $__attributesOriginal36407694a283dbdf6dc29360724d3fd0; ?>
<?php unset($__attributesOriginal36407694a283dbdf6dc29360724d3fd0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36407694a283dbdf6dc29360724d3fd0)): ?>
<?php $component = $__componentOriginal36407694a283dbdf6dc29360724d3fd0; ?>
<?php unset($__componentOriginal36407694a283dbdf6dc29360724d3fd0); ?>
<?php endif; ?>
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || <?php echo \Illuminate\Support\Js::from($project->name)->toHtml() ?>"></p>
                    <p class="line-clamp-3 text-zinc-600 dark:text-zinc-300" x-text="preview.body || <?php echo \Illuminate\Support\Js::from(__('Your message'))->toHtml() ?>"></p>
                    <p class="mt-1 truncate text-[11px] text-zinc-400"><?php echo e(parse_url(config('app.url'), PHP_URL_HOST)); ?></p>
                </div>
            </div>
            <template x-if="preview.image">
                <img :src="preview.image" alt="" class="mt-2.5 max-h-40 w-full rounded object-cover" x-on:error="$el.style.display = 'none'">
            </template>
            <template x-if="preview.buttons.length">
                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    <template x-for="button in preview.buttons.slice(0, 2)" :key="button">
                        <span class="truncate rounded-md bg-zinc-100 px-2 py-1.5 text-center text-[12px] font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200" x-text="button"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/messages/preview.blade.php ENDPATH**/ ?>