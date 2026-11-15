<?php
    $lang = app()->getLocale();
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn ($part, $total) => $total > 0 ? number_format($part / $total * 100, 1, ',', ' ').' %' : '—';
    $live = in_array($message->status, ['queued', 'sending'], true);
    $platformNames = ['ios' => ['iOS', 'apple'], 'android' => ['Android', 'android'], 'web' => ['Web', 'globe-web']];
    $preview = [
        'title' => \App\Models\Message::localized($message->headings, $lang),
        'subtitle' => \App\Models\Message::localized($message->subtitle, $lang),
        'body' => \App\Models\Message::localized($message->contents, $lang),
        'image' => $message->option('big_picture'),
        'buttons' => collect($message->option('buttons', []))->pluck('text')->all(),
    ];
?>
<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => $message->label()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->label())]); ?>
    <?php if($live): ?>
        
        <meta http-equiv="refresh" content="4">
    <?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal91a231a9270579fa1ae9246bd51fb785 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91a231a9270579fa1ae9246bd51fb785 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.page-header','data' => ['title' => $message->label()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->label())]); ?>
         <?php $__env->slot('breadcrumb', null, []); ?> 
            <a href="<?php echo e(route('projects.messages.index', $project)); ?>" class="hover:text-zinc-900 dark:hover:text-white"><?php echo e(__('Messages')); ?></a>
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevron-right','class' => 'size-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-right','class' => 'size-3.5']); ?>
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
            <span class="font-mono text-xs"><?php echo e(\Illuminate\Support\Str::limit($message->id, 8, '')); ?></span>
         <?php $__env->endSlot(); ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('send', $project)): ?>
            <?php if($message->isCancelable()): ?>
                <form method="POST" action="<?php echo e(route('projects.messages.cancel', [$project, $message])); ?>" data-confirm-title="<?php echo e(__('Cancel this message?')); ?>" data-confirm="<?php echo e(__('It will not be sent.')); ?>" data-confirm-action="<?php echo e(__('Cancel the message')); ?>">
                    <?php echo csrf_field(); ?>
                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','icon' => 'x']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','icon' => 'x']); ?><?php echo e(__('Cancel')); ?> <?php echo $__env->renderComponent(); ?>
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
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','href' => route('projects.messages.create', [$project, 'from' => $message->id]),'icon' => 'copy']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.messages.create', [$project, 'from' => $message->id])),'icon' => 'copy']); ?><?php echo e(__('Duplicate')); ?> <?php echo $__env->renderComponent(); ?>
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

    <div class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
        <?php if (isset($component)) { $__componentOriginal265ec32edd8b2457cacac492b237409d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal265ec32edd8b2457cacac492b237409d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.messages.status','data' => ['status' => $message->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('messages.status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->status)]); ?>
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
        <span><?php echo e($message->audienceLabel()); ?></span>
        <span>·</span>
        <span>
            <?php if($message->status === 'scheduled'): ?>
                <?php echo e(__('Scheduled for :date', ['date' => $message->send_after->isoFormat('LLL')])); ?>

            <?php elseif($message->completed_at): ?>
                <?php echo e(__('Sent :date', ['date' => $message->completed_at->isoFormat('LLL')])); ?>

            <?php else: ?>
                <?php echo e(__('Created :date', ['date' => $message->created_at->isoFormat('LLL')])); ?>

            <?php endif; ?>
        </span>
        <span>·</span>
        <span><?php echo e($message->author?->name ?? ($message->api_key_id ? __('Sent with the API') : '—')); ?></span>
    </div>

    <?php if($message->error): ?>
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'alert','class' => 'mt-0.5 size-4 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'alert','class' => 'mt-0.5 size-4 shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><?php echo e($message->error); ?>

        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Recipients'),'value' => $n($message->recipients),'icon' => 'users','hint' => $live ? __(':count remaining', ['count' => $n($message->remaining())]) : null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Recipients')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($message->recipients)),'icon' => 'users','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($live ? __(':count remaining', ['count' => $n($message->remaining())]) : null)]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Delivered'),'value' => $n($message->successful),'icon' => 'send','hint' => __(':rate of recipients', ['rate' => $pct($message->successful, $message->recipients)])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delivered')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($message->successful)),'icon' => 'send','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__(':rate of recipients', ['rate' => $pct($message->successful, $message->recipients)]))]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Received'),'value' => $n($message->received),'icon' => 'smartphone','hint' => __(':rate confirmed by the SDK', ['rate' => $pct($message->received, $message->successful)])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Received')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($message->received)),'icon' => 'smartphone','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__(':rate confirmed by the SDK', ['rate' => $pct($message->received, $message->successful)]))]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['label' => __('Clicked'),'value' => $n($message->clicked),'icon' => 'external','hint' => __(':rate click rate', ['rate' => $pct($message->clicked, $message->successful)])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Clicked')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($n($message->clicked)),'icon' => 'external','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__(':rate click rate', ['rate' => $pct($message->clicked, $message->successful)]))]); ?>
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

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div class="min-w-0 space-y-6">
            
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Delivery'),'icon' => 'layers']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delivery')),'icon' => 'layers']); ?>
                <?php $max = max(1, $message->recipients); ?>
                <div class="space-y-3">
                    <?php $__currentLoopData = [
                        [__('Recipients'), $message->recipients, 'bg-zinc-300 dark:bg-zinc-600'],
                        [__('Delivered'), $message->successful, 'bg-brand-500'],
                        [__('Received'), $message->received, 'bg-brand-400'],
                        [__('Clicked'), $message->clicked, 'bg-emerald-500'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="grid grid-cols-[96px_minmax(0,1fr)_72px] items-center gap-3 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400"><?php echo e($label); ?></span>
                            <span class="h-2 overflow-hidden rounded-sm bg-zinc-100 dark:bg-white/5"><span class="block h-full rounded-sm <?php echo e($color); ?>" style="width: <?php echo e(round($value / $max * 100, 2)); ?>%"></span></span>
                            <span class="text-right font-medium tabular-nums text-zinc-900 dark:text-white"><?php echo e($n($value)); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php if($message->failed || $message->errored): ?>
                    <p class="mt-5 flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 pt-4 text-sm text-zinc-500 dark:border-zinc-800">
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200"><?php echo e($n($message->failed)); ?></strong> <?php echo e(__('refused (devices disabled)')); ?></span>
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200"><?php echo e($n($message->errored)); ?></strong> <?php echo e(__('errors')); ?></span>
                    </p>
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

            
            <?php if($message->platformStats->isNotEmpty()): ?>
                <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('By platform'),'icon' => 'smartphone','padding' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('By platform')),'icon' => 'smartphone','padding' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-left text-xs text-zinc-500 dark:border-zinc-800">
                                    <th class="px-5 py-2.5 font-medium sm:px-6"><?php echo e(__('Platform')); ?></th>
                                    <?php $__currentLoopData = [__('Recipients'), __('Delivered'), __('Refused'), __('Errors'), __('Clicked')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <th class="px-3 py-2.5 text-right font-medium"><?php echo e($heading); ?></th>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                <?php $__currentLoopData = $message->platformStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td class="px-5 py-3 sm:px-6"><span class="inline-flex items-center gap-2 font-medium text-zinc-900 dark:text-white"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $platformNames[$stat->platform][1] ?? 'smartphone','class' => 'size-4 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($platformNames[$stat->platform][1] ?? 'smartphone'),'class' => 'size-4 text-zinc-400']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><?php echo e($platformNames[$stat->platform][0] ?? $stat->platform); ?></span></td>
                                        <?php $__currentLoopData = ['recipients', 'successful', 'failed', 'errored', 'clicked']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <td class="px-3 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300"><?php echo e($n($stat->{$column})); ?></td>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
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
            <?php endif; ?>

            <?php if($errors_summary->isNotEmpty()): ?>
                <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Errors'),'description' => __('Reasons given by Apple, Google or the browsers.'),'icon' => 'alert','padding' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Errors')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Reasons given by Apple, Google or the browsers.')),'icon' => 'alert','padding' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <?php $__currentLoopData = $errors_summary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm sm:px-6">
                                <span class="min-w-0 truncate font-mono text-xs text-zinc-700 dark:text-zinc-300"><?php echo e($row->error); ?></span>
                                <span class="shrink-0 font-medium tabular-nums text-zinc-900 dark:text-white"><?php echo e($n($row->total)); ?></span>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
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
            <?php endif; ?>

            
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Content'),'icon' => 'message']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Content')),'icon' => 'message']); ?>
                <dl class="space-y-4 text-sm">
                    <?php $__currentLoopData = $message->contents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $language => $body): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                            <p class="mb-2 font-mono text-xs text-zinc-400 uppercase"><?php echo e($language); ?></p>
                            <?php if($title = $message->headings[$language] ?? null): ?><p class="font-semibold text-zinc-900 dark:text-white"><?php echo e($title); ?></p><?php endif; ?>
                            <?php if($subtitle = $message->subtitle[$language] ?? null): ?><p class="font-medium text-zinc-700 dark:text-zinc-300"><?php echo e($subtitle); ?></p><?php endif; ?>
                            <p class="whitespace-pre-line text-zinc-700 dark:text-zinc-300"><?php echo e($body); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $details = array_filter([
                            __('Link') => $message->option('url'),
                            __('Image') => $message->option('big_picture'),
                            __('Collapse id') => $message->option('collapse_id'),
                            __('Priority') => $message->option('priority') ? ((int) $message->option('priority') >= 10 ? __('High') : __('Normal')) : null,
                            __('Time to live (seconds)') => $message->option('ttl'),
                        ], fn ($v) => $v !== null && $v !== '');
                    ?>
                    <?php if($details || $message->option('data')): ?>
                        <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <?php $__currentLoopData = $details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="min-w-0"><dt class="text-xs text-zinc-500"><?php echo e($label); ?></dt><dd class="truncate text-zinc-800 dark:text-zinc-200"><?php echo e($value); ?></dd></div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php endif; ?>
                    <?php if($message->option('data')): ?>
                        <?php if (isset($component)) { $__componentOriginal1b324299853ee89c95c87cc1f1320373 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b324299853ee89c95c87cc1f1320373 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.code','data' => ['label' => __('Additional data')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.code'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Additional data'))]); ?><?php echo e(json_encode($message->option('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $attributes = $__attributesOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__attributesOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $component = $__componentOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__componentOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
                    <?php endif; ?>
                </dl>
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
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6">
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Preview'),'icon' => 'smartphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Preview')),'icon' => 'smartphone']); ?>
                <div x-data="{ preview: <?php echo \Illuminate\Support\Js::from($preview)->toHtml() ?> }">
                    <?php if (isset($component)) { $__componentOriginale2cb43cb1485e58da270fb2618c66dbe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale2cb43cb1485e58da270fb2618c66dbe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.messages.preview','data' => ['project' => $project]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('messages.preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale2cb43cb1485e58da270fb2618c66dbe)): ?>
<?php $attributes = $__attributesOriginale2cb43cb1485e58da270fb2618c66dbe; ?>
<?php unset($__attributesOriginale2cb43cb1485e58da270fb2618c66dbe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale2cb43cb1485e58da270fb2618c66dbe)): ?>
<?php $component = $__componentOriginale2cb43cb1485e58da270fb2618c66dbe; ?>
<?php unset($__componentOriginale2cb43cb1485e58da270fb2618c66dbe); ?>
<?php endif; ?>
                </div>
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
        </aside>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/messages/show.blade.php ENDPATH**/ ?>