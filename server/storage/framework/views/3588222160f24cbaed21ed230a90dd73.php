
<?php
    $user = auth()->user();
    $current = request()->route('project');
    $current = $current instanceof \App\Models\Project ? $current : null;
    // Sections of a project; each appears once its pages exist.
    $sections = collect([
        ['route' => 'projects.show', 'match' => 'projects.show', 'icon' => 'home', 'label' => __('Overview')],
        ['route' => 'projects.messages.index', 'match' => 'projects.messages.*', 'icon' => 'send', 'label' => __('Messages')],
        ['route' => 'projects.subscribers.index', 'match' => 'projects.subscribers.*', 'icon' => 'users', 'label' => __('Audience')],
        ['route' => 'projects.segments.index', 'match' => 'projects.segments.*', 'icon' => 'layers', 'label' => __('Segments')],
        ['route' => 'projects.templates.index', 'match' => 'projects.templates.*', 'icon' => 'file', 'label' => __('Templates')],
        ['route' => 'projects.settings', 'match' => 'projects.settings*', 'icon' => 'settings', 'label' => __('Settings')],
    ])->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']));
    $admin = [
        ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'icon' => 'users', 'label' => __('Users'), 'count' => $nav['invitations'] ?: null],
        ['route' => 'admin.audit', 'match' => 'admin.audit', 'icon' => 'shield', 'label' => __('Audit log')],
        ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'icon' => 'settings', 'label' => __('Settings')],
    ];
?>
<div class="flex h-full flex-col">
    <a href="<?php echo e(route('projects.index')); ?>" class="flex items-center gap-3 px-5 pt-6 pb-5">
        <?php if (isset($component)) { $__componentOriginalc9b691e47e4aeaac2320d6494f20beb6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc9b691e47e4aeaac2320d6494f20beb6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.logo','data' => ['class' => 'size-9']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-9']); ?>
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
        <span class="min-w-0">
            <span class="block truncate text-[15px] font-semibold tracking-tight text-zinc-900 dark:text-white"><?php echo e(config('app.name')); ?></span>
            <span class="block text-xs text-zinc-500"><?php echo e(__('Push notifications')); ?></span>
        </span>
    </a>

    <nav class="flex-1 space-y-7 overflow-y-auto px-3 pb-4">
        
        <div class="space-y-1">
            <?php if (isset($component)) { $__componentOriginaleea726fa4f84deb9f7684b50bdd6328c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown','data' => ['width' => 'w-full']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['width' => 'w-full']); ?>
                 <?php $__env->slot('trigger', null, []); ?> 
                    <button type="button" class="flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white p-2 text-left shadow-xs transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                        <?php if($current): ?>
                            <?php if (isset($component)) { $__componentOriginal36407694a283dbdf6dc29360724d3fd0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36407694a283dbdf6dc29360724d3fd0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.project-icon','data' => ['project' => $current,'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.project-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($current),'size' => 'sm']); ?>
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
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white"><?php echo e($current->name); ?></span>
                                <span class="block text-xs text-zinc-500"><?php echo e(__('Project')); ?></span>
                            </span>
                        <?php else: ?>
                            <span class="grid size-8 place-items-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'grid','class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'grid','class' => 'size-4']); ?>
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
                            <span class="min-w-0 flex-1 text-sm font-semibold text-zinc-900 dark:text-white"><?php echo e(__('All projects')); ?></span>
                        <?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevrons-up-down','class' => 'size-4 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevrons-up-down','class' => 'size-4 text-zinc-400']); ?>
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
                    </button>
                 <?php $__env->endSlot(); ?>

                <?php $__currentLoopData = $nav['projects']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['href' => route('projects.show', $project)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.show', $project))]); ?>
                        <span class="flex items-center gap-2.5">
                            <?php if (isset($component)) { $__componentOriginal36407694a283dbdf6dc29360724d3fd0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36407694a283dbdf6dc29360724d3fd0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.project-icon','data' => ['project' => $project,'size' => 'xs']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.project-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['project' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($project),'size' => 'xs']); ?>
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
                            <span class="truncate"><?php echo e($project->name); ?></span>
                            <?php if($current?->is($project)): ?><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'check','class' => 'ml-auto size-4 text-brand-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'ml-auto size-4 text-brand-600']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?><?php endif; ?>
                        </span>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if($nav['projects']->isNotEmpty()): ?><div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['href' => route('projects.index'),'icon' => 'grid']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.index')),'icon' => 'grid']); ?><?php echo e(__('All projects')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', \App\Models\Project::class)): ?>
                    <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['href' => route('projects.create'),'icon' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('projects.create')),'icon' => 'plus']); ?><?php echo e(__('New project')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
                <?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c)): ?>
<?php $attributes = $__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c; ?>
<?php unset($__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleea726fa4f84deb9f7684b50bdd6328c)): ?>
<?php $component = $__componentOriginaleea726fa4f84deb9f7684b50bdd6328c; ?>
<?php unset($__componentOriginaleea726fa4f84deb9f7684b50bdd6328c); ?>
<?php endif; ?>
        </div>

        <?php if($current): ?>
            <div class="space-y-1">
                <?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route($item['route'], $current)); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])]); ?>">
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $item['icon'],'class' => 'size-[18px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'class' => 'size-[18px]']); ?>
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
                        <span class="flex-1"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin')): ?>
            <div class="space-y-1">
                <p class="eyebrow px-3 pb-1.5"><?php echo e(__('Administration')); ?></p>
                <?php $__currentLoopData = $admin; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route($item['route'])); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])]); ?>">
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $item['icon'],'class' => 'size-[18px]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'class' => 'size-[18px]']); ?>
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
                        <span class="flex-1"><?php echo e($item['label']); ?></span>
                        <?php if($item['count'] ?? null): ?>
                            <span class="rounded-md bg-brand-100 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700 tabular-nums dark:bg-brand-500/15 dark:text-brand-300"><?php echo e($item['count']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </nav>

    <div class="space-y-3 p-3">
        <p class="px-2 text-[11px] text-zinc-400 dark:text-zinc-600">
            Carillon <?php echo e(config('carillon.version')); ?> · <?php echo e(__('a project by')); ?> <a href="<?php echo e(config('carillon.publisher_url')); ?>" class="font-medium hover:text-zinc-600 dark:hover:text-zinc-400"><?php echo e(config('carillon.publisher')); ?></a>
        </p>

        <?php if (isset($component)) { $__componentOriginaleea726fa4f84deb9f7684b50bdd6328c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown','data' => ['align' => 'top','width' => 'w-full']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['align' => 'top','width' => 'w-full']); ?>
             <?php $__env->slot('trigger', null, []); ?> 
                <button type="button" class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-zinc-100 dark:hover:bg-white/5">
                    <?php if (isset($component)) { $__componentOriginald04dd79f9e235eb8e58dee4526a2f3c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald04dd79f9e235eb8e58dee4526a2f3c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.avatar','data' => ['user' => $user]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald04dd79f9e235eb8e58dee4526a2f3c2)): ?>
<?php $attributes = $__attributesOriginald04dd79f9e235eb8e58dee4526a2f3c2; ?>
<?php unset($__attributesOriginald04dd79f9e235eb8e58dee4526a2f3c2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald04dd79f9e235eb8e58dee4526a2f3c2)): ?>
<?php $component = $__componentOriginald04dd79f9e235eb8e58dee4526a2f3c2; ?>
<?php unset($__componentOriginald04dd79f9e235eb8e58dee4526a2f3c2); ?>
<?php endif; ?>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white"><?php echo e($user->name); ?></span>
                        <span class="block truncate text-xs text-zinc-500"><?php echo e($user->email); ?></span>
                    </span>
                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevrons-up-down','class' => 'size-4 text-zinc-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevrons-up-down','class' => 'size-4 text-zinc-400']); ?>
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
                </button>
             <?php $__env->endSlot(); ?>

            <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['href' => route('profile.edit'),'icon' => 'user']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('profile.edit')),'icon' => 'user']); ?><?php echo e(__('Profile')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
            <div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div>
            <div class="px-2.5 py-1.5">
                <p class="mb-2 text-xs font-medium text-zinc-500"><?php echo e(__('Theme')); ?></p>
                <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-white/5">
                    <?php $__currentLoopData = ['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $theme => $icon): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button" @click="$store.theme.set('<?php echo e($theme); ?>')" title="<?php echo e(__(ucfirst($theme))); ?>"
                            class="inline-flex items-center justify-center rounded-md py-1.5 text-zinc-500 transition"
                            :class="$store.theme.value === '<?php echo e($theme); ?>' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'hover:text-zinc-800 dark:hover:text-zinc-200'">
                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => $icon,'class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-4']); ?>
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
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <div class="my-1.5 h-px bg-zinc-100 dark:bg-zinc-800"></div>
            <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['href' => config('carillon.repository').'/blob/main/docs/api.md','icon' => 'book','target' => '_blank','rel' => 'noopener']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(config('carillon.repository').'/blob/main/docs/api.md'),'icon' => 'book','target' => '_blank','rel' => 'noopener']); ?><?php echo e(__('API documentation')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <?php if (isset($component)) { $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.dropdown-item','data' => ['icon' => 'logout','danger' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'logout','danger' => true]); ?><?php echo e(__('Log out')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $attributes = $__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__attributesOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86)): ?>
<?php $component = $__componentOriginalb5772a3fe50157660e6e2d7f982f2e86; ?>
<?php unset($__componentOriginalb5772a3fe50157660e6e2d7f982f2e86); ?>
<?php endif; ?>
            </form>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c)): ?>
<?php $attributes = $__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c; ?>
<?php unset($__attributesOriginaleea726fa4f84deb9f7684b50bdd6328c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleea726fa4f84deb9f7684b50bdd6328c)): ?>
<?php $component = $__componentOriginaleea726fa4f84deb9f7684b50bdd6328c; ?>
<?php unset($__componentOriginaleea726fa4f84deb9f7684b50bdd6328c); ?>
<?php endif; ?>
    </div>
</div>
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/layouts/navigation.blade.php ENDPATH**/ ?>