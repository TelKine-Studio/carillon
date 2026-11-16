<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_of_a_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Shop']);
        $project->addMember($owner, ProjectRole::Owner);

        foreach ([['ios', 'CI', 'fr', '1.3.3'], ['ios', 'CI', 'fr', '1.3.3'], ['android', 'SN', 'en', '28']] as $i => [$type, $country, $language, $version]) {
            $subscriber = Subscriber::create(['project_id' => $project->id, 'country' => $country, 'language' => $language]);
            Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => $type, 'token' => 'token-'.$i, 'enabled' => true, 'app_version' => $version, 'sdk' => $i === 2 ? Subscription::ONESIGNAL_SDK : 'carillon-flutter/1.0.0']);
        }

        $message = Message::create(['project_id' => $project->id, 'contents' => ['en' => 'Bonus today'], 'targeting' => ['included_segments' => ['All']], 'status' => 'sent', 'recipients' => 40, 'successful' => 40, 'clicked' => 4, 'started_at' => now()]);

        $this->actingAs($owner)->get(route('projects.stats', [$project, 'days' => 7]))
            ->assertOk()
            ->assertSee('Statistics')
            ->assertSee('10 %')
            ->assertSee('Bonus today')
            ->assertSee('iOS')
            ->assertSee('1.3.3')
            ->assertSee('1 device still runs the OneSignal SDK', false);

        // Unknown period: 30 days.
        $this->actingAs($owner)->get(route('projects.stats', [$project, 'days' => 12]))->assertOk()->assertSee('aria-current="page"', false);
        $this->actingAs(User::factory()->create())->get(route('projects.stats', $project))->assertForbidden();
    }
}
