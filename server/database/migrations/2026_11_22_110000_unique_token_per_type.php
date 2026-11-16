<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One number can be both an SMS and a WhatsApp subscription. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'token_hash']);
            $table->unique(['project_id', 'type', 'token_hash']);
            $table->index(['project_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'token_hash']);
            $table->dropUnique(['project_id', 'type', 'token_hash']);
            $table->unique(['project_id', 'token_hash']);
        });
    }
};
