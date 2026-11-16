<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two versions of a message sent to a sample of the audience; the
        // best one then goes to the rest.
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('sample');
            // Devices of the audience left out of the sample, for the winner.
            $table->json('remaining');
            $table->foreignUuid('winner_id')->nullable();
            $table->timestamps();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('ab_test_id')->nullable()->constrained()->nullOnDelete();
            // A, B, or W for the winner sent to the rest.
            $table->char('ab_variant', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ab_test_id');
            $table->dropColumn('ab_variant');
        });
        Schema::dropIfExists('ab_tests');
    }
};
