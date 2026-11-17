<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\AbTest;
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

        // The report lists the devices of each outcome.
        $owner = $this->project->members()->first();
        $this->actingAs($owner)->get(route('projects.messages.show', [$this->project, $id]))->assertOk()
            ->assertSee('Deliveries by platform')->assertSee('Audience activity');
        $this->get(route('projects.messages.activity', [$this->project, $id, 'tab' => 'delivered']))->assertOk()->assertSee('iOS')->assertSee('Android');
        $this->get(route('projects.messages.activity', [$this->project, $id, 'tab' => 'refused']))->assertOk()->assertSee('Unregistered');
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

    public function test_a_message_report_shows_clicks_over_time_and_exports_deliveries(): void
    {
        $owner = $this->project->members()->first();
        $this->device('ios', 'live-ios');
        $this->device('android', 'live-android');
        $id = $this->api()->postJson('/api/v1/notifications', ['included_segments' => ['All'], 'contents' => ['en' => 'Promo']])->json('id');
        $message = Message::findOrFail($id);
        $message->deliveries()->first()->forceFill(['clicked_at' => $message->started_at->copy()->addMinutes(20)])->save();
        $message->forceFill(['clicked' => 1])->save();

        $this->actingAs($owner)->get(route('projects.messages.show', [$this->project, $message]))
            ->assertOk()->assertSee('Clicks over time')->assertSee(route('projects.messages.export', [$this->project, $message]), false);

        $csv = $this->actingAs($owner)->get(route('projects.messages.export', [$this->project, $message]))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('subscription_id,external_id,platform', $lines[0]);
        $this->assertStringContainsString(',sent,', $csv);
        $this->assertStringContainsString('ios', $csv);
    }

    public function test_drafts_are_saved_without_sending_and_replaced_when_sent(): void
    {
        $owner = $this->project->members()->first();
        $this->device('ios', 'live-ios');
        $form = ['name' => 'Promo', 'audience' => 'segments', 'included_segments' => ['Subscribed Users'], 'texts' => [['language' => 'en', 'body' => 'Bonus today']]];

        $this->actingAs($owner)->post(route('projects.messages.store', $this->project), $form + ['save_draft' => 1])
            ->assertRedirect(route('projects.messages.index', [$this->project, 'status' => 'draft']));
        $draft = Message::firstOrFail();
        $this->assertSame('draft', $draft->status);
        Http::assertNothingSent();

        $this->actingAs($owner)->get(route('projects.messages.index', [$this->project, 'status' => 'draft']))->assertOk()->assertSee('Promo');
        $this->actingAs($owner)->get(route('projects.messages.create', [$this->project, 'from' => $draft->id]))->assertOk()->assertSee('name="draft_id"', false);

        // Sending from the draft: the draft goes away, the message goes out.
        $this->actingAs($owner)->post(route('projects.messages.store', $this->project), $form + ['draft_id' => $draft->id])->assertRedirect();
        $this->assertNull(Message::find($draft->id));
        $this->assertSame(['sent'], Message::pluck('status')->all());
    }

    public function test_the_daily_cap_skips_devices_that_received_enough(): void
    {
        $this->device('ios', 'busy-ios');
        $this->device('android', 'quiet-android');
        $this->project->update(['settings' => ['frequency_cap' => 1]]);

        $first = $this->api()->postJson('/api/v1/notifications', ['include_subscription_ids' => [Subscription::where('token', 'busy-ios')->value('id')], 'contents' => ['en' => 'One']])->json('id');
        $this->assertSame(1, Message::find($first)->successful);

        // The iPhone already got one today: only the Android phone receives the second.
        $second = $this->api()->postJson('/api/v1/notifications', ['included_segments' => ['All'], 'contents' => ['en' => 'Two']])->json('id');
        $message = Message::find($second);
        $this->assertSame(1, $message->capped);
        $this->assertSame(1, $message->recipients);
        $this->assertSame(1, $message->successful);
    }

    public function test_an_ab_test_sends_two_versions_to_a_sample_then_the_winner_to_the_rest(): void
    {
        $owner = $this->project->members()->first();
        foreach (range(1, 40) as $i) {
            $this->device('android', 'phone-'.$i);
        }

        $this->actingAs($owner)->post(route('projects.messages.store', $this->project), [
            'name' => 'Bonus', 'audience' => 'segments', 'included_segments' => ['Subscribed Users'],
            'texts' => [['language' => 'fr', 'title' => 'Bonus', 'body' => 'Version A']],
            'ab_enabled' => 1, 'ab_title' => 'Offre', 'ab_body' => 'Version B', 'ab_sample' => 50,
        ])->assertRedirect();

        $test = AbTest::firstOrFail();
        [$a, $b] = [$test->variant('A'), $test->variant('B')];
        $this->assertSame([10, 10], [$a->recipients, $b->recipients]);
        $this->assertCount(20, $test->remaining);
        $this->assertSame(['fr' => 'Version B'], $b->contents);
        $this->assertSame('Bonus · A', $a->name);
        // Nobody is in both samples.
        $this->assertSame(0, Delivery::where('message_id', $a->id)->whereIn('subscription_id', Delivery::where('message_id', $b->id)->pluck('subscription_id'))->count());

        $b->forceFill(['clicked' => 4])->save();
        $this->actingAs($owner)->get(route('projects.ab-tests.show', [$this->project, $test]))->assertOk()->assertSee('Version B')->assertSee('40,0 %');

        $this->actingAs($owner)->post(route('projects.ab-tests.winner', [$this->project, $test]), ['variant' => 'B'])->assertRedirect();
        $winner = Message::where('ab_variant', 'W')->firstOrFail();
        $this->assertSame(20, $winner->recipients);
        $this->assertSame(['fr' => 'Version B'], $winner->contents);
        $this->assertSame($b->id, $test->fresh()->winner_id);

        // Only once.
        $this->actingAs($owner)->post(route('projects.ab-tests.winner', [$this->project, $test]), ['variant' => 'A'])->assertSessionHasErrors('alert');
    }

    public function test_test_devices_have_a_list_names_and_a_segment(): void
    {
        $owner = $this->project->members()->first();
        $phone = $this->device('ios', 'my-phone', ['external_id' => 'admin-1']);
        $this->device('android', 'other-phone');

        $this->actingAs($owner)->post(route('projects.test-devices.store', $this->project), ['identifier' => 'admin-1', 'test_name' => 'iPhone LKAO'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('projects.test-devices.store', $this->project), ['identifier' => 'nobody'])->assertSessionHasErrors('identifier');
        $this->actingAs($owner)->get(route('projects.test-devices.index', $this->project))->assertRedirect(route('projects.subscribers.index', [$this->project, 'view' => 'test']));
        $this->actingAs($owner)->get(route('projects.subscribers.index', [$this->project, 'view' => 'test']))->assertOk()->assertSee('iPhone LKAO')->assertSee(__('Add a test device'))->assertDontSee('@js(', false);

        // Built-in segments, with the names of OneSignal too.
        foreach (['Test Devices' => 1, 'iOS Users' => 1, 'Android audience' => 1, 'Total Subscriptions' => 2] as $segment => $count) {
            $this->api()->postJson('/api/v1/notifications', ['included_segments' => [$segment], 'contents' => ['en' => 'Hi']])->assertJsonPath('recipients', $count);
        }

        $this->actingAs($owner)->delete(route('projects.test-devices.destroy', [$this->project, $phone]))->assertRedirect();
        $this->assertFalse(Subscription::find($phone)->is_test);
    }

    public function test_searches_work_on_any_column_type_and_ignore_case(): void
    {
        $owner = $this->project->members()->first();
        $id = $this->device('ios', 'my-phone', ['external_id' => 'LKAO-42', 'tags' => ['city' => 'Abidjan']]);
        $this->api()->postJson('/api/v1/notifications', ['included_segments' => ['All'], 'headings' => ['en' => 'Soldes'], 'contents' => ['en' => 'Promo du jour']]);

        // Not a UUID, part of an external id, a tag, any case.
        foreach (['lkao', 'abidjan', $id] as $term) {
            $this->actingAs($owner)->get(route('projects.subscribers.index', [$this->project, 'q' => $term]))->assertOk()->assertSee('LKAO-42');
        }
        $this->actingAs($owner)->get(route('projects.subscribers.index', [$this->project, 'q' => '100%_']))->assertOk()->assertDontSee('LKAO-42');
        $this->actingAs($owner)->get(route('projects.messages.index', [$this->project, 'q' => 'PROMO']))->assertOk()->assertSee('Soldes');
        $this->actingAs($owner)->get(route('admin.audit', ['q' => 'shop']))->assertOk();
        $this->actingAs($owner)->get(route('admin.users.index', ['q' => 'ZZZ']))->assertOk();
    }

    public function test_the_audience_lists_people_or_devices_sorted_like_onesignal(): void
    {
        $owner = $this->project->members()->first();
        $id = $this->device('ios', 'phone-a', ['external_id' => 'ada']);
        $this->device('android', 'phone-b', ['external_id' => 'bob']);
        $person = Subscription::find($id)->subscriber_id;

        $this->actingAs($owner)->get(route('projects.subscribers.index', $this->project))
            ->assertOk()->assertSee($person)->assertSee('ada')->assertSee('Channels');
        $this->actingAs($owner)->get(route('projects.subscribers.index', [$this->project, 'sort' => 'first_active', 'dir' => 'asc']))->assertOk()->assertSeeInOrder(['ada', 'bob']);
        $this->actingAs($owner)->get(route('projects.subscribers.index', [$this->project, 'view' => 'devices', 'q' => $person]))->assertOk()->assertSee('ada')->assertDontSee('bob');
        $this->actingAs($owner)->get(route('projects.subscribers.show', [$this->project, $person]))->assertOk()->assertSee('Push token')->assertSee('phone-a');

        // "Add as test devices" from the menu of a person.
        $this->actingAs($owner)->post(route('projects.test-devices.store', $this->project), ['identifier' => $person])->assertSessionHasNoErrors();
        $this->assertTrue(Subscription::find($id)->is_test);
    }
}
