<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The first filling of activity_days counted the day each person was
     * created in Carillon as a day of activity: for people imported from
     * OneSignal, the day of the import, which made every cohort "100 %
     * active" that week. Their first day is the day they were first seen.
     */
    public function up(): void
    {
        DB::table('subscribers')->whereNotNull('first_seen_at')->select(['id', 'project_id', 'first_seen_at', 'last_active_at', 'created_at'])
            ->orderBy('id')->chunk(1000, function ($rows) {
                DB::table('activity_days')->insertOrIgnore($rows->map(fn ($row) => [
                    'subscriber_id' => $row->id,
                    'project_id' => $row->project_id,
                    'day' => substr((string) $row->first_seen_at, 0, 10),
                ])->all());

                // People only known through OneSignal (no session of the Carillon
                // SDK): the day of the import was not a day of activity.
                $carillon = DB::table('subscriptions')->whereIn('subscriber_id', $rows->pluck('id'))
                    ->where(fn ($q) => $q->whereNull('sdk')->orWhere('sdk', '!=', 'onesignal-import'))
                    ->distinct()->pluck('subscriber_id')->flip();
                foreach ($rows as $row) {
                    $created = substr((string) $row->created_at, 0, 10);
                    if (isset($carillon[$row->id]) || $created === substr((string) $row->first_seen_at, 0, 10) || $created === substr((string) $row->last_active_at, 0, 10)) {
                        continue;
                    }
                    DB::table('activity_days')->where('subscriber_id', $row->id)->where('day', $created)->delete();
                }
            });
    }

    public function down(): void {}
};
