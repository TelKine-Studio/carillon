<?php $n = fn ($v) => number_format((int) $v, 0, ',', ' '); ?>
<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => __('Audience').' · '.$project->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Audience').' · '.$project->name)]); ?>
    <?php if (isset($component)) { $__componentOriginal91a231a9270579fa1ae9246bd51fb785 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91a231a9270579fa1ae9246bd51fb785 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.page-header','data' => ['title' => __('Audience'),'description' => __('The devices registered by your apps and the people they belong to.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Audience')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('The devices registered by your apps and the people they belong to.'))]); ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('send', $project)): ?>
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','href' => route('projects.segments.index', $project),'icon' => 'layers']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.segments.index', $project)),'icon' => 'layers']); ?><?php echo e(__('Segments')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
        <?php endif; ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91a231a9270579fa1ae9246bd51fb785)): ?>
<?php $attributes = $__attributesOriginal91a231a9270579fa1ae9246bd51fb785; ?>
<?php unset($__attributesOriginal91a231a9270579fa1ae9246bd51fb785); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91a231a9270579fa1ae9246bd51fb785)): ?>
<?php $component = $__componentOriginal91a231a9270579fa1ae9246bd51fb785; ?>
<?php unset($__componentOriginal91a231a9270579fa1ae9246bd51fb785); ?>
<?php endif; ?>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Subscribed devices'),'value' => $n($totals['subscribed']),'icon' => 'smartphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Subscribed devices')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($totals['subscribed'])),'icon' => 'smartphone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('People'),'value' => $n($totals['people']),'icon' => 'users']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('People')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($totals['people'])),'icon' => 'users']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Identified'),'value' => $n($totals['identified']),'icon' => 'key','hint' => __('With an external id')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Identified')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($totals['identified'])),'icon' => 'key','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('With an external id'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Active this week'),'value' => $n($totals['active']),'icon' => 'clock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Active this week')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($totals['active'])),'icon' => 'clock']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
    </div>

    <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" x-data>
        <div class="relative flex-1">
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'search','class' => 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','class' => 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400']); ?>
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
            <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="<?php echo e(__('External id, tag value, device id…')); ?>" class="form-control pl-9">
        </div>
        <div class="grid grid-cols-2 gap-3 sm:flex">
            <?php if (isset($component)) { $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.select','data' => ['name' => 'platform','size' => 'sm','class' => 'sm:w-40','value' => $platform,'options' => ['' => __('All platforms'), 'ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'],'onchange' => 'this.form.submit()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'platform','size' => 'sm','class' => 'sm:w-40','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($platform),'options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['' => __('All platforms'), 'ios' => 'iOS', 'android' => 'Android', 'web' => 'Web']),'onchange' => 'this.form.submit()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $attributes = $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $component = $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.select','data' => ['name' => 'state','size' => 'sm','class' => 'sm:w-44','value' => $state,'options' => ['' => __('All states'), 'subscribed' => __('Subscribed'), 'unsubscribed' => __('Unsubscribed'), 'invalid' => __('Unreachable')],'onchange' => 'this.form.submit()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'state','size' => 'sm','class' => 'sm:w-44','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($state),'options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['' => __('All states'), 'subscribed' => __('Subscribed'), 'unsubscribed' => __('Unsubscribed'), 'invalid' => __('Unreachable')]),'onchange' => 'this.form.submit()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $attributes = $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $component = $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] gap-4 border-b border-zinc-100 px-5 py-2.5 text-xs font-medium text-zinc-500 lg:grid dark:border-zinc-800">
            <span><?php echo e(__('Device')); ?></span><span><?php echo e(__('Person')); ?></span><span><?php echo e(__('Tags')); ?></span><span><?php echo e(__('Last seen')); ?></span><span><?php echo e(__('State')); ?></span>
        </div>
        <?php $__empty_1 = true; $__currentLoopData = $subscriptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subscription): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $person = $subscription->subscriber; ?>
            <a href="<?php echo e(route('projects.subscribers.show', [$project, $person])); ?>" class="grid gap-3 border-t border-zinc-100 px-4 py-3.5 transition first:border-0 hover:bg-zinc-50/80 sm:px-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)_120px_110px] lg:items-center lg:gap-4 dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="flex min-w-0 items-center gap-3">
                    <?php if (isset($component)) { $__componentOriginal28a7ca8fef8cf1faa13d42563e39dfba = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal28a7ca8fef8cf1faa13d42563e39dfba = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audience.device','data' => ['subscription' => $subscription]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audience.device'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['subscription' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($subscription)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal28a7ca8fef8cf1faa13d42563e39dfba)): ?>
<?php $attributes = $__attributesOriginal28a7ca8fef8cf1faa13d42563e39dfba; ?>
<?php unset($__attributesOriginal28a7ca8fef8cf1faa13d42563e39dfba); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal28a7ca8fef8cf1faa13d42563e39dfba)): ?>
<?php $component = $__componentOriginal28a7ca8fef8cf1faa13d42563e39dfba; ?>
<?php unset($__componentOriginal28a7ca8fef8cf1faa13d42563e39dfba); ?>
<?php endif; ?>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($subscription->device_model ?: ucfirst($subscription->type)); ?></p>
                        <p class="truncate text-xs text-zinc-500"><?php echo e(collect([$subscription->device_os, $subscription->app_version ? 'v'.$subscription->app_version : null])->filter()->implode(' · ') ?: '—'); ?></p>
                    </div>
                    <span class="ml-auto lg:hidden"><?php if (isset($component)) { $__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audience.state','data' => ['subscription' => $subscription]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audience.state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['subscription' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($subscription)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0)): ?>
<?php $attributes = $__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0; ?>
<?php unset($__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0)): ?>
<?php $component = $__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0; ?>
<?php unset($__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0); ?>
<?php endif; ?></span>
                </div>
                <p class="min-w-0 truncate text-sm">
                    <?php if($person->external_id): ?>
                        <span class="font-mono text-xs text-zinc-800 dark:text-zinc-200"><?php echo e($person->external_id); ?></span>
                    <?php else: ?>
                        <span class="text-zinc-400"><?php echo e(__('Anonymous')); ?></span>
                    <?php endif; ?>
                </p>
                <div class="flex min-w-0 flex-wrap gap-1">
                    <?php $__currentLoopData = array_slice($person->tags ?? [], 0, 3, true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="inline-flex max-w-full truncate rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400"><?php echo e($key); ?>=<?php echo e($value); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if(count($person->tags ?? []) > 3): ?><span class="text-[11px] text-zinc-400">+<?php echo e(count($person->tags) - 3); ?></span><?php endif; ?>
                </div>
                <p class="text-xs text-zinc-500"><?php echo e($subscription->last_active_at?->diffForHumans() ?? '—'); ?></p>
                <span class="hidden lg:block"><?php if (isset($component)) { $__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audience.state','data' => ['subscription' => $subscription]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audience.state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['subscription' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($subscription)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0)): ?>
<?php $attributes = $__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0; ?>
<?php unset($__attributesOriginal403941eb9c6fc6fe38e336e800e6f0c0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0)): ?>
<?php $component = $__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0; ?>
<?php unset($__componentOriginal403941eb9c6fc6fe38e336e800e6f0c0); ?>
<?php endif; ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty','data' => ['icon' => 'users','title' => $search || $platform || $state ? __('Nobody matches') : __('No device yet'),'description' => $search || $platform || $state ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this project’s App ID.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.empty'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'users','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($search || $platform || $state ? __('Nobody matches') : __('No device yet')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($search || $platform || $state ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this project’s App ID.'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756)): ?>
<?php $attributes = $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756; ?>
<?php unset($__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756)): ?>
<?php $component = $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756; ?>
<?php unset($__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756); ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if($subscriptions->hasPages()): ?>
            <div class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800"><?php echo e($subscriptions->links()); ?></div>
        <?php endif; ?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $attributes = $__attributesOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__attributesOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $component = $__componentOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__componentOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/audience/index.blade.php ENDPATH**/ ?>