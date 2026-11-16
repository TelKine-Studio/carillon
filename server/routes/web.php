<?php

use App\Audience\Presence;
use App\Http\Controllers\AbTestController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AudienceImportController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\InAppMessageController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\LiveActivityController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OneSignalSyncController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\PushCredentialController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TestDeviceController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// Links inside the emails (signed, no account).
Route::middleware('signed:relative')->prefix('e')->name('email.')->group(function () {
    Route::get('o/{message}/{subscription}', [EmailTrackingController::class, 'open'])->name('open');
    Route::get('c/{message}/{subscription}', [EmailTrackingController::class, 'click'])->name('click');
    Route::get('u/{subscription}', [EmailTrackingController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('u/{subscription}', [EmailTrackingController::class, 'confirm'])->name('unsubscribe.confirm');
});

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

        Route::get('stats', StatsController::class)->name('stats');
        Route::get('live', function (Project $project) {
            Gate::authorize('view', $project);

            return response()->json(Presence::now($project));
        })->name('live');
        Route::get('in-app', [InAppMessageController::class, 'index'])->name('in-app.index');
        Route::get('in-app/create', [InAppMessageController::class, 'create'])->name('in-app.create');
        Route::post('in-app', [InAppMessageController::class, 'store'])->name('in-app.store');
        Route::get('in-app/{inApp}/edit', [InAppMessageController::class, 'edit'])->name('in-app.edit');
        Route::put('in-app/{inApp}', [InAppMessageController::class, 'update'])->name('in-app.update');
        Route::put('in-app/{inApp}/status', [InAppMessageController::class, 'status'])->name('in-app.status');
        Route::delete('in-app/{inApp}', [InAppMessageController::class, 'destroy'])->name('in-app.destroy');
        Route::get('journeys', [JourneyController::class, 'index'])->name('journeys.index');
        Route::get('journeys/create', [JourneyController::class, 'create'])->name('journeys.create');
        Route::post('journeys', [JourneyController::class, 'store'])->name('journeys.store');
        Route::get('journeys/{journey}', [JourneyController::class, 'show'])->name('journeys.show');
        Route::get('journeys/{journey}/edit', [JourneyController::class, 'edit'])->name('journeys.edit');
        Route::put('journeys/{journey}', [JourneyController::class, 'update'])->name('journeys.update');
        Route::put('journeys/{journey}/status', [JourneyController::class, 'status'])->name('journeys.status');
        Route::delete('journeys/{journey}', [JourneyController::class, 'destroy'])->name('journeys.destroy');
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('live-activities', [LiveActivityController::class, 'index'])->name('live-activities.index');
        Route::get('messages/create', [MessageController::class, 'create'])->name('messages.create');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/estimate', [MessageController::class, 'estimate'])->name('messages.estimate');
        Route::post('messages/test', [MessageController::class, 'test'])->name('messages.test');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::put('messages/{message}/labels', [MessageController::class, 'labelsUpdate'])->name('messages.labels');
        Route::post('messages/{message}/cancel', [MessageController::class, 'cancel'])->name('messages.cancel');
        Route::delete('messages/{message}/draft', [MessageController::class, 'destroyDraft'])->name('messages.destroy-draft');
        Route::get('messages/{message}/export', [MessageController::class, 'export'])->name('messages.export');
        Route::get('ab-tests/{abTest}', [AbTestController::class, 'show'])->name('ab-tests.show');
        Route::post('ab-tests/{abTest}/winner', [AbTestController::class, 'winner'])->name('ab-tests.winner');

        Route::get('audience', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('audience/import', [AudienceImportController::class, 'create'])->name('imports.create');
        Route::post('audience/import', [AudienceImportController::class, 'store'])->name('imports.store');
        Route::get('audience/import/{import}', [AudienceImportController::class, 'show'])->whereNumber('import')->name('imports.show');
        Route::put('audience/sync', [OneSignalSyncController::class, 'update'])->name('onesignal-sync.update');
        Route::post('audience/sync', [OneSignalSyncController::class, 'run'])->name('onesignal-sync.run');
        Route::delete('audience/sync', [OneSignalSyncController::class, 'destroy'])->name('onesignal-sync.destroy');
        Route::get('audience/test-devices', [TestDeviceController::class, 'index'])->name('test-devices.index');
        Route::post('audience/test-devices', [TestDeviceController::class, 'store'])->name('test-devices.store');
        Route::put('audience/test-devices/{subscription}', [TestDeviceController::class, 'update'])->name('test-devices.update');
        Route::delete('audience/test-devices/{subscription}', [TestDeviceController::class, 'destroy'])->name('test-devices.destroy');
        Route::get('audience/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');
        Route::put('audience/{subscriber}/tags', [SubscriberController::class, 'updateTags'])->name('subscribers.tags');
        Route::post('audience/{subscriber}/test', [SubscriberController::class, 'test'])->name('subscribers.test');
        Route::put('audience/{subscriber}/devices/{subscription}/test', [SubscriberController::class, 'testDevice'])->name('subscribers.test-device');
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

        Route::put('email-settings', [EmailSettingsController::class, 'update'])->name('email.update');
        Route::post('email-settings/test', [EmailSettingsController::class, 'test'])->name('email.test');
        Route::post('email-settings/port', [EmailSettingsController::class, 'checkPort'])->name('email.port');
        Route::get('sms/balance', [PushCredentialController::class, 'smsBalance'])->name('sms.balance');
        Route::get('whatsapp/templates', [PushCredentialController::class, 'whatsAppTemplates'])->name('whatsapp.templates');
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
