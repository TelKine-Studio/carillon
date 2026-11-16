<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One row per person and day they used the app: the base of retention cohorts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->timestamp('first_seen_at')->nullable()->after('last_active_at');
        });
        DB::table('subscribers')->update(['first_seen_at' => DB::raw('created_at')]);

        Schema::create('activity_days', function (Blueprint $table) {
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->primary(['subscriber_id', 'day']);
            $table->index(['project_id', 'day']);
        });

        // What is already known: the first and the last day seen.
        foreach (['created_at', 'last_active_at'] as $column) {
            DB::table('subscribers')->whereNotNull($column)->select(['id', 'project_id', $column])->orderBy('id')
                ->chunk(1000, function ($rows) use ($column) {
                    DB::table('activity_days')->insertOrIgnore($rows->map(fn ($row) => [
                        'subscriber_id' => $row->id,
                        'project_id' => $row->project_id,
                        'day' => substr((string) $row->{$column}, 0, 10),
                    ])->all());
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_days');
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('first_seen_at');
        });
    }
};
