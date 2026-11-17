<?php

namespace App\Audience;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/** Daily counts of SDK logins by identity hash: valid, wrong, missing. */
final class IdentityChecks
{
    public const RESULTS = ['valid', 'invalid', 'missing'];

    public static function count(Project $project, string $result): void
    {
        $key = ['project_id' => $project->id, 'day' => now()->toDateString()];
        DB::table('identity_checks')->insertOrIgnore($key);
        DB::table('identity_checks')->where($key)->increment($result);
    }

    /** @return array{valid: int, invalid: int, missing: int, days: list<array{day: string, valid: int, invalid: int, missing: int}>} */
    public static function summary(Project $project, int $days = 7): array
    {
        $rows = DB::table('identity_checks')->where('project_id', $project->id)
            ->where('day', '>=', now()->subDays($days - 1)->toDateString())->orderByDesc('day')->get();

        return [
            'valid' => (int) $rows->sum('valid'),
            'invalid' => (int) $rows->sum('invalid'),
            'missing' => (int) $rows->sum('missing'),
            'days' => $rows->map(fn ($row) => ['day' => (string) $row->day, 'valid' => (int) $row->valid, 'invalid' => (int) $row->invalid, 'missing' => (int) $row->missing])->all(),
        ];
    }
}
