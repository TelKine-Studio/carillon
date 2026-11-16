<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Devices that receive the tests sent from the composer.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_test')->default(false);
        });
        // Test sends stay out of the statistics.
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_test')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('is_test'));
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('is_test'));
    }
};
