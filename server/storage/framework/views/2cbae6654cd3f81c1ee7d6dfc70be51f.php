<?php if (isset($component)) { $__componentOriginal53cabdff9b70544ec3bf35a9473bde38 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53cabdff9b70544ec3bf35a9473bde38 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.projects.settings','data' => ['project' => $project,'section' => $section]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('projects.settings'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project),'section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($section)]); ?>
    <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('API keys'),'description' => __('Your server sends notifications and manages subscribers with these keys. Keep them secret: never put them in an app.'),'icon' => 'key','padding' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('API keys')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Your server sends notifications and manages subscribers with these keys. Keep them secret: never put them in an app.')),'icon' => 'key','padding' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
        <?php if(session('plain_api_key')): ?>
            <div class="border-b border-amber-200 bg-amber-50 p-5 sm:px-6 dark:border-amber-500/20 dark:bg-amber-500/10">
                <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'alert','class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'alert','class' => 'size-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><?php echo e(__('Copy this key now: it will not be shown again.')); ?></p>
                <?php if (isset($component)) { $__componentOriginal1b324299853ee89c95c87cc1f1320373 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b324299853ee89c95c87cc1f1320373 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.code','data' => ['label' => __('API key')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.code'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('API key'))]); ?><?php echo e(session('plain_api_key')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $attributes = $__attributesOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__attributesOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $component = $__componentOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__componentOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
            </div>
        <?php endif; ?>

        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <?php $__empty_1 = true; $__currentLoopData = $apiKeys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-500 dark:bg-white/5"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'key','class' => 'size-[18px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'key','class' => 'size-[18px]']); ?>
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
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($key->name); ?></p>
                        <p class="text-xs text-zinc-500">
                            <span class="font-mono"><?php echo e($key->prefix); ?>…</span>
                            · <?php echo e(__('created :date', ['date' => $key->created_at->diffForHumans()])); ?><?php if($key->creator): ?> <?php echo e(__('by :name', ['name' => $key->creator->name])); ?><?php endif; ?>
                            · <?php echo e($key->last_used_at ? __('last used :date', ['date' => $key->last_used_at->diffForHumans()]) : __('never used')); ?>

                        </p>
                    </div>
                    <?php if($canConfigure): ?>
                        <form method="POST" action="<?php echo e(route('projects.api-keys.destroy', [$project, $key])); ?>" data-confirm-title="<?php echo e(__('Revoke this key?')); ?>" data-confirm="<?php echo e(__('Servers using it can no longer send notifications.')); ?>" data-confirm-action="<?php echo e(__('Revoke')); ?>">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'ghost-danger','size' => 'sm','icon' => 'trash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'ghost-danger','size' => 'sm','icon' => 'trash']); ?><?php echo e(__('Revoke')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (! (session('plain_api_key'))): ?>
                    <li><?php if (isset($component)) { $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty','data' => ['icon' => 'key','title' => __('No API key yet'),'description' => __('Create one per server or service, so you can revoke them separately.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.empty'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'key','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('No API key yet')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Create one per server or service, so you can revoke them separately.'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756)): ?>
<?php $attributes = $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756; ?>
<?php unset($__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756)): ?>
<?php $component = $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756; ?>
<?php unset($__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756); ?>
<?php endif; ?></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <?php if($canConfigure): ?>
             <?php $__env->slot('footer', null, []); ?> 
                <form method="POST" action="<?php echo e(route('projects.api-keys.store', $project)); ?>" class="flex w-full flex-wrap items-end gap-3">
                    <?php echo csrf_field(); ?>
                    <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'name','label' => __('New key'),'placeholder' => __('e.g. Production backend'),'required' => true,'maxlength' => '80','class' => 'min-w-60 flex-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'name','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('New key')),'placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('e.g. Production backend')),'required' => true,'maxlength' => '80','class' => 'min-w-60 flex-1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['icon' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'plus']); ?><?php echo e(__('Create key')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                </form>
             <?php $__env->endSlot(); ?>
        <?php endif; ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

    <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Using a key'),'icon' => 'code']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Using a key')),'icon' => 'code']); ?>
        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400"><?php echo e(__('Send it in the Authorization header, like OneSignal:')); ?></p>
        <?php if (isset($component)) { $__componentOriginal1b324299853ee89c95c87cc1f1320373 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b324299853ee89c95c87cc1f1320373 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.code','data' => ['label' => 'curl']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.code'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'curl']); ?>curl -X POST <?php echo e(rtrim(config('app.url'), '/')); ?>/api/v1/notifications \
  -H "Authorization: Key YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"app_id": "<?php echo e($project->app_id); ?>", "included_segments": ["All"], "contents": {"en": "Hello!"}}' <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $attributes = $__attributesOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__attributesOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $component = $__componentOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__componentOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53cabdff9b70544ec3bf35a9473bde38)): ?>
<?php $attributes = $__attributesOriginal53cabdff9b70544ec3bf35a9473bde38; ?>
<?php unset($__attributesOriginal53cabdff9b70544ec3bf35a9473bde38); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53cabdff9b70544ec3bf35a9473bde38)): ?>
<?php $component = $__componentOriginal53cabdff9b70544ec3bf35a9473bde38; ?>
<?php unset($__componentOriginal53cabdff9b70544ec3bf35a9473bde38); ?>
<?php endif; ?>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/projects/settings/api-keys.blade.php ENDPATH**/ ?>