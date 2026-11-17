<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SDK logins per day by identity hash: valid, wrong or missing. Counted
     * as soon as the secret exists, verification on or off, to know when
     * the installed apps send it.
     */
    public function up(): void
    {
        Schema::create('identity_checks', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('valid')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->unsignedInteger('missing')->default(0);
            $table->primary(['project_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_checks');
    }
};
