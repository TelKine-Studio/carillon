<?php
    $labels = [
        'message.sent' => [__('Message sent'), __('A message has been sent to all its recipients, with its totals.')],
        'notification.received' => [__('Notification received'), __('Shown on a device (reported by the SDK).')],
        'notification.clicked' => [__('Notification clicked'), __('Opened by a person, with the button pressed.')],
        'subscription.created' => [__('New device'), __('A device registered for notifications.')],
        'subscription.unsubscribed' => [__('Device unsubscribed'), __('Notifications turned off, or the app uninstalled.')],
    ];
    $open = session('open_webhook');
?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Webhooks'),'description' => __('Carillon sends a POST request to your server for the events you choose, signed with a secret.'),'icon' => 'link','padding' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Webhooks')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Carillon sends a POST request to your server for the events you choose, signed with a secret.')),'icon' => 'link','padding' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <?php $__empty_1 = true; $__currentLoopData = $webhooks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $webhook): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li x-data="{ open: <?php echo \Illuminate\Support\Js::from($open === $webhook->id)->toHtml() ?> }">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 sm:px-6">
                        <button type="button" class="flex min-w-0 flex-1 basis-64 items-start gap-3 text-left" x-on:click="open = !open" :aria-expanded="open">
                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevron-right','class' => 'mt-0.5 size-4 shrink-0 text-zinc-400 transition',':class' => 'open && \'rotate-90\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-right','class' => 'mt-0.5 size-4 shrink-0 text-zinc-400 transition',':class' => 'open && \'rotate-90\'']); ?>
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
                            <span class="min-w-0">
                                <span class="block truncate font-mono text-sm text-zinc-900 dark:text-white"><?php echo e($webhook->url); ?></span>
                                <span class="mt-1 block text-xs text-zinc-500">
                                    <?php echo e(trans_choice(':count event|:count events', count($webhook->events))); ?>

                                    · <?php echo e($webhook->last_delivery_at ? __('last delivery :date', ['date' => $webhook->last_delivery_at->diffForHumans()]) : __('no delivery yet')); ?>

                                </span>
                            </span>
                        </button>
                        <?php if($webhook->enabled): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['color' => 'green','dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'green','dot' => true]); ?><?php echo e(__('Active')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php elseif($webhook->disabled_reason === 'failures'): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['color' => 'red','dot' => true,'title' => __('Disabled after :count failed events in a row.', ['count' => \App\Models\Webhook::MAX_FAILURES])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'red','dot' => true,'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Disabled after :count failed events in a row.', ['count' => \App\Models\Webhook::MAX_FAILURES]))]); ?><?php echo e(__('Disabled: failures')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php else: ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['dot' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['dot' => true]); ?><?php echo e(__('Disabled')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div x-show="open" x-collapse x-cloak class="space-y-6 border-t border-zinc-100 bg-zinc-50/60 px-5 py-5 sm:px-6 dark:border-zinc-800 dark:bg-white/[0.02]">
                        <?php if($canConfigure): ?>
                            <form method="POST" action="<?php echo e(route('projects.webhooks.update', [$project, $webhook])); ?>" class="space-y-4">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PUT'); ?>
                                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'url','type' => 'url','id' => 'url-'.$webhook->id,'label' => __('Endpoint URL'),'value' => $webhook->url,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'url','type' => 'url','id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('url-'.$webhook->id),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Endpoint URL')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($webhook->url),'required' => true]); ?>
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
                                <fieldset>
                                    <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200"><?php echo e(__('Events')); ?></legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <?php $__currentLoopData = $labels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event => [$label, $text]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-zinc-200 bg-white p-3 transition has-checked:border-brand-500 dark:border-zinc-800 dark:bg-zinc-900">
                                                <input type="checkbox" name="events[]" value="<?php echo e($event); ?>" <?php if($webhook->listensTo($event)): echo 'checked'; endif; ?> class="mt-0.5">
                                                <span class="min-w-0"><span class="block text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($label); ?></span><span class="block font-mono text-[11px] text-zinc-500"><?php echo e($event); ?></span></span>
                                            </label>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </fieldset>
                                <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['size' => 'sm','icon' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'sm','icon' => 'check']); ?><?php echo e(__('Save')); ?> <?php echo $__env->renderComponent(); ?>
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

                            <div>
                                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200"><?php echo e(__('Signing secret')); ?></p>
                                <div class="flex flex-wrap items-center gap-2" x-data="{ shown: false, copied: false }">
                                    <code class="min-w-0 flex-1 truncate rounded-md border border-zinc-200 bg-white px-3 py-2 font-mono text-xs text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300" x-text="shown ? <?php echo \Illuminate\Support\Js::from($webhook->secret)->toHtml() ?> : 'whsec_••••••••••••••••••••'">whsec_••••••••••••••••••••</code>
                                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','size' => 'sm','xOn:click' => 'shown = !shown']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','size' => 'sm','x-on:click' => 'shown = !shown']); ?><span x-text="shown ? <?php echo \Illuminate\Support\Js::from(__('Hide'))->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(__('Show'))->toHtml() ?>"><?php echo e(__('Show')); ?></span> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','size' => 'sm','icon' => 'copy','xOn:click' => 'navigator.clipboard.writeText(@js($webhook->secret)); copied = true; setTimeout(() => copied = false, 1500)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','size' => 'sm','icon' => 'copy','x-on:click' => 'navigator.clipboard.writeText(@js($webhook->secret)); copied = true; setTimeout(() => copied = false, 1500)']); ?><span x-text="copied ? <?php echo \Illuminate\Support\Js::from(__('Copied'))->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(__('Copy'))->toHtml() ?>"><?php echo e(__('Copy')); ?></span> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                                    <form method="POST" action="<?php echo e(route('projects.webhooks.rotate', [$project, $webhook])); ?>" data-confirm-title="<?php echo e(__('Generate a new secret?')); ?>" data-confirm="<?php echo e(__('Your server must use the new one to verify the next events.')); ?>" data-confirm-action="<?php echo e(__('Generate')); ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'ghost','size' => 'sm','icon' => 'refresh']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'ghost','size' => 'sm','icon' => 'refresh']); ?><?php echo e(__('Regenerate')); ?> <?php echo $__env->renderComponent(); ?>
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
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200"><?php echo e(__('Recent attempts')); ?></p>
                            <?php if($webhook->deliveries->isEmpty()): ?>
                                <p class="text-sm text-zinc-500"><?php echo e(__('No attempt yet. Send a test event to check your endpoint.')); ?></p>
                            <?php else: ?>
                                <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <table class="w-full min-w-[480px] text-sm">
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <?php $__currentLoopData = $webhook->deliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $delivery): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr>
                                                    <td class="py-2 pr-3 pl-3.5">
                                                        <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['color' => $delivery->successful() ? 'green' : 'red']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($delivery->successful() ? 'green' : 'red')]); ?><?php echo e($delivery->status ?? __('Error')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                                                    </td>
                                                    <td class="py-2 pr-3 font-mono text-xs text-zinc-700 dark:text-zinc-300"><?php echo e($delivery->event); ?></td>
                                                    <td class="max-w-56 truncate py-2 pr-3 text-xs text-zinc-500" title="<?php echo e($delivery->error); ?>"><?php echo e($delivery->error); ?><?php if($delivery->attempt > 1): ?> <span class="text-zinc-400">· <?php echo e(__('attempt :n', ['n' => $delivery->attempt])); ?></span><?php endif; ?></td>
                                                    <td class="py-2 pr-3 text-right text-xs whitespace-nowrap tabular-nums text-zinc-500"><?php echo e($delivery->duration_ms); ?> ms</td>
                                                    <td class="py-2 pr-3.5 text-right text-xs whitespace-nowrap text-zinc-500"><?php echo e($delivery->created_at->diffForHumans()); ?></td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($canConfigure): ?>
                            <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                                <form method="POST" action="<?php echo e(route('projects.webhooks.test', [$project, $webhook])); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','size' => 'sm','icon' => 'send']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','icon' => 'send']); ?><?php echo e(__('Send a test event')); ?> <?php echo $__env->renderComponent(); ?>
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
                                <form method="POST" action="<?php echo e(route('projects.webhooks.update', [$project, $webhook])); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>
                                    <input type="hidden" name="enabled" value="<?php echo e($webhook->enabled ? 0 : 1); ?>">
                                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','size' => 'sm','icon' => $webhook->enabled ? 'pause' : 'play']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','size' => 'sm','icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($webhook->enabled ? 'pause' : 'play')]); ?><?php echo e($webhook->enabled ? __('Disable') : __('Enable')); ?> <?php echo $__env->renderComponent(); ?>
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
                                <form method="POST" action="<?php echo e(route('projects.webhooks.destroy', [$project, $webhook])); ?>" class="ml-auto" data-confirm-title="<?php echo e(__('Delete this webhook?')); ?>" data-confirm="<?php echo e(__('Your server will no longer receive these events.')); ?>" data-confirm-action="<?php echo e(__('Delete')); ?>">
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
<?php $component->withAttributes(['variant' => 'ghost-danger','size' => 'sm','icon' => 'trash']); ?><?php echo e(__('Delete')); ?> <?php echo $__env->renderComponent(); ?>
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
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li><?php if (isset($component)) { $__componentOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d34c8741b1a71c3623a1c9c1f10e756 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty','data' => ['icon' => 'link','title' => __('No webhook yet'),'description' => __('For example: update your database when a notification is clicked, or clean up devices that unsubscribed.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.empty'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'link','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('No webhook yet')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('For example: update your database when a notification is clicked, or clean up devices that unsubscribed.'))]); ?>
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
        </ul>

        <?php if($canConfigure): ?>
             <?php $__env->slot('footer', null, []); ?> 
                <form method="POST" action="<?php echo e(route('projects.webhooks.store', $project)); ?>" class="w-full space-y-4" x-data="{ adding: <?php echo \Illuminate\Support\Js::from($errors->has('url') || $errors->has('events'))->toHtml() ?> }">
                    <?php echo csrf_field(); ?>
                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','icon' => 'plus','xShow' => '!adding','xOn:click' => 'adding = true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','icon' => 'plus','x-show' => '!adding','x-on:click' => 'adding = true']); ?><?php echo e(__('Add a webhook')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                    <div x-show="adding" x-cloak class="space-y-4">
                        <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'url','type' => 'url','label' => __('Endpoint URL'),'placeholder' => 'https://api.example.com/carillon/webhook','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'url','type' => 'url','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Endpoint URL')),'placeholder' => 'https://api.example.com/carillon/webhook','required' => true]); ?>
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
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200"><?php echo e(__('Events')); ?></legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <?php $__currentLoopData = $labels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event => [$label, $text]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-zinc-200 p-3 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                                        <input type="checkbox" name="events[]" value="<?php echo e($event); ?>" <?php if(in_array($event, old('events', ['message.sent', 'notification.clicked', 'subscription.unsubscribed']), true)): echo 'checked'; endif; ?> class="mt-0.5">
                                        <span class="min-w-0"><span class="block text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($label); ?></span><span class="block text-xs text-zinc-500"><?php echo e($text); ?></span></span>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <?php $__errorArgs = ['events'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-red-600 dark:text-red-400"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </fieldset>
                        <div class="flex gap-2">
                            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['icon' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'plus']); ?><?php echo e(__('Add the webhook')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'ghost','xOn:click' => 'adding = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'ghost','x-on:click' => 'adding = false']); ?><?php echo e(__('Cancel')); ?> <?php echo $__env->renderComponent(); ?>
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
                    </div>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Verifying the signature'),'icon' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Verifying the signature')),'icon' => 'shield']); ?>
        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400"><?php echo __('Each request carries a :header header: compute the HMAC-SHA256 of :signed with the secret and compare it with v1. Reject requests older than 5 minutes.', ['header' => '<code class="font-mono text-xs">Carillon-Signature: t=…,v1=…</code>', 'signed' => '<code class="font-mono text-xs">t + "." + body</code>']); ?></p>
        <?php if (isset($component)) { $__componentOriginal1b324299853ee89c95c87cc1f1320373 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b324299853ee89c95c87cc1f1320373 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.code','data' => ['label' => 'Node.js']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.code'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Node.js']); ?>import crypto from 'node:crypto';

function verify(rawBody, header, secret) {
  const { t, v1 } = Object.fromEntries(header.split(',').map((part) => part.split('=')));
  const expected = crypto.createHmac('sha256', secret).update(`${t}.${rawBody}`).digest('hex');
  const fresh = Math.abs(Date.now() / 1000 - Number(t)) < 300;
  return fresh && crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1));
} <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $attributes = $__attributesOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__attributesOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $component = $__componentOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__componentOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal1b324299853ee89c95c87cc1f1320373 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b324299853ee89c95c87cc1f1320373 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.code','data' => ['label' => 'PHP','class' => 'mt-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.code'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'PHP','class' => 'mt-4']); ?>function verify(string $body, string $header, string $secret): bool
{
    parse_str(str_replace(',', '&', $header), $parts);
    $expected = hash_hmac('sha256', $parts['t'].'.'.$body, $secret);

    return abs(time() - (int) $parts['t']) < 300 && hash_equals($expected, $parts['v1'] ?? '');
} <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $attributes = $__attributesOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__attributesOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b324299853ee89c95c87cc1f1320373)): ?>
<?php $component = $__componentOriginal1b324299853ee89c95c87cc1f1320373; ?>
<?php unset($__componentOriginal1b324299853ee89c95c87cc1f1320373); ?>
<?php endif; ?>
        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400"><?php echo e(__('Answer with a 2xx status within 10 seconds. Failed deliveries are retried 4 times over about 40 minutes; after :count events in a row could not be delivered, the webhook is disabled.', ['count' => \App\Models\Webhook::MAX_FAILURES])); ?></p>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/projects/settings/webhooks.blade.php ENDPATH**/ ?>