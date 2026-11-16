<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // full: the whole REST API; send: notifications, templates (read), events and stats.
            $table->string('scope', 16)->default('full')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', fn (Blueprint $table) => $table->dropColumn('scope'));
    }
};
