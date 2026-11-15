<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Jobs\DeliverWebhook;
use App\Jobs\DispatchMessage;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Models\Webhook;
use App\Webhooks\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Webhooks::flush();

        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $this->owner->id]);
        $this->project->addMember($this->owner, ProjectRole::Owner);
    }

    protected function webhook(array $events = Webhook::EVENTS): Webhook
    {
        return $this->project->webhooks()->create(['url' => 'https://hooks.test/carillon', 'events' => $events, 'secret' => 'whsec_test']);
    }

    public function test_settings_page_and_management(): void
    {
        $this->actingAs($this->owner);
        $this->get(route('projects.settings', [$this->project, 'webhooks']))->assertOk()->assertSee('No webhook yet');

        $this->post(route('projects.webhooks.store', $this->project), ['url' => 'https://hooks.test/a', 'events' => ['message.sent']])->assertRedirect();
        $webhook = $this->project->webhooks()->first();
        $this->assertStringStartsWith('whsec_', $webhook->secret);
        $this->assertSame(['message.sent'], $webhook->events);

        $this->post(route('projects.webhooks.store', $this->project), ['url' => 'ftp://x', 'events' => ['nope']])->assertSessionHasErrors(['url', 'events.0']);

        $this->put(route('projects.webhooks.update', [$this->project, $webhook]), ['url' => 'https://hooks.test/b', 'events' => ['notification.clicked', 'message.sent']])->assertRedirect();
        $this->assertSame('https://hooks.test/b', $webhook->fresh()->url);

        $this->put(route('projects.webhooks.update', [$this->project, $webhook]), ['enabled' => 0])->assertRedirect();
        $this->assertFalse($webhook->fresh()->enabled);

        $secret = $webhook->fresh()->secret;
        $this->post(route('projects.webhooks.rotate', [$this->project, $webhook]))->assertRedirect();
        $this->assertNotSame($secret, $webhook->fresh()->secret);

        Http::fake(['hooks.test/*' => Http::response('ok')]);
        $this->post(route('projects.webhooks.test', [$this->project, $webhook]))->assertSessionHas('status');
        $this->assertSame(200, $webhook->deliveries()->first()->status);
        $this->get(route('projects.settings', [$this->project, 'webhooks']))->assertOk()->assertSee('webhook.test');

        $this->delete(route('projects.webhooks.destroy', [$this->project, $webhook]))->assertRedirect();
        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_events_are_signed_and_delivered(): void
    {
        $webhook = $this->webhook();
        Http::fake(['hooks.test/*' => Http::response('ok')]);

        $payload = Webhooks::payload('notification.clicked', ['message_id' => 'm1']);
        (new DeliverWebhook($webhook->id, $payload))->handle();

        Http::assertSent(function (Request $request) {
            [$t, $v1] = array_map(fn ($part) => explode('=', $part, 2)[1], explode(',', $request->header('Carillon-Signature')[0]));

            return $request->url() === 'https://hooks.test/carillon'
                && $request->header('Carillon-Event')[0] === 'notification.clicked'
                && hash_equals(hash_hmac('sha256', $t.'.'.$request->body(), 'whsec_test'), $v1)
                && $request['data']['message_id'] === 'm1'
                && $request['project']['app_id'] === $this->project->app_id;
        });
        $this->assertNotNull($webhook->fresh()->last_delivery_at);
    }

    public function test_failures_disable_the_webhook(): void
    {
        $webhook = $this->webhook();
        $webhook->forceFill(['failures' => Webhook::MAX_FAILURES - 1])->save();
        Http::fake(['hooks.test/*' => Http::response('nope', 500)]);

        $job = new DeliverWebhook($webhook->id, Webhooks::payload('message.sent', []));
        try {
            $job->handle();
            $this->fail('A failed delivery throws, to be retried.');
        } catch (\RuntimeException) {
        }
        $job->failed(null);

        $webhook->refresh();
        $this->assertFalse($webhook->enabled);
        $this->assertSame('failures', $webhook->disabled_reason);
        $this->assertSame(500, $webhook->deliveries()->first()->status);
    }

    public function test_sdk_and_engine_fire_events(): void
    {
        $this->webhook(['subscription.created', 'subscription.unsubscribed', 'notification.clicked', 'message.sent']);
        $this->webhook(['notification.received']);
        Queue::fake([DeliverWebhook::class]);

        $base = '/api/v1/sdk/apps/'.$this->project->app_id;
        $id = $this->postJson("$base/subscriptions", ['type' => 'ios', 'token' => str_repeat('b', 64), 'external_id' => 'u1'])->assertCreated()->json('id');
        // Already known: no second creation event.
        $this->postJson("$base/subscriptions", ['type' => 'ios', 'token' => str_repeat('b', 64)])->assertCreated();

        $this->patchJson("$base/subscriptions/$id", ['enabled' => false])->assertOk();

        $message = Message::create(['project_id' => $this->project->id, 'contents' => ['en' => 'Hi'], 'targeting' => ['segments' => ['All']], 'status' => 'sending', 'recipients' => 1, 'successful' => 1]);
        \DB::table('deliveries')->insert(['message_id' => $message->id, 'subscription_id' => $id, 'status' => 'sent', 'sent_at' => now()]);
        $this->postJson("$base/subscriptions/$id/events", ['message_id' => $message->id, 'event' => 'clicked', 'action_id' => 'track'])->assertOk();
        $this->postJson("$base/subscriptions/$id/events", ['message_id' => $message->id, 'event' => 'clicked'])->assertOk(); // counted once
        $this->postJson("$base/subscriptions/$id/events", ['message_id' => $message->id, 'event' => 'received'])->assertOk();

        DispatchMessage::complete($message->id);

        $types = collect(Queue::pushed(DeliverWebhook::class))->map(fn ($job) => $job->payload['type'])->countBy()->all();
        $this->assertSame(['subscription.created' => 1, 'subscription.unsubscribed' => 1, 'notification.clicked' => 1, 'notification.received' => 1, 'message.sent' => 1], $types);

        $clicked = collect(Queue::pushed(DeliverWebhook::class))->first(fn ($job) => $job->payload['type'] === 'notification.clicked');
        $this->assertSame(['message_id' => $message->id, 'subscription_id' => $id, 'external_id' => 'u1', 'platform' => 'ios', 'action_id' => 'track'], $clicked->payload['data']);
    }
}
