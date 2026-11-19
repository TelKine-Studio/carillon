<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls of the SDK about Live Activities (start token, enter, exit), accepted
 * or refused: shows why an activity never got its update token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Set when the id sent by the app is a device of the project.
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            // The id as sent by the app, even unknown.
            $table->string('subscription_ref', 64);
            $table->string('action', 16);
            $table->string('activity_id', 255)->nullable();
            $table->string('activity_type', 128)->nullable();
            // ok, unknown_device, other_project, not_ios, invalid, error
            $table->string('outcome', 24);
            $table->string('detail', 255)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_activity_calls');
    }
};
