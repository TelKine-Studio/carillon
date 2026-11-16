<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A name for each test device ("Ada's iPhone"), as OneSignal does.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('test_name', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('test_name'));
    }
};
