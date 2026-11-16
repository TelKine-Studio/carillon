<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WeeklyReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('carillon:weekly-reports')]
#[Description('Emails the summary of the week to the members of the projects')]
class SendWeeklyReports extends Command
{
    public function handle(): int
    {
        $sent = 0;
        User::query()->where('is_active', true)->where('weekly_report', true)->each(function (User $user) use (&$sent) {
            // Projects with some activity this week.
            $projects = $user->projects()
                ->whereHas('messages', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
                ->pluck('projects.id')
                ->all();
            if ($projects !== []) {
                $user->notify(new WeeklyReport($projects));
                $sent++;
            }
        });

        $this->info("Weekly reports sent: {$sent}");

        return self::SUCCESS;
    }
}
