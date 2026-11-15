<?php

namespace App\Audience;

use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/** Links devices to people (login / logout of the app). */
class Identity
{
    /**
     * Attaches a device to the person with this external id (created if
     * needed). The anonymous person the device belonged to is merged into
     * it: tags it did not have are kept, then it is deleted.
     */
    public function login(Subscription $subscription, string $externalId): Subscriber
    {
        return DB::transaction(function () use ($subscription, $externalId) {
            $previous = $subscription->subscriber;

            if ($previous->external_id === $externalId) {
                return $previous;
            }

            $person = Subscriber::query()->firstOrCreate(
                ['project_id' => $subscription->project_id, 'external_id' => $externalId],
                ['language' => $previous->language, 'timezone' => $previous->timezone, 'country' => $previous->country],
            );

            $subscription->forceFill(['subscriber_id' => $person->id])->save();

            if ($previous->external_id === null) {
                $person->tags = array_merge($previous->tags ?? [], $person->tags ?? []) ?: null;
                $person->last_active_at = max($person->last_active_at, $previous->last_active_at);
                $person->save();

                if (! $previous->subscriptions()->exists()) {
                    $previous->delete();
                }
            }

            return $person;
        });
    }

    /** Detaches the device from the person: it becomes anonymous again. */
    public function logout(Subscription $subscription): Subscriber
    {
        $previous = $subscription->subscriber;

        if ($previous->external_id === null) {
            return $previous;
        }

        $anonymous = Subscriber::query()->create([
            'project_id' => $subscription->project_id,
            'language' => $previous->language,
            'timezone' => $previous->timezone,
            'country' => $previous->country,
            'last_active_at' => now(),
        ]);
        $subscription->forceFill(['subscriber_id' => $anonymous->id])->save();

        return $anonymous;
    }

    public function newSubscriber(Project $project, array $attributes = []): Subscriber
    {
        return Subscriber::query()->create(['project_id' => $project->id, ...$attributes]);
    }
}
