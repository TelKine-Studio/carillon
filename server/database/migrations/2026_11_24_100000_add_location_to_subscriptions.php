<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Last known position of the device, read by the SDK only when the
        // app already has the location permission (rounded to about 1 km).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->unsignedInteger('location_accuracy')->nullable();
            $table->timestamp('located_at')->nullable();

            $table->index(['project_id', 'latitude']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'latitude']);
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy', 'located_at']);
        });
    }
};
