<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Automations (OneSignal "Journeys"): a trigger, then steps each person
        // goes through on their own (wait, send, check a tag).
        Schema::create('journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            // draft, active or paused.
            $table->string('status', 16)->default('draft');
            // {"type": "new_device" | "inactive" | "tag" | "event", ...}
            $table->json('trigger');
            // Only people of this segment enter (optional).
            $table->string('segment')->nullable();
            $table->boolean('reentry')->default(false);
            $table->json('steps');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journey_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step')->default(0);
            // active, completed or exited.
            $table->string('status', 16)->default('active');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_run_at']);
            $table->index(['journey_id', 'subscriber_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('journey_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('journey_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journey_id');
            $table->dropColumn('journey_step');
        });
        Schema::dropIfExists('journey_entries');
        Schema::dropIfExists('journeys');
    }
};
