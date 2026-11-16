<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What people do after a message (purchase, top-up…), reported by
        // the SDK or the API, and the message it is attributed to.
        Schema::create('custom_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->decimal('value', 14, 2)->nullable();
            $table->json('properties')->nullable();
            $table->foreignUuid('message_id')->nullable()->constrained()->nullOnDelete();
            // direct (clicked the message) or influenced (received it).
            $table->string('attribution', 16)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'name']);
            $table->index('message_id');
            $table->index('subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_events');
    }
};
