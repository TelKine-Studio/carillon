<?php

namespace Tests\Feature;

use App\Audience\Activity;
use App\Audience\Audience;
use App\Enums\ProjectRole;
use App\Jobs\DispatchMessage;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SmartTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_person_gets_the_message_at_their_usual_hour(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:10:00', 'UTC'));
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);
        PushCredential::create(['project_id' => $project->id, 'provider' => 'apns', 'details' => ['key_id' => 'ABCDEFGHIJ', 'team_id' => 'ABCDEFGHIJ', 'bundle_id' => 'com.shop'], 'secret' => 'key']);

        $device = function (?array $hours) use ($project) {
            $subscriber = Subscriber::create(['project_id' => $project->id]);
            $subscriber->forceFill(['hour_counts' => $hours])->save();

            return Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'ios', 'token' => bin2hex(random_bytes(32)), 'enabled' => true]);
        };
        // Evening person, morning person (now), and one without history.
        $evening = $device([19 => 6, 8 => 1]);
        $morning = $device([8 => 3]);
        $unknown = $device(null);
        // The creation of the people counts an hour of activity at 08:00 (now): reset.
        Subscriber::query()->whereKey($unknown->subscriber_id)->update(['hour_counts' => null]);
        Subscriber::query()->whereKey($evening->subscriber_id)->update(['hour_counts' => json_encode([19 => 6, 8 => 1])]);

        // Learned from activity: a click weighs 2.
        Activity::click($unknown->subscriber_id, CarbonImmutable::parse('2026-11-19 21:30', 'UTC'));
        $this->assertSame(21, Activity::bestHour(Subscriber::find($unknown->subscriber_id)->hour_counts));
        Subscriber::query()->whereKey($unknown->subscriber_id)->update(['hour_counts' => null]);

        Bus::fake();
        $this->actingAs($owner)->post(route('projects.messages.store', $project), [
            'texts' => [['language' => 'en', 'body' => 'Hi']], 'audience' => 'segments', 'included_segments' => ['Subscribed Users'], 'schedule' => 'smart',
        ])->assertRedirect();
        $message = $project->messages()->first();
        $this->assertSame('last-active', $message->option('delayed_option'));

        (new DispatchMessage($message->id))->handle(app(Audience::class));
        Bus::assertBatched(function ($batch) use ($evening, $morning, $unknown) {
            $delays = [];
            foreach ($batch->jobs as $job) {
                foreach ($job->subscriptionIds as $id) {
                    $delays[$id] = $job->delay?->format('H:i');
                }
            }

            // The one without history gets the busiest hour of the project (19:00).
            return $delays === [$evening->id => '19:00', $unknown->id => '19:00', $morning->id => null]
                || ($delays[$evening->id] === '19:00' && $delays[$unknown->id] === '19:00' && $delays[$morning->id] === null);
        });

        $this->get(route('projects.messages.create', $project))->assertOk()->assertSee('Busiest hour', false);
    }
}
