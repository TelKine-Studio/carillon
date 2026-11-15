<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2000);
            $table->json('events');
            $table->text('secret');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('failures')->default(0);
            $table->string('disabled_reason')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Recent attempts, to debug an endpoint from the dashboard.
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event', 64);
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamp('created_at')->nullable();

            $table->index(['webhook_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
