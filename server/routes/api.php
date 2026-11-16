<?php

use App\Http\Controllers\Api\CustomEventController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Sdk\ConfigController;
use App\Http\Controllers\Sdk\EmailEventsController;
use App\Http\Controllers\Sdk\InAppController;
use App\Http\Controllers\Sdk\SubscriptionController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

/*
| REST API, OneSignal-compatible: replace https://onesignal.com/api/v1 (or
| https://api.onesignal.com) by https://<your-server>/api/v1 and use an API
| key of the project.
*/
Route::prefix('v1')->group(function () {
    Route::middleware([AuthenticateApiKey::class, 'throttle:api'])->group(function () {
        Route::post('notifications', [NotificationController::class, 'store']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/{id}', [NotificationController::class, 'show']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

        Route::get('templates', [TemplateController::class, 'index']);
        Route::post('templates', [TemplateController::class, 'store']);
        Route::get('templates/{id}', [TemplateController::class, 'show']);
        Route::patch('templates/{id}', [TemplateController::class, 'update']);
        Route::delete('templates/{id}', [TemplateController::class, 'destroy']);

        Route::post('apps/{app_id}/users', [UserController::class, 'store']);
        Route::get('apps/{app_id}/users/by/external_id/{externalId}', [UserController::class, 'show']);
        Route::patch('apps/{app_id}/users/by/external_id/{externalId}', [UserController::class, 'update']);
        Route::delete('apps/{app_id}/users/by/external_id/{externalId}', [UserController::class, 'destroy']);
        Route::post('apps/{app_id}/custom_events', [CustomEventController::class, 'store']);
    });

    // Bounces and complaints from the email service of a project.
    Route::post('email/{appId}/{provider}/{secret}', [EmailEventsController::class, 'store'])->middleware('throttle:email-events');

    // Called by the SDKs inside the apps (App ID only).
    Route::prefix('sdk/apps/{appId}')->middleware('throttle:sdk')->group(function () {
        Route::get('config', [ConfigController::class, 'show']);
        Route::get('icon', [ConfigController::class, 'icon']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::patch('subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::post('subscriptions/{id}/login', [SubscriptionController::class, 'login']);
        Route::post('subscriptions/{id}/logout', [SubscriptionController::class, 'logout']);
        Route::put('subscriptions/{id}/tags', [SubscriptionController::class, 'tags']);
        Route::post('subscriptions/{id}/events', [SubscriptionController::class, 'event']);
        Route::post('subscriptions/{id}/custom-events', [SubscriptionController::class, 'customEvent']);
        Route::get('subscriptions/{id}/in-app-messages', [InAppController::class, 'index']);
        Route::post('in-app-messages/{messageId}/events', [InAppController::class, 'event']);
        Route::post('subscriptions/{id}/email', [SubscriptionController::class, 'addEmail']);
        Route::delete('subscriptions/{id}/email', [SubscriptionController::class, 'removeEmail']);
        Route::post('subscriptions/{id}/sms', [SubscriptionController::class, 'addSms']);
        Route::delete('subscriptions/{id}/sms', [SubscriptionController::class, 'removeSms']);
    });
});
