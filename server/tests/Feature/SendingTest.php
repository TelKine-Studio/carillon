<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Delivery;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\User;
use App\Push\Result;
use App\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SendingTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->admin()->create();
        $this->project = Project::factory()->create(['name' => 'Shop']);
        $this->project->addMember($owner, ProjectRole::Owner);
        [, $this->key] = ApiKey::generate($this->project, 'Backend');

        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ec, $p8);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'apns', 'details' => ['key_id' => 'ABC123DEFG', 'team_id' => '4ZA5MXYWG7', 'bundle_id' => 'com.example.shop'], 'secret' => $p8]);

        $rsa = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $pem);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'fcm', 'details' => ['project_id' => 'shop-123', 'client_email' => 'push@shop-123.iam.gserviceaccount.com'], 'secret' => json_encode([
            'type' => 'service_account', 'project_id' => 'shop-123', 'client_email' => 'push@shop-123.iam.gserviceaccount.com', 'private_key' => $pem,
        ])]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'api.push.apple.com/3/device/dead-ios' => Http::response(['reason' => 'Unregistered'], 410),
            'api.push.apple.com/*' => Http::response(null, 200),
            'api.sandbox.push.apple.com/*' => Http::response(null, 200),
            'fcm.googleapis.com/*' => function (HttpRequest $request) {
                return $request['message']['token'] === 'dead-android'
                    ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/shop-123/messages/1']);
            },
        ]);
    }

    /** Registers a device through the SDK endpoint. */
    protected function device(string $type, string $token, array $extra = []): string
    {
        return $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => $type, 'token' => $token, ...$extra])
            ->assertCreated()
            ->json('id');
    }

    protected function api(): static
    {
        return $this->withHeader('Authorization', 'Key '.$this->key);
    }

    public function test_the_sdk_registers_devices_people_and_tags(): void
    {
        $id = $this->device('ios', 'token-1', ['environment' => 'sandbox', 'language' => 'fr-CI', 'timezone' => 'Africa/Abidjan', 'app_version' => '2.1.0']);

        // Same token again: same device.
        $this->assertSame($id, $this->device('ios', 'token-1'));

        $sdk = "/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$id}";
        $this->putJson("{$sdk}/tags", ['tags' => ['plan' => 'gold', 'level' => 7]])->assertOk()->assertJsonPath('tags.plan', 'gold');
        $this->postJson("{$sdk}/login", ['external_id' => 'user-42'])->assertOk()->assertJsonPath('external_id', 'user-42');

        // Tags of the anonymous person follow the login.
        $subscription = Subscription::findOrFail($id);
        $this->assertSame(['plan' => 'gold', 'level' => '7'], $subscription->subscriber->tags);
        $this->assertSame('fr', $subscription->subscriber->language);

        $this->api()->getJson("/api/v1/apps/{$this->project->app_id}/users/by/external_id/user-42")
            ->assertOk()
            ->assertJsonPath('properties.tags.plan', 'gold')
            ->assertJsonPath('subscriptions.0.type', 'iOSPush');

        $this->putJson("{$sdk}/tags", ['tags' => ['plan' => null]])->assertJsonMissingPath('tags.plan');
        $this->postJson("{$sdk}/logout")->assertJsonPath('external_id', null);
    }

    public function test_a_notification_is_sent_to_every_platform_and_dead_tokens_are_disabled(): void
    {
        $this->device('ios', 'live-ios', ['language' => 'fr']);
        $this->device('ios', 'dead-ios');
        $this->device('android', 'live-android');
        $this->device('android', 'dead-android');

        $id = $this->api()->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'included_segments' => ['All'],
            'headings' => ['en' => 'Sale', 'fr' => 'Soldes'],
            'contents' => ['en' => '-20% today', 'fr' => '-20 % aujourd’hui'],
            'data' => ['order' => 42],
            'collapse_id' => 'sale',
        ])->assertOk()->assertJsonPath('recipients', 4)->json('id');

        $message = Message::findOrFail($id);
        $this->assertSame('sent', $message->status);
        $this->assertSame([4, 2, 2, 0], [$message->recipients, $message->successful, $message->failed, $message->errored]);
        $this->assertSame(2, Subscription::whereNotNull('invalid_at')->count());

        // The French device received the French text, with the collapse id.
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/live-ios')
            && $request['aps']['alert']['title'] === 'Soldes'
            && $request['carillon']['d'] === ['order' => 42]
            && $request->header('apns-collapse-id') === ['sale']
            && str_starts_with($request->header('authorization')[0], 'bearer '));
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'fcm.googleapis.com')
            && json_decode($request['message']['data']['carillon'], true)['title'] === 'Sale');

        $this->api()->getJson("/api/v1/notifications/{$id}?app_id={$this->project->app_id}")
            ->assertOk()
            ->assertJsonPath('successful', 2)
            ->assertJsonPath('failed', 2)
            ->assertJsonPath('platform_delivery_stats.ios.successful', 1)
            ->assertJsonPath('platform_delivery_stats.android.failed', 1);

        // A second send skips the disabled devices.
        $this->api()->postJson('/api/v1/notifications', ['included_segments' => ['All'], 'contents' => ['en' => 'Again']])
            ->assertJsonPath('recipients', 2);
    }

    public function test_devices_still_running_the_onesignal_sdk_receive_the_onesignal_format(): void
    {
        // Imported from OneSignal, app not updated yet.
        $this->device('android', 'old-android');
        $this->device('ios', 'old-ios');
        Subscription::query()->update(['sdk' => Subscription::ONESIGNAL_SDK]);
        // Already on the Carillon SDK.
        $this->device('android', 'new-android', ['sdk' => 'carillon-flutter/1.0.0']);

        $this->api()->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'included_segments' => ['All'],
            'headings' => ['en' => 'Bonus'],
            'contents' => ['en' => 'Top up today'],
            'data' => ['screen' => 'offers'],
            'url' => 'marecharge://offers',
            'big_picture' => 'https://cdn.example.com/bonus.png',
            'buttons' => [['id' => 'go', 'text' => 'See']],
        ])->assertOk()->assertJsonPath('recipients', 3);

        $sent = fn (string $token) => Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/'.$token)
            || ($request['message']['token'] ?? null) === $token)->first()[0];

        // Android, OneSignal SDK: its data keys, all strings.
        $data = $sent('old-android')['message']['data'];
        $this->assertSame('Top up today', $data['alert']);
        $this->assertSame('Bonus', $data['title']);
        $this->assertSame('https://cdn.example.com/bonus.png', $data['bicon']);
        $custom = json_decode($data['custom'], true);
        $this->assertNotEmpty($custom['i']);
        $this->assertSame(['screen' => 'offers'], $custom['a']);
        $this->assertSame('marecharge://offers', $custom['u']);
        $this->assertSame([['i' => 'go', 'n' => 'See']], json_decode($data['o'], true));
        $this->assertArrayNotHasKey('carillon', $data);
        $this->assertContainsOnly('string', $data);

        // iOS, OneSignal SDK: the alert, plus "custom", the image and the buttons.
        $ios = $sent('old-ios');
        $this->assertSame('Bonus', $ios['aps']['alert']['title']);
        $this->assertSame(1, $ios['aps']['mutable-content']);
        $this->assertSame(['screen' => 'offers'], $ios['custom']['a']);
        $this->assertSame(['id' => 'https://cdn.example.com/bonus.png'], $ios['att']);
        $this->assertSame([['i' => 'go', 'n' => 'See']], $ios['buttons']);
        $this->assertArrayNotHasKey('carillon', $ios->data());

        // Carillon SDK: its own format.
        $this->assertArrayHasKey('carillon', $sent('new-android')['message']['data']);

        // Once the updated app registers with the Carillon SDK, the device switches format.
        $this->device('android', 'old-android', ['sdk' => 'carillon-flutter/1.0.0']);
        $this->assertFalse(Subscription::where('token', 'old-android')->first()->usesOneSignalSdk());
    }

    public function test_targeting_by_external_id_tags_segments_and_platform(): void
    {
        $gold = $this->device('ios', 'gold', ['external_id' => 'alice', 'tags' => ['plan' => 'gold', 'spent' => '120']]);
        $this->device('android', 'silver', ['external_id' => 'bob', 'tags' => ['plan' => 'silver', 'spent' => '30']]);
        $this->device('android', 'anon');
        Segment::create(['project_id' => $this->project->id, 'name' => 'Big spenders', 'filters' => [['field' => 'tag', 'key' => 'spent', 'relation' => '>', 'value' => '100']]]);

        $count = fn (array $targeting) => $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], ...$targeting])->json('recipients');

        $this->assertSame(1, $count(['include_aliases' => ['external_id' => ['alice']]]));
        $this->assertSame(2, $count(['include_external_user_ids' => ['alice', 'bob']]));
        $this->assertSame(1, $count(['include_subscription_ids' => [$gold]]));
        $this->assertSame(1, $count(['filters' => [['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'silver']]]));
        $this->assertSame(2, $count(['filters' => [
            ['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'gold'], ['operator' => 'OR'],
            ['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'silver'],
        ]]));
        $this->assertSame(1, $count(['included_segments' => ['Big spenders']]));
        $this->assertSame(2, $count(['included_segments' => ['All'], 'excluded_segments' => ['Big spenders']]));
        $this->assertSame(2, $count(['included_segments' => ['All'], 'isAndroid' => true]));

        $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], 'included_segments' => ['Nope']])
            ->assertStatus(400)->assertJsonPath('errors.0', 'Unknown segment: Nope');
        $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi']])
            ->assertStatus(400)->assertJsonPath('errors.0', 'You must include which players, segments, or tags you wish to send this notification to.');
        $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], 'include_aliases' => ['external_id' => ['nobody']]])
            ->assertStatus(400)->assertJsonPath('errors.0', 'All included players are not subscribed');
    }

    public function test_api_keys_are_required_and_scoped_to_their_project(): void
    {
        $this->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], 'included_segments' => ['All']])->assertForbidden();
        $this->withHeader('Authorization', 'Key crl_wrong')->postJson('/api/v1/notifications', [])->assertForbidden();

        $other = Project::factory()->create();
        $this->api()->postJson('/api/v1/notifications', ['app_id' => $other->app_id, 'contents' => ['en' => 'Hi'], 'included_segments' => ['All']])
            ->assertForbidden();
    }

    public function test_scheduled_messages_can_be_canceled_or_go_out_on_time(): void
    {
        $this->device('ios', 'live-ios');

        $id = $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Later'], 'included_segments' => ['All'], 'send_after' => now()->addHour()->toIso8601String()])
            ->assertOk()->json('id');
        $this->assertSame('scheduled', Message::find($id)->status);

        $this->artisan('carillon:dispatch-scheduled');
        $this->assertSame('scheduled', Message::find($id)->status);

        $this->travel(61)->minutes();
        $this->artisan('carillon:dispatch-scheduled');
        $this->assertSame('sent', Message::find($id)->status);
        $this->api()->deleteJson("/api/v1/notifications/{$id}")->assertStatus(400);

        $later = $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Later'], 'included_segments' => ['All'], 'send_after' => now()->addDay()->toIso8601String()])->json('id');
        $this->api()->deleteJson("/api/v1/notifications/{$later}")->assertOk()->assertJsonPath('success', true);
        $this->assertSame('canceled', Message::find($later)->status);
    }

    public function test_idempotency_keys_prevent_duplicates_and_clicks_are_counted(): void
    {
        $device = $this->device('android', 'live-android');
        $payload = ['contents' => ['en' => 'Once'], 'included_segments' => ['All'], 'idempotency_key' => 'order-42-shipped'];

        $id = $this->api()->postJson('/api/v1/notifications', $payload)->json('id');
        $this->assertSame($id, $this->api()->postJson('/api/v1/notifications', $payload)->json('id'));
        $this->assertSame(1, Message::count());

        $events = "/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$device}/events";
        $this->postJson($events, ['message_id' => $id, 'event' => 'received'])->assertOk();
        $this->postJson($events, ['message_id' => $id, 'event' => 'clicked'])->assertOk();
        $this->postJson($events, ['message_id' => $id, 'event' => 'clicked'])->assertOk();

        $message = Message::find($id);
        $this->assertSame([1, 1], [$message->received, $message->clicked]);
        $this->assertNotNull(Delivery::sole()->clicked_at);
    }

    public function test_web_push_and_missing_keys(): void
    {
        $this->device('web', json_encode(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'auth']]));

        // No web push keys: the browser is counted as errored.
        $id = $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], 'included_segments' => ['All']])->json('id');
        $this->assertSame(1, Message::find($id)->errored);

        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'webpush', 'details' => ['public_key' => 'pub', 'subject' => 'mailto:a@b.c'], 'secret' => 'priv']);
        $sender = Mockery::mock(WebPushSender::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(fn ($credential, $message, $subscriptions) => $subscriptions->mapWithKeys(fn ($s) => [$s->id => Result::sent()])->all());
        $this->app->instance(WebPushSender::class, $sender);

        $id = $this->api()->postJson('/api/v1/notifications', ['contents' => ['en' => 'Hi'], 'included_segments' => ['All'], 'isAnyWeb' => true])->json('id');
        $this->assertSame(1, Message::find($id)->successful);
    }

    public function test_the_composer_sends_tests_to_test_devices_only(): void
    {
        $owner = $this->project->members()->first();
        $this->device('ios', 'my-phone');
        $this->device('android', 'customer-phone');

        // No test device yet.
        $this->actingAs($owner)->postJson(route('projects.messages.test', $this->project), ['texts' => [['language' => 'en', 'body' => 'Hello']]])
            ->assertStatus(422)->assertJsonStructure(['error']);

        $phone = Subscription::where('token', 'my-phone')->first();
        $this->actingAs($owner)->put(route('projects.subscribers.test-device', [$this->project, $phone->subscriber_id, $phone->id]), ['is_test' => 1])->assertRedirect();
        $this->assertTrue($phone->fresh()->is_test);

        // The segments of the form are ignored: only the test device receives it.
        $this->actingAs($owner)->postJson(route('projects.messages.test', $this->project), [
            'name' => 'Bonus', 'audience' => 'segments', 'included_segments' => ['Subscribed Users'],
            'texts' => [['language' => 'en', 'title' => 'Bonus', 'body' => 'Top up today']],
        ])->assertOk()->assertJsonPath('recipients', 1);

        $message = Message::latest()->first();
        $this->assertTrue($message->is_test);
        $this->assertSame('Test · Bonus', $message->name);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/my-phone'));
        Http::assertNotSent(fn (HttpRequest $request) => ($request['message']['token'] ?? null) === 'customer-phone');

        // Test sends stay out of the statistics.
        $this->actingAs($owner)->get(route('projects.stats', $this->project))->assertOk()->assertSee('Statistics');
    }
}
