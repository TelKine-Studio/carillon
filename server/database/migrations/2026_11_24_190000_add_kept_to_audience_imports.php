<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Devices of the export already on the Carillon SDK: left as they are.
        Schema::table('audience_imports', function (Blueprint $table) {
            $table->unsignedInteger('kept')->default(0)->after('skipped');
        });
    }

    public function down(): void
    {
        Schema::table('audience_imports', function (Blueprint $table) {
            $table->dropColumn('kept');
        });
    }
};
