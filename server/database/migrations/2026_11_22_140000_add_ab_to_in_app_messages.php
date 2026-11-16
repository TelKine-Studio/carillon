<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A/B test of in-app messages: a second text shown to half of the people. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('in_app_messages', function (Blueprint $table) {
            $table->json('variant_b')->nullable()->after('buttons');
            $table->unsignedInteger('shown_b')->default(0);
            $table->unsignedInteger('clicked_b')->default(0);
            $table->unsignedInteger('dismissed_b')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('in_app_messages', function (Blueprint $table) {
            $table->dropColumn(['variant_b', 'shown_b', 'clicked_b', 'dismissed_b']);
        });
    }
};
