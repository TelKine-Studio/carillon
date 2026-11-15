<?php

namespace App\Console\Commands;

use App\Jobs\DispatchMessage;
use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('carillon:dispatch-scheduled')]
#[Description('Start sending the messages whose time has come (send_after)')]
class DispatchScheduledMessages extends Command
{
    public function handle(): int
    {
        Message::query()
            ->where('status', 'scheduled')
            ->where('send_after', '<=', now())
            ->pluck('id')
            ->each(fn (string $id) => DispatchMessage::dispatch($id));

        return self::SUCCESS;
    }
}
