<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages a person has read (or removed) in the inbox of the app. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_reads', function (Blueprint $table) {
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('removed_at')->nullable();

            $table->primary(['subscriber_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_reads');
    }
};
