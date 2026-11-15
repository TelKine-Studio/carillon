<?php
    $text = [['=', __('is')], ['!=', __('is not')], ['exists', __('is set')], ['not_exists', __('is not set')]];
    $number = [['=', '='], ['!=', '≠'], ['>', '>'], ['<', '<']];
    $config = [
        'countUrl' => route('projects.segments.count', $project),
        'filters' => old('filters') ? json_decode(old('filters'), true) : ($segment->filters ?? []),
        'relations' => [
            'tag' => [['=', __('is')], ['!=', __('is not')], ['>', __('greater than')], ['<', __('less than')], ['exists', __('is set')], ['not_exists', __('is not set')], ['time_elapsed_gt', __('older than (s)')], ['time_elapsed_lt', __('more recent than (s)')]],
            'language' => $text, 'country' => $text, 'app_version' => $number, 'session_count' => $number,
            'external_id' => $text, 'device_type' => [['=', __('is')], ['!=', __('is not')]],
            'last_session' => [['>', __('more than … hours ago')], ['<', __('less than … hours ago')]],
            'first_session' => [['>', __('more than … hours ago')], ['<', __('less than … hours ago')]],
        ],
    ];
    $fields = ['tag' => __('Tag'), 'language' => __('Language'), 'country' => __('Country'), 'app_version' => __('App version'), 'session_count' => __('Sessions'), 'last_session' => __('Last session'), 'first_session' => __('First session'), 'external_id' => __('External id'), 'device_type' => __('Platform')];
?>
<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => ($segment->exists ? $segment->name : __('New segment')).' · '.$project->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($segment->exists ? $segment->name : __('New segment')).' · '.$project->name)]); ?>
    <div class="mx-auto max-w-3xl">
        <?php if (isset($component)) { $__componentOriginal91a231a9270579fa1ae9246bd51fb785 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91a231a9270579fa1ae9246bd51fb785 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.page-header','data' => ['title' => $segment->exists ? $segment->name : __('New segment'),'description' => __('Devices matching every condition of a group; several groups widen the audience (or).')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($segment->exists ? $segment->name : __('New segment')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Devices matching every condition of a group; several groups widen the audience (or).'))]); ?>
             <?php $__env->slot('breadcrumb', null, []); ?> 
                <a href="<?php echo e(route('projects.segments.index', $project)); ?>" class="hover:text-zinc-900 dark:hover:text-white"><?php echo e(__('Segments')); ?></a>
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
                <span><?php echo e($segment->exists ? __('Edit') : __('New')); ?></span>
             <?php $__env->endSlot(); ?>
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

        <form method="POST" action="<?php echo e($segment->exists ? route('projects.segments.update', [$project, $segment]) : route('projects.segments.store', $project)); ?>" x-data="segmentBuilder(<?php echo \Illuminate\Support\Js::from($config)->toHtml() ?>)" class="space-y-6">
            <?php echo csrf_field(); ?>
            <?php if($segment->exists): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
            <input type="hidden" name="filters" :value="json">

            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'name','label' => __('Name'),'value' => $segment->name,'required' => true,'maxlength' => '100','placeholder' => __('e.g. VIP customers')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'name','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Name')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($segment->name),'required' => true,'maxlength' => '100','placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('e.g. VIP customers'))]); ?>
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

            <template x-for="(group, g) in groups" :key="g">
                <div>
                    <div class="my-3 flex items-center gap-3 text-xs font-semibold tracking-wide text-zinc-400 uppercase" x-show="g > 0"><span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span><?php echo e(__('or')); ?><span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span></div>
                    <div class="card p-4 sm:p-5">
                        <template x-for="(condition, c) in group" :key="c">
                            <div>
                                <p class="my-2 text-xs font-semibold tracking-wide text-zinc-400 uppercase" x-show="c > 0"><?php echo e(__('and')); ?></p>
                                <div class="grid gap-2 sm:grid-cols-[150px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <?php if (isset($component)) { $__componentOriginal5fe25b7a8121fa127c0e44d315b1382d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dynamic-select','data' => ['options' => json_encode(collect($fields)->map(fn ($l, $v) => [$v, $l])->values()),'model' => 'condition.field','xOn:change' => '$nextTick(() => fieldChanged(condition))']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dynamic-select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(json_encode(collect($fields)->map(fn ($l, $v) => [$v, $l])->values())),'model' => 'condition.field','x-on:change' => '$nextTick(() => fieldChanged(condition))']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d)): ?>
<?php $attributes = $__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d; ?>
<?php unset($__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5fe25b7a8121fa127c0e44d315b1382d)): ?>
<?php $component = $__componentOriginal5fe25b7a8121fa127c0e44d315b1382d; ?>
<?php unset($__componentOriginal5fe25b7a8121fa127c0e44d315b1382d); ?>
<?php endif; ?>
                                    <input x-show="usesKey(condition.field)" x-model="condition.key" class="form-control font-mono text-sm" placeholder="<?php echo e(__('tag key')); ?>">
                                    <span x-show="!usesKey(condition.field)" class="hidden sm:block"></span>
                                    <?php if (isset($component)) { $__componentOriginal5fe25b7a8121fa127c0e44d315b1382d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dynamic-select','data' => ['options' => 'relations(condition.field)','model' => 'condition.relation']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dynamic-select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['options' => 'relations(condition.field)','model' => 'condition.relation']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d)): ?>
<?php $attributes = $__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d; ?>
<?php unset($__attributesOriginal5fe25b7a8121fa127c0e44d315b1382d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5fe25b7a8121fa127c0e44d315b1382d)): ?>
<?php $component = $__componentOriginal5fe25b7a8121fa127c0e44d315b1382d; ?>
<?php unset($__componentOriginal5fe25b7a8121fa127c0e44d315b1382d); ?>
<?php endif; ?>
                                    <input x-show="needsValue(condition)" x-model="condition.value" class="form-control" placeholder="<?php echo e(__('value')); ?>">
                                    <input x-show="usesHours(condition.field)" x-model="condition.hours_ago" type="number" min="0" step="any" class="form-control" placeholder="<?php echo e(__('hours')); ?>">
                                    <span x-show="!needsValue(condition) && !usesHours(condition.field)" class="hidden sm:block"></span>
                                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'ghost','size' => 'icon','icon' => 'x','class' => 'size-[42px] justify-self-end','xOn:click' => 'group.splice(c, 1); if (!group.length) groups.splice(g, 1); if (!groups.length) groups.push([blank()])']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'ghost','size' => 'icon','icon' => 'x','class' => 'size-[42px] justify-self-end','x-on:click' => 'group.splice(c, 1); if (!group.length) groups.splice(g, 1); if (!groups.length) groups.push([blank()])']); ?><span class="sr-only"><?php echo e(__('Remove')); ?></span> <?php echo $__env->renderComponent(); ?>
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
                        </template>
                        <button type="button" class="mt-4 text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="group.push(blank())">+ <?php echo e(__('And…')); ?></button>
                    </div>
                </div>
            </template>
            <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="groups.push([blank()])">+ <?php echo e(__('Or another group')); ?></button>

            <?php $__errorArgs = ['filters'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

            <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400"><?php echo e(__('Matching devices')); ?></p>
                    <p class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white"><span x-text="count === null ? '—' : new Intl.NumberFormat(document.documentElement.lang).format(count)"></span></p>
                    <p class="text-xs text-amber-700 dark:text-amber-400" x-show="error" x-text="error" x-cloak></p>
                </div>
                <div class="flex gap-3">
                    <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['variant' => 'secondary','href' => route('projects.segments.index', $project)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'secondary','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.segments.index', $project))]); ?><?php echo e(__('Cancel')); ?> <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['icon' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'check']); ?><?php echo e($segment->exists ? __('Save') : __('Create the segment')); ?> <?php echo $__env->renderComponent(); ?>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/segments/form.blade.php ENDPATH**/ ?>