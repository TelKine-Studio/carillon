<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast channels of APNs (iOS 18+): an activity started on a channel
 * gets its updates without the update token, which iOS often gives the app
 * too late (or never, when the app is not running).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('activity_id', 255);
            $table->string('environment', 16);
            $table->string('channel_id', 255);
            // Deleted at Apple (10 000 channels at most per app and environment).
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'activity_id', 'environment']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::table('live_activities', function (Blueprint $table) {
            $table->string('channel_id', 255)->nullable()->after('update_token');
        });
    }

    public function down(): void
    {
        Schema::table('live_activities', fn (Blueprint $table) => $table->dropColumn('channel_id'));
        Schema::dropIfExists('live_activity_channels');
    }
};
