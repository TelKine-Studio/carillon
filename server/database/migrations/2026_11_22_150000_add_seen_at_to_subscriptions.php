<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Last sign of life of the open app (Carillon SDK), for "online now". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable();
            $table->index(['project_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'seen_at']);
            $table->dropColumn('seen_at');
        });
    }
};
