<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live Activities of iOS. Their tokens are not devices: a push-to-start
 * token belongs to a device and a type of activity, an update token lives as
 * long as one activity. Neither is counted in the audience.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_start_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            // Name of the ActivityAttributes struct of the app.
            $table->string('activity_type', 128);
            $table->text('token');
            $table->timestamps();

            $table->unique(['subscription_id', 'activity_type']);
            $table->index(['project_id', 'activity_type']);
        });

        Schema::create('live_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 128);
            // Chosen by the app or its server (an order number…), shared by
            // the devices of one person.
            $table->string('activity_id', 255);
            // starting → started (by push, waiting for its token) → active
            // (token known) → ended; failed when Apple refused the start.
            $table->string('status', 16);
            $table->text('update_token')->nullable();
            $table->json('content_state')->nullable();
            // Update or end received before the update token: sent when it arrives.
            $table->json('pending')->nullable();
            $table->unsignedInteger('updates')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('token_at')->nullable();
            $table->timestamp('updated_last_at')->nullable();
            // Ended by Carillon at this time if nobody did (forgotten activity).
            $table->timestamp('end_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'activity_id']);
            $table->index(['project_id', 'activity_id']);
            $table->index(['status', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_activities');
        Schema::dropIfExists('live_activity_start_tokens');
    }
};
