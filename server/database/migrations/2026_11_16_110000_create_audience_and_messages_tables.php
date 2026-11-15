<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A person using the app (OneSignal "user"), known by an external id
        // once the app logs them in, with tags shared by their devices.
        Schema::create('subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->json('tags')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'external_id']);
            $table->index(['project_id', 'last_active_at']);
        });

        // A device (or browser) able to receive notifications: OneSignal "subscription".
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            // ios, android or web.
            $table->string('type', 16);
            // APNs/FCM token, or the JSON web push subscription.
            $table->text('token')->nullable();
            $table->char('token_hash', 64)->nullable();
            // APNs environment of iOS devices: sandbox or production.
            $table->string('environment', 16)->nullable();
            // Opted in on the device and allowed by the user.
            $table->boolean('enabled')->default(true);
            // Rejected by Apple/Google: no longer targeted.
            $table->timestamp('invalid_at')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('device_model', 64)->nullable();
            $table->string('device_os', 32)->nullable();
            $table->string('sdk', 32)->nullable();
            $table->unsignedInteger('sessions')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'token_hash']);
            $table->index(['project_id', 'type', 'enabled']);
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Same format as the "filters" of the API.
            $table->json('filters');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            // Client-chosen key making retries safe (OneSignal "idempotency_key").
            $table->string('idempotency_key', 100)->nullable();
            // Localized texts: {"en": "...", "fr": "..."}.
            $table->json('headings')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('contents');
            // Everything else of the OneSignal format (data, url, image, buttons, platform options).
            $table->json('options')->nullable();
            // Who receives it: segments, filters, aliases, subscription ids, platforms.
            $table->json('targeting');
            $table->string('status', 16)->default('queued');
            $table->timestamp('send_after')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('successful')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('errored')->default(0);
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'idempotency_key']);
            $table->index(['project_id', 'created_at']);
            $table->index(['status', 'send_after']);
        });

        // Totals per platform (kept forever, unlike the per-device details).
        Schema::create('message_platforms', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('successful')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('errored')->default(0);
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('clicked')->default(0);

            $table->primary(['message_id', 'platform']);
        });

        // One row per device a message was sent to (kept for the retention period).
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            // sent, failed (rejected), errored (provider or network error).
            $table->string('status', 16);
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('clicked_at')->nullable();

            $table->unique(['message_id', 'subscription_id']);
            $table->index('subscription_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('message_platforms');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscribers');
    }
};
