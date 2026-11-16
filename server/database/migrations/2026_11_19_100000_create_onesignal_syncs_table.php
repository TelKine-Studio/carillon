<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nightly import of the subscribers of a OneSignal app, while the
        // apps still run the OneSignal SDK.
        Schema::create('onesignal_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('app_id', 64);
            $table->text('api_key');
            $table->boolean('enabled')->default(true);
            $table->text('pending_url')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onesignal_syncs');
    }
};
