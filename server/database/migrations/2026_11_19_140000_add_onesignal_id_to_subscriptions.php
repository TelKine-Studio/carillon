<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Id of the device at OneSignal ("subscription id" / player id), kept
        // at import so a device can be found with the id shown by OneSignal.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('onesignal_id', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['onesignal_id']);
            $table->dropColumn('onesignal_id');
        });
    }
};
