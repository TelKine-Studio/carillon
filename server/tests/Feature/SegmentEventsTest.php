<?php

namespace Tests\Feature;

use App\Audience\Audience;
use App\Enums\ProjectRole;
use App\Models\CustomEvent;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_segments_on_events_amounts_and_clicks(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);

        $person = function (string $token, array $purchases = []) use ($project) {
            $subscriber = Subscriber::create(['project_id' => $project->id]);
            $device = Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'android', 'token' => $token, 'enabled' => true]);
            foreach ($purchases as [$value, $daysAgo]) {
                CustomEvent::query()->create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'name' => 'recharge', 'value' => $value, 'occurred_at' => now()->subDays($daysAgo)]);
            }

            return $device;
        };
        $big = $person('big', [[5000, 1], [10000, 3], [2000, 40]]);
        $small = $person('small', [[500, 2]]);
        $none = $person('none');

        $match = fn (array $filter) => app(Audience::class)->query($project, ['filters' => [$filter]])->orderBy('subscriptions.token')->pluck('subscriptions.token')->all();

        $this->assertSame(['big', 'small'], $match(['field' => 'event', 'key' => 'recharge', 'relation' => 'exists']));
        $this->assertSame(['none'], $match(['field' => 'event', 'key' => 'recharge', 'relation' => 'not_exists']));
        $this->assertSame(['big'], $match(['field' => 'event', 'key' => 'recharge', 'relation' => '>', 'value' => 1, 'hours_ago' => 24 * 7]));
        $this->assertSame(['big'], $match(['field' => 'event_value', 'key' => 'recharge', 'relation' => '>', 'value' => 10000, 'hours_ago' => 24 * 30]));
        $this->assertSame(['none', 'small'], $match(['field' => 'event_value', 'key' => 'recharge', 'relation' => '<', 'value' => 1000]));

        // Clicked in the last 48 hours, or not.
        $message = Message::create(['project_id' => $project->id, 'contents' => ['en' => 'x'], 'targeting' => [], 'status' => 'sent']);
        DB::table('deliveries')->insert(['message_id' => $message->id, 'subscription_id' => $small->id, 'status' => 'sent', 'sent_at' => now()->subDay(), 'clicked_at' => now()->subDay()]);
        $this->assertSame(['small'], $match(['field' => 'last_click', 'relation' => '<', 'hours_ago' => 48]));
        $this->assertSame(['big', 'none'], $match(['field' => 'last_click', 'relation' => '>', 'hours_ago' => 48]));

        // Saved from the editor.
        $this->actingAs($owner)->post(route('projects.segments.store', $project), [
            'name' => 'Gros clients', 'filters' => json_encode([['field' => 'event_value', 'key' => 'recharge', 'relation' => '>', 'value' => '10000', 'hours_ago' => 720]]),
        ])->assertRedirect();
        $this->assertSame(['big'], app(Audience::class)->query($project, ['segments' => ['Gros clients']])->pluck('subscriptions.token')->all());
        $this->get(route('projects.segments.create', $project))->assertOk()->assertSee('event_value');
    }
}
