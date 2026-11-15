<?php
    $tabs = ['' => __('All'), 'sent' => __('Sent'), 'scheduled' => __('Scheduled'), 'sending' => __('Sending'), 'canceled' => __('Canceled')];
?>
<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => __('Messages').' · '.$project->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Messages').' · '.$project->name)]); ?>
    <?php if (isset($component)) { $__componentOriginal91a231a9270579fa1ae9246bd51fb785 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91a231a9270579fa1ae9246bd51fb785 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.page-header','data' => ['title' => __('Messages'),'description' => __('Notifications sent from the dashboard and the API.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Messages')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Notifications sent from the dashboard and the API.'))]); ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('send', $project)): ?>
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['href' => route('projects.messages.create', $project),'icon' => 'send']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.messages.create', $project)),'icon' => 'send']); ?><?php echo e(__('New message')); ?> <?php echo $__env->renderComponent(); ?>
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

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0">
            <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0); ?>
                <a href="<?php echo e(route('projects.messages.index', [$project, 'status' => $key ?: null, 'q' => $search ?: null])); ?>"
                    class="<?php echo \Illuminate\Support\Arr::toCssClasses(['inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition', 'bg-white text-zinc-900 shadow-(--shadow-soft) ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-white dark:ring-zinc-800' => (string) $status === $key, 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' => (string) $status !== $key]); ?>">
                    <?php echo e($label); ?><span class="text-xs text-zinc-400 tabular-nums"><?php echo e(number_format($count, 0, ',', ' ')); ?></span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
        <form method="GET" class="relative sm:w-72">
            <?php if($status): ?><input type="hidden" name="status" value="<?php echo e($status); ?>"><?php endif; ?>
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
            <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="<?php echo e(__('Search messages…')); ?>" class="form-control pl-9">
        </form>
    </div>

    <div class="card overflow-hidden">
        <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <a href="<?php echo e(route('projects.messages.show', [$project, $message])); ?>" class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-4 transition first:border-0 hover:bg-zinc-50/80 sm:px-5 lg:flex-row lg:items-center lg:gap-6 dark:border-zinc-800 dark:hover:bg-white/[.02]">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white"><?php echo e($message->label()); ?></p>
                        <?php if (isset($component)) { $__componentOriginal265ec32edd8b2457cacac492b237409d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal265ec32edd8b2457cacac492b237409d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.messages.status','data' => ['status' => $message->status,'class' => 'shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('messages.status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->status),'class' => 'shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal265ec32edd8b2457cacac492b237409d)): ?>
<?php $attributes = $__attributesOriginal265ec32edd8b2457cacac492b237409d; ?>
<?php unset($__attributesOriginal265ec32edd8b2457cacac492b237409d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal265ec32edd8b2457cacac492b237409d)): ?>
<?php $component = $__componentOriginal265ec32edd8b2457cacac492b237409d; ?>
<?php unset($__componentOriginal265ec32edd8b2457cacac492b237409d); ?>
<?php endif; ?>
                    </div>
                    <?php if($message->name || \App\Models\Message::localized($message->headings, app()->getLocale())): ?>
                        <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400"><?php echo e(\App\Models\Message::localized($message->contents, app()->getLocale())); ?></p>
                    <?php endif; ?>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-zinc-400">
                        <span><?php echo e($message->audienceLabel()); ?></span>
                        <span>·</span>
                        <span><?php echo e($message->status === 'scheduled' ? __('scheduled :date', ['date' => $message->send_after->diffForHumans()]) : $message->created_at->diffForHumans()); ?></span>
                        <span>·</span>
                        <span><?php echo e($message->author?->name ?? ($message->api_key_id ? __('API') : '—')); ?></span>
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-4 text-sm lg:w-80 lg:shrink-0">
                    <div>
                        <dt class="text-xs text-zinc-400"><?php echo e(__('Recipients')); ?></dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white"><?php echo e(number_format($message->recipients, 0, ',', ' ')); ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400"><?php echo e(__('Delivered')); ?></dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white"><?php echo e(number_format($message->successful, 0, ',', ' ')); ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400"><?php echo e(__('Clicks')); ?></dt>
                        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-white"><?php echo e($message->rate($message->clicked) !== null ? number_format($message->rate($message->clicked), 1, ',', ' ').' %' : '—'); ?></dd>
                    </div>
                </dl>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty','data' => ['icon' => 'send','title' => $search || $status ? __('No message matches') : __('No message yet'),'description' => $search || $status ? __('Try another search or status.') : __('Write your first notification, or send one with the API.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.empty'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'send','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($search || $status ? __('No message matches') : __('No message yet')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($search || $status ? __('Try another search or status.') : __('Write your first notification, or send one with the API.'))]); ?>
                <?php if(! $search && ! $status): ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('send', $project)): ?>
                        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['href' => route('projects.messages.create', $project),'icon' => 'send']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.messages.create', $project)),'icon' => 'send']); ?><?php echo e(__('New message')); ?> <?php echo $__env->renderComponent(); ?>
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
                <?php endif; ?>
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

        <?php if($messages->hasPages()): ?>
            <div class="border-t border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-800"><?php echo e($messages->links()); ?></div>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/messages/index.blade.php ENDPATH**/ ?>