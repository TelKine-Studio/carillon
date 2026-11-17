<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An in-app message "kept for journeys" is shown only to the people a
        // journey step gave it to (and still at most as often as allowed).
        Schema::table('in_app_messages', function (Blueprint $table) {
            $table->boolean('journey_only')->default(false);
        });

        Schema::create('in_app_targets', function (Blueprint $table) {
            $table->foreignUuid('in_app_message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journey_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->primary(['in_app_message_id', 'subscriber_id']);
            $table->index('subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_targets');
        Schema::table('in_app_messages', function (Blueprint $table) {
            $table->dropColumn('journey_only');
        });
    }
};
