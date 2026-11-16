<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banners and pop-ups shown inside the open app (OneSignal "In-app").
        Schema::create('in_app_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            // draft, active or paused.
            $table->string('status', 16)->default('draft');
            // banner, modal or fullscreen.
            $table->string('layout', 16)->default('modal');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('image_url', 500)->nullable();
            // [{"id", "text", "action": "dismiss" | "url", "url"}]
            $table->json('buttons')->nullable();
            // {"type": "app_open" | "event", "event": "..."}
            $table->json('trigger');
            $table->string('segment')->nullable();
            // once, every_session or every_days (with "days").
            $table->string('frequency', 16)->default('once');
            $table->unsignedSmallInteger('frequency_days')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('shown')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->unsignedInteger('dismissed')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_messages');
    }
};
