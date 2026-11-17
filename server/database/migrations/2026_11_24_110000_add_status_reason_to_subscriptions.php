<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Why a device is unsubscribed or unreachable (OneSignal "status details").
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status_reason', 32)->nullable();
            // Raw answer of the provider (APNs reason, FCM code, SMTP bounce...).
            $table->string('status_detail', 255)->nullable();
            $table->timestamp('status_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['status_reason', 'status_detail', 'status_changed_at']);
        });
    }
};
