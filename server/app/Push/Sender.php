<?php

namespace App\Push;

use App\Models\Message;
use App\Models\PushCredential;
use App\Models\Subscription;
use Illuminate\Support\Collection;

interface Sender
{
    /**
     * Sends a message to devices of one platform, concurrently.
     *
     * @param  Collection<int, Subscription>  $subscriptions  With their subscriber loaded.
     * @return array<string, Result> Keyed by subscription id.
     */
    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array;
}
