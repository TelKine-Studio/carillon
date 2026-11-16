<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Journeys\JourneyRunner;
use App\Models\ApiKey;
use App\Models\CustomEvent;
use App\Models\InAppMessage;
use App\Models\Journey;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function test_in_app_messages_are_served_to_the_sdk_and_counted(): void
    {
        $device = $this->device('ada-phone');
        $this->actingAs($this->owner)->post(route('projects.in-app.store', $this->project), [
            'name' => 'Nouveau forfait', 'layout' => 'modal', 'title' => 'Nouveau !', 'body' => 'Découvrez le forfait illimité',
            'buttons' => [['text' => 'Voir', 'action' => 'url', 'url' => 'marecharge://forfaits'], ['text' => '', 'action' => 'dismiss']],
            'trigger_type' => 'app_open', 'segment' => '', 'frequency' => 'once',
        ])->assertSessionHasNoErrors();
        $message = InAppMessage::firstOrFail();
        $sdk = "/api/v1/sdk/apps/{$this->project->app_id}";

        // A draft is not served.
        $this->getJson("{$sdk}/subscriptions/{$device->id}/in-app-messages")->assertOk()->assertJsonCount(0, 'in_app_messages');

        $this->actingAs($this->owner)->put(route('projects.in-app.status', [$this->project, $message]), ['status' => 'active']);
        $this->getJson("{$sdk}/subscriptions/{$device->id}/in-app-messages")
            ->assertOk()
            ->assertJsonPath('in_app_messages.0.title', 'Nouveau !')
            ->assertJsonPath('in_app_messages.0.buttons.0.url', 'marecharge://forfaits')
            ->assertJsonCount(1, 'in_app_messages.0.buttons');

        // Limited to iOS users: this Android phone no longer gets it.
        $message->update(['segment' => 'iOS Users']);
        $this->getJson("{$sdk}/subscriptions/{$device->id}/in-app-messages")->assertJsonCount(0, 'in_app_messages');

        $this->postJson("{$sdk}/in-app-messages/{$message->id}/events", ['subscription_id' => $device->id, 'event' => 'shown'])->assertOk();
        $this->postJson("{$sdk}/in-app-messages/{$message->id}/events", ['subscription_id' => $device->id, 'event' => 'clicked', 'button_id' => 'button-1'])->assertOk();
        $this->assertSame([1, 1], [$message->fresh()->shown, $message->fresh()->clicked]);
        $this->getJson("{$sdk}/subscriptions/not-a-uuid/in-app-messages")->assertNotFound();

        $this->actingAs($this->owner)->get(route('projects.in-app.index', $this->project))->assertOk()->assertSee('Nouveau forfait');
        $this->actingAs($this->owner)->get(route('projects.in-app.edit', [$this->project, $message]))->assertOk()->assertSee('Découvrez');
    }

    public function test_branches_event_waits_and_sending_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'UTC'));
        $this->actingAs($this->owner)->post(route('projects.journeys.store', $this->project), [
            'name' => 'Cart',
            'trigger' => ['type' => 'event', 'event' => 'cart'],
            'steps' => [
                ['type' => 'wait_event', 'event' => 'purchase', 'amount' => 2, 'unit' => 'hours', 'on_timeout' => 'continue'],
                ['type' => 'condition', 'check' => 'event', 'event' => 'purchase', 'operator' => 'is_not', 'otherwise' => 'goto', 'goto' => 4],
                ['type' => 'push', 'body' => 'Votre panier vous attend'],
                ['type' => 'push', 'body' => 'Merci pour votre achat'],
            ],
            'window_enabled' => 1, 'window_from' => 9, 'window_to' => 20,
        ])->assertSessionHasNoErrors();
        $journey = Journey::firstOrFail();
        $this->assertSame(['from' => 9, 'to' => 20], $journey->send_window);
        $this->assertSame(3, $journey->steps[1]['else_step']);
        $journey->forceFill(['status' => 'active'])->save();
        $this->actingAs($this->owner)->get(route('projects.journeys.show', [$this->project, $journey]))->assertOk()->assertSee('purchase');

        $key = ApiKey::generate($this->project, 'test')[1];
        $event = fn (string $externalId, string $name) => $this->postJson('/api/v1/apps/'.$this->project->app_id.'/custom_events', ['events' => [['name' => $name, 'external_id' => $externalId]]], ['Authorization' => 'Key '.$key])->assertSuccessful();
        $ada = $this->device('ada', ['external_id' => 'ada']);
        $bob = $this->device('bob', ['external_id' => 'bob']);
        $event('ada', 'cart');
        $event('bob', 'cart');
        $runner = app(JourneyRunner::class);
        $runner->run();
        $this->assertSame(2, $journey->entries()->whereNotNull('waiting_since')->count());

        // Ada buys: she moves on right away, to the "thank you" branch.
        $this->travel(10)->minutes();
        $event('ada', 'purchase');
        $runner->run();
        $this->assertSame(1, Message::where('journey_step', 3)->value('recipients'));
        $this->assertSame('completed', $journey->entries()->where('subscriber_id', $ada->subscriber_id)->value('status'));
        $this->assertNull(Message::where('journey_step', 2)->first());

        // Bob does not: after two hours he gets the reminder, but only from 9:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00', 'UTC'));
        $runner->run();
        $entry = $journey->entries()->where('subscriber_id', $bob->subscriber_id)->first();
        $this->assertSame('2026-11-21 09:00', $entry->next_run_at->utc()->format('Y-m-d H:i'));
        $this->assertNull(Message::where('journey_step', 2)->first());
        $this->travelTo(CarbonImmutable::parse('2026-11-21 09:01', 'UTC'));
        $runner->run();
        $this->assertSame(1, Message::where('journey_step', 2)->value('recipients'));

        // A jump must lead to another existing step.
        $this->put(route('projects.journeys.update', [$this->project, $journey]), [
            'name' => 'Cart', 'trigger' => ['type' => 'event', 'event' => 'cart'],
            'steps' => [['type' => 'condition', 'check' => 'clicked', 'otherwise' => 'goto', 'goto' => 1]],
        ])->assertSessionHasErrors('steps.0.goto');
    }

    public function test_in_app_timing_recipes_and_ab_test(): void
    {
        $this->actingAs($this->owner)->get(route('projects.in-app.create', [$this->project, 'recipe' => 'review']))->assertOk()->assertSee('A review helps us a lot', false);
        $this->post(route('projects.in-app.store', $this->project), [
            'name' => 'Promo', 'layout' => 'modal', 'title' => 'A', 'body' => 'Texte A', 'trigger_type' => 'app_open', 'frequency' => 'once',
            'trigger_delay' => 5, 'min_sessions' => 3, 'ab_enabled' => 1, 'variant_b_title' => 'B', 'variant_b_body' => 'Texte B',
        ])->assertSessionHasNoErrors();
        $message = InAppMessage::firstOrFail();
        $this->assertSame(['type' => 'app_open', 'delay_seconds' => 5, 'min_sessions' => 3], $message->trigger);
        $message->forceFill(['status' => 'active'])->save();

        $base = "/api/v1/sdk/apps/{$this->project->app_id}";
        // Shown from the third session only.
        $devices = collect(range(1, 12))->map(fn ($i) => $this->device("device-$i"));
        $this->getJson("$base/subscriptions/{$devices[0]->id}/in-app-messages")->assertJsonCount(0, 'in_app_messages');
        Subscription::query()->update(['sessions' => 3]);

        $bodies = $devices->map(fn ($device) => $this->getJson("$base/subscriptions/{$device->id}/in-app-messages")->json('in_app_messages.0'));
        $this->assertSame(['a', 'b'], $bodies->pluck('variant')->unique()->sort()->values()->all());
        $this->assertTrue($bodies->every(fn ($m) => $m['body'] === ($m['variant'] === 'b' ? 'Texte B' : 'Texte A')));
        // The same device always sees the same version.
        $this->assertSame($bodies[0]['variant'], $this->getJson("$base/subscriptions/{$devices[0]->id}/in-app-messages")->json('in_app_messages.0.variant'));

        $this->postJson("$base/in-app-messages/{$message->id}/events", ['subscription_id' => $devices[0]->id, 'event' => 'shown', 'variant' => 'b'])->assertOk();
        $this->postJson("$base/in-app-messages/{$message->id}/events", ['subscription_id' => $devices[0]->id, 'event' => 'clicked', 'variant' => 'b'])->assertOk();
        $this->postJson("$base/in-app-messages/{$message->id}/events", ['subscription_id' => $devices[1]->id, 'event' => 'shown', 'variant' => 'a'])->assertOk();
        $message->refresh();
        $this->assertSame([1, 0, 1, 1], [$message->shown, $message->clicked, $message->shown_b, $message->clicked_b]);
        $this->get(route('projects.in-app.index', $this->project))->assertOk()->assertSee('B 100,0 %', false);
    }

    public function test_online_now(): void
    {
        $base = "/api/v1/sdk/apps/{$this->project->app_id}";
        $ada = $this->device('ada');
        $bob = $this->device('bob');
        $this->postJson("$base/subscriptions/{$ada->id}/heartbeat")->assertOk();
        $this->postJson("$base/subscriptions/{$bob->id}/heartbeat")->assertOk();
        $this->actingAs($this->owner)->getJson(route('projects.live', $this->project))->assertOk()
            ->assertJsonPath('online', 2)->assertJsonPath('people', 2)->assertJsonPath('platforms.android', 2);

        // Three minutes without a sign of life: gone.
        $this->travel(3)->minutes();
        $this->postJson("$base/subscriptions/{$ada->id}/heartbeat")->assertOk();
        $this->getJson(route('projects.live', $this->project))->assertJsonPath('online', 1);
        $this->get(route('projects.show', $this->project))->assertOk()->assertSee('Online now');
        $this->postJson("$base/subscriptions/00000000-0000-0000-0000-000000000000/heartbeat")->assertNotFound();
    }
}
