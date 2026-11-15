<?php

use Illuminate\Support\Facades\Schedule;

// Delete old delivery history and stale invitations.
Schedule::command('model:prune')->daily()->withoutOverlapping();

// Messages scheduled with send_after.
Schedule::command('carillon:dispatch-scheduled')->everyMinute()->withoutOverlapping();
