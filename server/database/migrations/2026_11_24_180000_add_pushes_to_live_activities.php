<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The last answers of Apple / Google for an activity: status, ids, token used. */
    public function up(): void
    {
        Schema::table('live_activities', function (Blueprint $table) {
            $table->json('pushes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('live_activities', function (Blueprint $table) {
            $table->dropColumn('pushes');
        });
    }
};
