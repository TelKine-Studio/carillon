<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => __('Settings')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Settings'))]); ?>
    <?php echo $__env->make('admin.settings.partials.nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if (isset($component)) { $__componentOriginal951a5936e8413b65cd052beecc1fba57 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal951a5936e8413b65cd052beecc1fba57 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.settings.layout','data' => ['section' => $section]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('settings.layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($section)]); ?>
        <form method="POST" action="<?php echo e(route('admin.settings.security')); ?>" class="space-y-6" x-data="{ sso: <?php echo e(old('sso_enabled', $settings->boolean('sso.enabled')) ? 'true' : 'false'); ?> }">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Two-factor authentication'),'icon' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Two-factor authentication')),'icon' => 'shield']); ?>
                <?php if (isset($component)) { $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.toggle','data' => ['name' => 'require_2fa_admins','checked' => $settings->boolean('security.require_2fa_admins'),'label' => __('Required for administrators'),'description' => __('Administrators without two-factor authentication cannot open the administration until they enable it.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'require_2fa_admins','checked' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->boolean('security.require_2fa_admins')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Required for administrators')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Administrators without two-factor authentication cannot open the administration until they enable it.'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $attributes = $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $component = $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
                <?php $__errorArgs = ['require_2fa_admins'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => __('Single sign-on (OpenID Connect)'),'description' => __('Let people log in with Google Workspace, Microsoft Entra ID, Keycloak, Authentik, Okta…'),'icon' => 'key']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Single sign-on (OpenID Connect)')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Let people log in with Google Workspace, Microsoft Entra ID, Keycloak, Authentik, Okta…')),'icon' => 'key']); ?>
                <div class="space-y-5">
                    <?php if (isset($component)) { $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.toggle','data' => ['name' => 'sso_enabled','checked' => $settings->boolean('sso.enabled'),'label' => __('Enable single sign-on'),'xModel' => 'sso']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_enabled','checked' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->boolean('sso.enabled')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Enable single sign-on')),'x-model' => 'sso']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $attributes = $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $component = $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
                    <div x-show="sso" x-collapse class="space-y-5">
                        <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'sso_label','label' => __('Button label'),'value' => $settings->get('sso.label'),'placeholder' => __('e.g. Google Workspace')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_label','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Button label')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.label')),'placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('e.g. Google Workspace'))]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'sso_issuer','type' => 'url','label' => __('Issuer URL'),'value' => $settings->get('sso.issuer'),'placeholder' => 'https://accounts.google.com','hint' => __('The address that serves /.well-known/openid-configuration.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_issuer','type' => 'url','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Issuer URL')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.issuer')),'placeholder' => 'https://accounts.google.com','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('The address that serves /.well-known/openid-configuration.'))]); ?>
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
                        <div class="grid gap-5 sm:grid-cols-2">
                            <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'sso_client_id','label' => __('Client ID'),'value' => $settings->get('sso.client_id'),'autocomplete' => 'off']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_client_id','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Client ID')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.client_id')),'autocomplete' => 'off']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'sso_client_secret','type' => 'password','label' => __('Client secret'),'autocomplete' => 'new-password','placeholder' => $settings->get('sso.client_secret') ? '••••••••' : '','hint' => $settings->get('sso.client_secret') ? __('Leave empty to keep the current secret.') : __('Stored encrypted.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_client_secret','type' => 'password','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Client secret')),'autocomplete' => 'new-password','placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.client_secret') ? '••••••••' : ''),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.client_secret') ? __('Leave empty to keep the current secret.') : __('Stored encrypted.'))]); ?>
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
                        </div>
                        <div x-data="copy(<?php echo \Illuminate\Support\Js::from(route('sso.callback'))->toHtml() ?>)" class="rounded-xl bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-white/[.03] dark:text-zinc-400">
                            <?php echo e(__('Redirect URI to declare at the provider:')); ?>

                            <span class="mt-1 flex items-center gap-2"><code class="font-mono text-zinc-800 dark:text-zinc-200"><?php echo e(route('sso.callback')); ?></code><button type="button" @click="copy()"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'copy','class' => 'size-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'copy','class' => 'size-3.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?></button></span>
                        </div>
                        <div class="border-t border-zinc-100 pt-5 dark:border-zinc-800">
                            <?php if (isset($component)) { $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.toggle','data' => ['name' => 'sso_auto_create','checked' => $settings->boolean('sso.auto_create'),'label' => __('Create accounts automatically'),'description' => __('People without an account get one (role User) at their first sign-in. Otherwise, add them first in Users.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_auto_create','checked' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->boolean('sso.auto_create')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Create accounts automatically')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('People without an account get one (role User) at their first sign-in. Otherwise, add them first in Users.'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $attributes = $__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__attributesOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee)): ?>
<?php $component = $__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee; ?>
<?php unset($__componentOriginalb5e8d8ae92d68ac68a76e18b82b039ee); ?>
<?php endif; ?>
                        </div>
                        <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['name' => 'sso_allowed_domains','label' => __('Allowed email domains'),'value' => $settings->get('sso.allowed_domains'),'placeholder' => 'telkine.net, example.com','hint' => __('Comma separated. Empty: any domain accepted by the provider.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sso_allowed_domains','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Allowed email domains')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->get('sso.allowed_domains')),'placeholder' => 'telkine.net, example.com','hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Comma separated. Empty: any domain accepted by the provider.'))]); ?>
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
                    </div>
                </div>
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
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal951a5936e8413b65cd052beecc1fba57)): ?>
<?php $attributes = $__attributesOriginal951a5936e8413b65cd052beecc1fba57; ?>
<?php unset($__attributesOriginal951a5936e8413b65cd052beecc1fba57); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal951a5936e8413b65cd052beecc1fba57)): ?>
<?php $component = $__componentOriginal951a5936e8413b65cd052beecc1fba57; ?>
<?php unset($__componentOriginal951a5936e8413b65cd052beecc1fba57); ?>
<?php endif; ?>
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
<?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/admin/settings/security.blade.php ENDPATH**/ ?>