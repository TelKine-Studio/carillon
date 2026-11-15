<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\PushCredentialController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');

// First-run setup (only reachable while no user exists).
Route::get('setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('setup', [SetupController::class, 'store'])->name('setup.store');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:login')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('auth/sso', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

    Route::get('two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:login');

    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])->name('invitations.accept');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('dashboard', '/projects')->name('dashboard');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('show');
        Route::get('icon', [ProjectController::class, 'icon'])->name('icon');
        Route::get('settings/{section?}', [ProjectController::class, 'settings'])->name('settings');
        Route::put('/', [ProjectController::class, 'update'])->name('update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('destroy');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/create', [MessageController::class, 'create'])->name('messages.create');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/estimate', [MessageController::class, 'estimate'])->name('messages.estimate');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{message}/cancel', [MessageController::class, 'cancel'])->name('messages.cancel');

        Route::get('audience', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('audience/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');
        Route::put('audience/{subscriber}/tags', [SubscriberController::class, 'updateTags'])->name('subscribers.tags');
        Route::post('audience/{subscriber}/test', [SubscriberController::class, 'test'])->name('subscribers.test');
        Route::delete('audience/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');

        Route::get('segments', [SegmentController::class, 'index'])->name('segments.index');
        Route::get('segments/create', [SegmentController::class, 'create'])->name('segments.create');
        Route::post('segments', [SegmentController::class, 'store'])->name('segments.store');
        Route::post('segments/count', [SegmentController::class, 'count'])->name('segments.count');
        Route::get('segments/{segment}/edit', [SegmentController::class, 'edit'])->name('segments.edit');
        Route::put('segments/{segment}', [SegmentController::class, 'update'])->name('segments.update');
        Route::delete('segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::put('push/{provider}', [PushCredentialController::class, 'update'])->name('push.update');
        Route::post('push/webpush/generate', [PushCredentialController::class, 'generateWebPush'])->name('push.webpush.generate');
        Route::delete('push/{provider}', [PushCredentialController::class, 'destroy'])->name('push.destroy');

        Route::post('api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->scopeBindings()->name('api-keys.destroy');

        Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
        Route::post('webhooks/{webhook}/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');
        Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

        Route::post('members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::put('members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('profile/two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->name('two-factor.recovery-codes');
    Route::delete('profile/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    Route::middleware(['can:admin', RequireTwoFactor::class])->prefix('admin')->name('admin.')->group(function () {
        Route::get('audit', AuditLogController::class)->name('audit');
        Route::put('settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security');
        Route::resource('users', UserController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::redirect('invitations', '/admin/users')->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('settings/{section?}', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::put('settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');
    });
});
