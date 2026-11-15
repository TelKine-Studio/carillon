<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null, 'heading' => null, 'subheading' => null, 'wide' => false]));

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

foreach (array_filter((['title' => null, 'heading' => null, 'subheading' => null, 'wide' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if (isset($component)) { $__componentOriginald4c772c02301431d3253f64117700596 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4c772c02301431d3253f64117700596 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.base','data' => ['title' => $title,'class' => 'bg-white dark:bg-zinc-950']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.base'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title),'class' => 'bg-white dark:bg-zinc-950']); ?>
    <div class="flex min-h-full">
        
        <aside class="relative hidden w-[44%] max-w-2xl overflow-hidden bg-zinc-950 lg:flex lg:flex-col">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,var(--color-brand-600),transparent_55%),radial-gradient(ellipse_at_bottom_right,#7c3aed55,transparent_50%)] opacity-90"></div>
            <div class="dot-grid absolute inset-0 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>

            <div class="relative flex flex-1 flex-col justify-between p-12 xl:p-16">
                <a href="<?php echo e(url('/')); ?>" class="flex items-center gap-3 text-white">
                    <?php if (isset($component)) { $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.logo','data' => ['class' => 'size-10']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-10']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $attributes = $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $component = $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
                    <span class="text-lg font-semibold tracking-tight"><?php echo e(config('app.name')); ?></span>
                </a>

                <div class="space-y-4">
                    <?php $__currentLoopData = [
                        ['title' => __('Your order is on its way'), 'sub' => 'Boutique', 'body' => __('Delivery expected tomorrow before noon.'), 'time' => __('now')],
                        ['title' => __('New message from Awa'), 'sub' => 'Chat', 'body' => __('Are we still on for tonight?'), 'time' => '2 min'],
                        ['title' => __('-20% this weekend only'), 'sub' => 'Promo', 'body' => __('On your favourite products, until Sunday.'), 'time' => '12 min'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="max-w-sm rounded-[22px] border border-white/15 bg-white/10 p-3.5 text-white shadow-2xl shadow-black/30 backdrop-blur-xl" style="margin-left: <?php echo e($index * 2.5); ?>rem; opacity: <?php echo e(1 - $index * 0.18); ?>">
                            <div class="flex gap-3">
                                <?php if (isset($component)) { $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.logo','data' => ['class' => 'size-9 rounded-[10px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-9 rounded-[10px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $attributes = $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $component = $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-[13px] font-semibold"><?php echo e($card['title']); ?></p>
                                        <span class="text-[11px] text-white/60"><?php echo e($card['time']); ?></span>
                                    </div>
                                    <p class="text-[13px] font-medium text-white/80"><?php echo e($card['sub']); ?></p>
                                    <p class="text-[13px] text-white/70"><?php echo e($card['body']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <div>
                    <h2 class="max-w-md text-3xl font-semibold tracking-tight text-white xl:text-4xl"><?php echo e(__('Your push notifications, on your server.')); ?></h2>
                    <p class="mt-4 max-w-md text-base leading-relaxed text-white/70"><?php echo e(__('Send to every app of your team on iOS, Android and the web: no limits, no per-subscriber bill.')); ?></p>
                </div>
            </div>
        </aside>

        
        <main class="flex flex-1 flex-col">
            <div class="flex flex-1 flex-col justify-center px-5 py-12 sm:px-10">
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mx-auto w-full animate-slide-up', 'max-w-sm' => ! $wide, 'max-w-xl' => $wide]); ?>">
                    <a href="<?php echo e(url('/')); ?>" class="mb-10 inline-flex items-center gap-3 lg:hidden">
                        <?php if (isset($component)) { $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.logo','data' => ['class' => 'size-10']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-10']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $attributes = $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6)): ?>
<?php $component = $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6; ?>
<?php unset($__componentOriginalc9b691e47e4aeaac2320d6494f20beb6); ?>
<?php endif; ?>
                        <span class="text-lg font-semibold tracking-tight"><?php echo e(config('app.name')); ?></span>
                    </a>

                    <?php if($heading): ?>
                        <div class="mb-8">
                            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-white"><?php echo e($heading); ?></h1>
                            <?php if($subheading): ?>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400"><?php echo e($subheading); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php echo e($slot); ?>


                    <?php if(isset($below)): ?>
                        <div class="mt-8 text-sm text-zinc-500 dark:text-zinc-400"><?php echo e($below); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="flex items-center justify-between px-5 py-6 text-xs text-zinc-400 sm:px-10">
                <span><a href="<?php echo e(config('carillon.repository')); ?>" class="font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">Carillon</a> · <?php echo e(__('a project by')); ?> <a href="<?php echo e(config('carillon.publisher_url')); ?>" class="font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><?php echo e(config('carillon.publisher')); ?></a></span>
                <div x-data class="flex gap-1">
                    <?php $__currentLoopData = ['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $theme => $icon): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button" @click="$store.theme.set('<?php echo e($theme); ?>')" class="rounded-md p-1.5 transition hover:text-zinc-700 dark:hover:text-zinc-200" :class="$store.theme.value === '<?php echo e($theme); ?>' && 'text-zinc-800 dark:text-white'">
                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><span class="sr-only"><?php echo e(__(ucfirst($theme))); ?></span>
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </footer>
        </main>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4c772c02301431d3253f64117700596)): ?>
<?php $attributes = $__attributesOriginald4c772c02301431d3253f64117700596; ?>
<?php unset($__attributesOriginald4c772c02301431d3253f64117700596); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4c772c02301431d3253f64117700596)): ?>
<?php $component = $__componentOriginald4c772c02301431d3253f64117700596; ?>
<?php unset($__componentOriginald4c772c02301431d3253f64117700596); ?>
<?php endif; ?>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/components/layouts/guest.blade.php ENDPATH**/ ?>