<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Ends the other sessions of a user (new password: a thief is logged out). */
final class Sessions
{
    public static function endOthers(User $user, ?string $keep = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())
                ->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();
        }
        // "Remember me" cookies of the other browsers stop working too.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
