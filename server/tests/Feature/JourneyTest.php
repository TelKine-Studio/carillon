<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Journeys\JourneyRunner;
use App\Models\ApiKey;
use App\Models\CustomEvent;
use App\Models\Journey;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->admin()->create();
        $this->project = Project::factory()->create(['name' => 'Shop']);
        $this->project->addMember($this->owner, ProjectRole::Owner);

        $rsa = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $pem);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'fcm', 'details' => ['project_id' => 'shop-123', 'client_email' => 'push@shop-123.iam.gserviceaccount.com'], 'secret' => json_encode([
            'type' => 'service_account', 'project_id' => 'shop-123', 'client_email' => 'push@shop-123.iam.gserviceaccount.com', 'private_key' => $pem,
        ])]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/shop-123/messages/1']),
        ]);
    }

    protected function device(string $token, array $extra = []): Subscription
    {
        $id = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'android', 'token' => $token, ...$extra])->assertCreated()->json('id');

        return Subscription::findOrFail($id);
    }

    public function test_a_welcome_journey_waits_then_sends_to_new_devices(): void
    {
        $this->actingAs($this->owner)->post(route('projects.journeys.store', $this->project), [
            'name' => 'Welcome',
            'trigger' => ['type' => 'new_device'],
            'steps' => [
                ['type' => 'wait', 'amount' => 10, 'unit' => 'minutes'],
                ['type' => 'push', 'title' => 'Bienvenue', 'body' => 'Merci !'],
                ['type' => 'condition', 'key' => 'plan', 'operator' => 'is', 'value' => 'pro'],
                ['type' => 'push', 'body' => 'Pour les pros'],
            ],
        ])->assertSessionHasNoErrors();
        $journey = Journey::firstOrFail();
        $this->assertSame('draft', $journey->status);

        // A draft lets nobody in.
        $this->device('before-activation');
        $this->assertSame(0, $journey->entries()->count());

        $this->actingAs($this->owner)->put(route('projects.journeys.status', [$this->project, $journey]), ['status' => 'active']);
        $ada = $this->device('ada', ['tags' => ['plan' => 'pro']]);
        $bob = $this->device('bob');
        $this->assertSame(2, $journey->entries()->count());

        $runner = app(JourneyRunner::class);
        $runner->run();
        $this->assertSame(0, Message::count());

        // Ten minutes later: one message for both, then the condition keeps Ada only.
        $this->travel(11)->minutes();
        $runner->run();
        $welcome = Message::where('journey_step', 1)->firstOrFail();
        $this->assertSame($journey->id, $welcome->journey_id);
        $this->assertSame(2, $welcome->recipients);
        $this->assertSame(1, Message::where('journey_step', 3)->value('recipients'));
        $this->assertSame('completed', $journey->entries()->where('subscriber_id', $ada->subscriber_id)->value('status'));
        $this->assertSame('exited', $journey->entries()->where('subscriber_id', $bob->subscriber_id)->value('status'));

        // Journey messages stay out of the campaign list; the journey reports them.
        $this->actingAs($this->owner)->get(route('projects.messages.index', $this->project))->assertDontSee('Bienvenue');
        $this->actingAs($this->owner)->get(route('projects.journeys.show', [$this->project, $journey]))->assertOk()->assertSee('Bienvenue');
        $this->actingAs($this->owner)->get(route('projects.journeys.index', $this->project))->assertOk()->assertSee('Welcome');
    }

    public function test_inactive_people_and_tags_trigger_journeys(): void
    {
        $winback = $this->project->journeys()->create(['name' => 'Win back', 'status' => 'active', 'trigger' => ['type' => 'inactive', 'days' => 7], 'reentry' => true, 'steps' => [['type' => 'push', 'body' => 'On vous attend']]]);
        $premium = $this->project->journeys()->create(['name' => 'Premium', 'status' => 'active', 'trigger' => ['type' => 'tag', 'key' => 'plan', 'value' => 'premium'], 'steps' => [['type' => 'push', 'body' => 'Merci']]]);

        $device = $this->device('carla');
        Subscriber::whereKey($device->subscriber_id)->update(['last_active_at' => now()->subDays(8)]);

        $runner = app(JourneyRunner::class);
        $this->assertSame(1, $runner->sweepInactive());
        $this->assertSame(0, $runner->sweepInactive());
        $this->assertSame(1, $winback->entries()->count());

        $this->putJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$device->id}/tags", ['tags' => ['plan' => 'basic']]);
        $this->assertSame(0, $premium->entries()->count());
        $this->putJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$device->id}/tags", ['tags' => ['plan' => 'premium']]);
        $this->assertSame(1, $premium->entries()->count());

        $runner->run();
        $this->assertSame(2, Message::whereNotNull('journey_id')->count());
    }

    public function test_the_editor_starts_from_recipes(): void
    {
        $this->actingAs($this->owner)->get(route('projects.journeys.create', [$this->project, 'recipe' => 'welcome']))->assertOk()->assertSee('journeyEditor', false);
        $this->actingAs($this->owner)->post(route('projects.journeys.store', $this->project), ['name' => 'Empty', 'trigger' => ['type' => 'new_device'], 'steps' => [['type' => 'push', 'body' => '']]])
            ->assertSessionHasErrors('steps.0.body');
    }

    public function test_custom_events_are_attributed_to_messages_and_start_journeys(): void
    {
        [, $key] = ApiKey::generate($this->project, 'Backend');
        $device = $this->device('ada-phone', ['external_id' => 'ada']);
        $journey = $this->project->journeys()->create(['name' => 'Thanks', 'status' => 'active', 'trigger' => ['type' => 'event', 'event' => 'recharge'], 'steps' => [['type' => 'push', 'body' => 'Merci pour la recharge']]]);

        // A campaign reaches Ada; she tops up an hour later without clicking: influenced.
        $messageId = $this->withHeader('Authorization', 'Key '.$key)->postJson('/api/v1/notifications', ['included_segments' => ['All'], 'contents' => ['en' => 'Bonus']])->json('id');
        $this->travel(1)->hours();
        $this->withHeader('Authorization', 'Key '.$key)->postJson("/api/v1/apps/{$this->project->app_id}/custom_events", [
            'events' => [
                ['name' => 'recharge', 'external_id' => 'ada', 'properties' => ['amount' => 1000]],
                ['name' => 'recharge', 'external_id' => 'nobody'],
            ],
        ])->assertOk()->assertJsonPath('recorded', 1)->assertJsonPath('unknown_users', 1);

        $event = CustomEvent::firstOrFail();
        $this->assertSame([$messageId, 'influenced', '1000.00'], [$event->message_id, $event->attribution, $event->value]);
        $this->assertSame(1, $journey->entries()->count());

        // After a click, the next event is direct (from the SDK, with a value).
        $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$device->id}/events", ['message_id' => $messageId, 'event' => 'clicked']);
        $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$device->id}/custom-events", ['name' => 'purchase', 'value' => 2500])->assertOk();
        $this->assertSame('direct', CustomEvent::where('name', 'purchase')->value('attribution'));

        $this->actingAs($this->owner)->get(route('projects.messages.show', [$this->project, $messageId]))->assertOk()->assertSee('Conversions')->assertSee('recharge');
        $this->actingAs($this->owner)->get(route('projects.stats', $this->project))->assertOk()->assertSee('purchase')->assertSee('2 500');
        $this->actingAs($this->owner)->get(route('projects.subscribers.show', [$this->project, $device->subscriber_id]))->assertOk()->assertSee('recharge');
    }
}
