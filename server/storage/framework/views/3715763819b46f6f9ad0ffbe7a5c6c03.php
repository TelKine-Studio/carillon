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
    <form method="POST" action="<?php echo e(route('projects.update', $project)); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Project'),'icon' => 'settings']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Project')),'icon' => 'settings']); ?>
            <fieldset <?php if(! $canConfigure): echo 'disabled'; endif; ?> class="space-y-6">
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'name','label' => __('Name'),'value' => $project->name,'required' => true,'maxlength' => '80']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'name','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Name')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project->name),'required' => true,'maxlength' => '80']); ?>
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
                <div class="flex items-start gap-4">
                    <?php if (isset($component)) { $__componentOriginal36407694a283dbdf6dc29360724d3fd0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36407694a283dbdf6dc29360724d3fd0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.project-icon','data' => ['project' => $project,'size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.project-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project),'size' => 'lg']); ?>
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
                    <div class="min-w-0 flex-1 space-y-3">
                        <?php if (isset($component)) { $__componentOriginaldc723fd52a2a983147bc24be67950fe5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldc723fd52a2a983147bc24be67950fe5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.file','data' => ['name' => 'icon','accept' => 'image/png,image/jpeg,image/webp','label' => __('Icon'),'hint' => __('PNG, JPEG or WebP, 1 MB maximum.'),'optional' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.file'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'icon','accept' => 'image/png,image/jpeg,image/webp','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Icon')),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('PNG, JPEG or WebP, 1 MB maximum.')),'optional' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldc723fd52a2a983147bc24be67950fe5)): ?>
<?php $attributes = $__attributesOriginaldc723fd52a2a983147bc24be67950fe5; ?>
<?php unset($__attributesOriginaldc723fd52a2a983147bc24be67950fe5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldc723fd52a2a983147bc24be67950fe5)): ?>
<?php $component = $__componentOriginaldc723fd52a2a983147bc24be67950fe5; ?>
<?php unset($__componentOriginaldc723fd52a2a983147bc24be67950fe5); ?>
<?php endif; ?>
                        <?php if($project->icon_path): ?>
                            <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400"><input type="checkbox" name="remove_icon" value="1"> <?php echo e(__('Remove the icon')); ?></label>
                        <?php endif; ?>
                    </div>
                </div>
            </fieldset>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['class' => 'mt-6','title' => __('Defaults'),'description' => __('Used when a message does not specify them.'),'icon' => 'sliders']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Defaults')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Used when a message does not specify them.')),'icon' => 'sliders']); ?>
            <fieldset <?php if(! $canConfigure): echo 'disabled'; endif; ?> class="grid gap-5 sm:grid-cols-2">
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'default_url','type' => 'url','label' => __('Default link'),'value' => $project->setting('default_url'),'placeholder' => 'https://','hint' => __('Opened when a notification without link is tapped (web).'),'optional' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'default_url','type' => 'url','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Default link')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project->setting('default_url')),'placeholder' => 'https://','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Opened when a notification without link is tapped (web).')),'optional' => true]); ?>
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
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'default_ttl','type' => 'number','min' => '0','max' => '2419200','label' => __('Time to live (seconds)'),'value' => $project->setting('default_ttl'),'placeholder' => '259200','hint' => __('How long Apple and Google keep trying to deliver to a device that is offline. 3 days by default.'),'optional' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'default_ttl','type' => 'number','min' => '0','max' => '2419200','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Time to live (seconds)')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project->setting('default_ttl')),'placeholder' => '259200','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('How long Apple and Google keep trying to deliver to a device that is offline. 3 days by default.')),'optional' => true]); ?>
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
            </fieldset>
            <?php if($canConfigure): ?>
                 <?php $__env->slot('footer', null, []); ?> <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('Save')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?> <?php $__env->endSlot(); ?>
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
    </form>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $project)): ?>
        <section class="rounded-2xl border border-red-200 bg-red-50/40 p-5 sm:p-6 dark:border-red-500/20 dark:bg-red-500/5" x-data="{ open: <?php echo e($errors->has('confirm_name') ? 'true' : 'false'); ?> }">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-red-900 dark:text-red-200"><?php echo e(__('Delete this project')); ?></h2>
                    <p class="mt-1 text-sm text-red-800/80 dark:text-red-200/70"><?php echo e(__('Its subscribers, messages, statistics and keys are deleted permanently. Apps using its App ID stop receiving notifications.')); ?></p>
                </div>
                <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'danger','icon' => 'trash','xOn:click' => 'open = true','xShow' => '!open']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'danger','icon' => 'trash','x-on:click' => 'open = true','x-show' => '!open']); ?><?php echo e(__('Delete the project')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
            </div>
            <form method="POST" action="<?php echo e(route('projects.destroy', $project)); ?>" x-show="open" x-cloak class="mt-5 flex flex-wrap items-end gap-3">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'confirm_name','label' => __('Type :name to confirm', ['name' => $project->name]),'class' => 'min-w-60 flex-1','autocomplete' => 'off']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'confirm_name','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Type :name to confirm', ['name' => $project->name])),'class' => 'min-w-60 flex-1','autocomplete' => 'off']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'danger','icon' => 'trash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'danger','icon' => 'trash']); ?><?php echo e(__('Delete permanently')); ?> <?php echo $__env->renderComponent(); ?>
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
        </section>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/projects/settings/general.blade.php ENDPATH**/ ?>