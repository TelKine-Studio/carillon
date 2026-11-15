<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
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
    });

    // Called by the SDKs inside the apps (App ID only).
    Route::prefix('sdk/apps/{appId}')->middleware('throttle:sdk')->group(function () {
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::patch('subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::post('subscriptions/{id}/login', [SubscriptionController::class, 'login']);
        Route::post('subscriptions/{id}/logout', [SubscriptionController::class, 'logout']);
        Route::put('subscriptions/{id}/tags', [SubscriptionController::class, 'tags']);
        Route::post('subscriptions/{id}/events', [SubscriptionController::class, 'event']);
    });
});
