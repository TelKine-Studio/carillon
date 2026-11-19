<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\LiveActivities\LiveActivities;
use App\Models\ApiKey;
use App\Models\LiveActivity;
use App\Models\LiveActivityCall;
use App\Models\LiveActivityStartToken;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LiveActivitiesTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
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
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/shop-123/messages/1']),
            'api.sandbox.push.apple.com/3/device/dead-start' => Http::response(['reason' => 'BadDeviceToken'], 400),
            'api.sandbox.push.apple.com/*' => Http::response(null, 200, ['apns-id' => 'A1B2C3D4-0000-0000-0000-000000000001', 'apns-unique-id' => 'u-123']),
            'api.push.apple.com/*' => Http::response(null, 200),
        ]);
    }

    protected function device(string $token, ?string $externalId = null, string $type = 'ios', ?string $sdk = null): string
    {
        $id = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", array_filter(['type' => $type, 'token' => $token, 'environment' => $type === 'ios' ? 'sandbox' : null, 'sdk' => $sdk]))
            ->assertCreated()->json('id');

        if ($externalId) {
            $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$id}/login", ['external_id' => $externalId])->assertOk();
        }

        return $id;
    }

    protected function sdk(string $id, string $path, array $data): TestResponse
    {
        return $this->json($path === 'start-token' ? 'PUT' : 'POST', "/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$id}/live-activities/{$path}", $data);
    }

    protected function api(): static
    {
        return $this->withHeader('Authorization', 'Key '.$this->key);
    }

    protected function start(array $data = []): TestResponse
    {
        return $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/activities/activity/DeliveryAttributes", [
            'event' => 'start',
            'activity_id' => 'MRC-0428',
            'event_attributes' => ['reference' => 'MRC-0428'],
            'event_updates' => ['step' => 'waiting'],
            'headings' => ['en' => 'Top-up in progress'],
            'contents' => ['en' => 'We are on it'],
            'include_aliases' => ['external_id' => ['user-1']],
            ...$data,
        ]);
    }

    public function test_a_start_without_texts_still_has_a_complete_alert_and_typed_state(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1'])->assertOk();

        // iOS refuses a start whose alert lacks the title or the body.
        $this->start(['headings' => null, 'contents' => null, 'name' => 'Recharge en cours', 'event_updates' => ['etat' => 'en_cours', 'progression' => 0.4]])->assertOk();

        Http::assertSent(function (HttpRequest $request) {
            $aps = $request['aps'] ?? [];

            return ($aps['event'] ?? null) === 'start'
                && $aps['alert'] === ['title' => 'Recharge en cours', 'body' => 'Recharge en cours']
                && str_contains($request->body(), '"progression":0.4');
        });
    }

    public function test_the_answers_of_apple_are_kept_on_the_activity(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-0123456789'])->assertOk();
        $this->start()->assertOk();

        $push = LiveActivity::sole()->pushes[0];
        $this->assertSame('start', $push['event']);
        $this->assertSame('sent', $push['result']);
        $this->assertSame(200, $push['status']);
        $this->assertSame('A1B2C3D4-0000-0000-0000-000000000001', $push['apns_id']);
        $this->assertSame('u-123', $push['apns_unique_id']);
        $this->assertSame('sandbox', $push['environment']);
        $this->assertSame('start-to…456789', $push['token']);

        $owner = User::factory()->admin()->create();
        $this->project->addMember($owner, ProjectRole::Owner);
        $this->actingAs($owner)->get(route('projects.live-activities.index', $this->project))->assertOk()->assertSee('A1B2C3D4-0000-0000-0000-000000000001');
    }

    public function test_starts_by_push_with_the_start_token_of_the_type(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->device('other-token', 'user-2');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1'])->assertOk();

        $this->start()->assertOk()->assertJson(['id' => 'MRC-0428', 'recipients' => 1, 'already' => 0]);

        Http::assertSent(function (HttpRequest $request) {
            $aps = $request['aps'] ?? [];

            return str_ends_with($request->url(), '/3/device/start-token-1')
                && $request->header('apns-push-type') === ['liveactivity']
                && $request->header('apns-topic') === ['com.example.shop.push-type.liveactivity']
                && $aps['event'] === 'start'
                && $aps['attributes-type'] === 'DeliveryAttributes'
                && $aps['input-push-token'] === 1
                && $aps['attributes'] === ['reference' => 'MRC-0428', 'carillon' => ['activityId' => 'MRC-0428']]
                && $aps['content-state'] === ['step' => 'waiting']
                && $aps['alert'] === ['title' => 'Top-up in progress', 'body' => 'We are on it'];
        });
        $this->assertSame('started', LiveActivity::sole()->status);

        // Twice: nothing more (the retry of a backend is harmless).
        $this->start()->assertOk()->assertJson(['recipients' => 0, 'already' => 1]);
        $this->assertSame(1, LiveActivity::count());
    }

    public function test_devices_without_start_token_are_not_counted(): void
    {
        $this->device('phone-token', 'user-1');

        $this->start()->assertOk()->assertJson(['recipients' => 0, 'targeted' => 1, 'skipped' => ['no_start_token' => 1, 'unsupported' => 0]]);
        $this->assertSame(0, LiveActivity::count());
        Http::assertNothingSent();
    }

    public function test_a_dead_start_token_is_forgotten(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'dead-start'])->assertOk();

        $this->start()->assertOk()->assertJson(['recipients' => 0, 'errors' => ['BadDeviceToken']]);
        $this->assertSame('failed', LiveActivity::sole()->status);
        $this->assertSame(0, LiveActivityStartToken::count());
    }

    public function test_updates_wait_for_the_token_then_leave(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);
        $this->start()->assertOk();

        // Delivered before the app sent the update token: kept.
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/MRC-0428/notifications", [
            'event' => 'end', 'event_updates' => ['step' => 'delivered'], 'dismissal_date' => 1900000000,
        ])->assertOk()->assertJson(['recipients' => 0, 'pending' => 1]);
        Http::assertSentCount(1);

        // The token arrives: the end leaves at once.
        $this->sdk($phone, 'enter', ['activity_id' => 'MRC-0428', 'activity_type' => 'DeliveryAttributes', 'token' => 'update-token-1'])->assertOk();

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/update-token-1')
            && $request['aps']['event'] === 'end'
            && $request['aps']['content-state'] === ['step' => 'delivered']
            && $request['aps']['dismissal-date'] === 1900000000
            && $request->header('apns-priority') === ['10']);
        $activity = LiveActivity::sole();
        $this->assertSame('ended', $activity->status);
        $this->assertSame(1, $activity->ends);

        // Ended: further updates find nothing.
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/MRC-0428/notifications", ['event' => 'update', 'event_updates' => ['step' => 'x']])
            ->assertNotFound();
    }

    public function test_update_reaches_every_device_showing_the_activity(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $tablet = $this->device('tablet-token', 'user-1');
        $this->sdk($phone, 'enter', ['activity_id' => 'game-42', 'activity_type' => 'ScoreAttributes', 'token' => 'update-phone'])->assertOk();
        $this->sdk($tablet, 'enter', ['activity_id' => 'game-42', 'activity_type' => 'ScoreAttributes', 'token' => 'update-tablet'])->assertOk();

        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/game-42/notifications", [
            'event' => 'update', 'event_updates' => ['home' => 2, 'away' => 1], 'priority' => 5,
        ])->assertOk()->assertJson(['recipients' => 2, 'pending' => 0]);

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request) => $request['aps']['event'] === 'update' && $request->header('apns-priority') === ['5']);
        $this->assertSame([1, 1], LiveActivity::pluck('updates')->all());

        // Dismissed on the tablet: only the phone gets the next one.
        $this->sdk($tablet, 'exit', ['activity_id' => 'game-42'])->assertOk();
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/game-42/notifications", ['event' => 'update', 'event_updates' => ['home' => 3]])
            ->assertOk()->assertJson(['recipients' => 1]);
    }

    public function test_forgotten_activities_are_ended(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);
        $this->start(['end_after' => 600])->assertOk();
        $this->sdk($phone, 'enter', ['activity_id' => 'MRC-0428', 'activity_type' => 'DeliveryAttributes', 'token' => 'update-token-1']);

        $this->travel(11)->minutes();
        $this->assertSame(1, app(LiveActivities::class)->sweep());

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/update-token-1') && $request['aps']['event'] === 'end');
        $this->assertSame('ended', LiveActivity::sole()->status);
    }

    public function test_an_activity_ended_before_its_token_is_closed_when_the_token_comes(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);
        $this->start(['end_after' => 600])->assertOk();

        $this->travel(11)->minutes();
        app(LiveActivities::class)->sweep();
        $this->assertSame('ended', LiveActivity::sole()->status);

        // The app is opened later: the activity is ended on the device.
        $this->sdk($phone, 'enter', ['activity_id' => 'MRC-0428', 'activity_type' => 'DeliveryAttributes', 'token' => 'late-token'])->assertOk();
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/3/device/late-token') && $request['aps']['event'] === 'end');
    }

    public function test_send_only_keys_can_drive_activities_and_start_tokens_stay_out_of_the_audience(): void
    {
        [, $sendKey] = ApiKey::generate($this->project, 'Sender', scope: 'send');
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);

        $this->withHeader('Authorization', 'Key '.$sendKey)
            ->postJson("/api/v1/apps/{$this->project->app_id}/activities/activity/DeliveryAttributes", ['activity_id' => 'A1', 'include_subscription_ids' => [$phone], 'contents' => ['en' => 'Hi']])
            ->assertOk()->assertJson(['recipients' => 1]);

        $this->assertSame(1, $this->project->subscriptions()->count());
    }

    public function test_ended_activities_follow_the_retention_of_messages(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'enter', ['activity_id' => 'old', 'token' => 'tok-old']);
        $this->sdk($phone, 'exit', ['activity_id' => 'old']);
        $this->sdk($phone, 'enter', ['activity_id' => 'open', 'token' => 'tok-open']);

        $this->travel(91)->days();
        $this->artisan('model:prune', ['--model' => [LiveActivity::class]])->assertSuccessful();

        $this->assertSame(['open'], LiveActivity::pluck('activity_id')->all());
    }

    public function test_android_shows_it_as_a_live_update(): void
    {
        $this->device('pixel-token', 'user-1', 'android', 'carillon-flutter/0.3.0');
        $this->device('old-sdk-token', 'user-1', 'android', 'carillon-flutter/0.2.0');
        $this->device('onesignal-token', 'user-1', 'android');

        $this->start([
            'android' => ['progress' => 1, 'progress_max' => 3, 'segments' => 3, 'color' => '#0F766E'],
            'data' => ['screen' => 'achats'],
        ])->assertOk()->assertJson(['recipients' => 1, 'targeted' => 3, 'skipped' => ['no_start_token' => 0, 'unsupported' => 2]]);

        $live = fn (HttpRequest $request) => json_decode($request['message']['data']['carillon_live'] ?? 'null', true);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'fcm.googleapis.com')
            && $request['message']['token'] === 'pixel-token'
            && $request['message']['android']['priority'] === 'HIGH'
            && $live($request)['e'] === 'start'
            && $live($request)['a'] === 'MRC-0428'
            && $live($request)['title'] === 'Top-up in progress'
            && $live($request)['body'] === 'We are on it'
            && $live($request)['p'] === 1 && $live($request)['pm'] === 3 && $live($request)['seg'] === 3
            && $live($request)['d'] === ['screen' => 'achats']);
        $this->assertSame('active', LiveActivity::sole()->status);

        // An update keeps what it does not change; no token to wait for.
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/MRC-0428/notifications", [
            'event' => 'update', 'android' => ['progress' => 2, 'body' => 'Sending'], 'priority' => 5,
        ])->assertOk()->assertJson(['recipients' => 1, 'pending' => 0]);
        Http::assertSent(fn (HttpRequest $request) => ($live($request)['e'] ?? null) === 'update'
            && $live($request)['p'] === 2 && $live($request)['pm'] === 3
            && $live($request)['title'] === 'Top-up in progress' && $live($request)['body'] === 'Sending'
            && $request['message']['android']['priority'] === 'NORMAL');

        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/MRC-0428/notifications", [
            'event' => 'end', 'android' => ['title' => 'Delivered', 'body' => '1 000 F', 'progress' => 3], 'dismissal_date' => 1900000000,
        ])->assertOk()->assertJson(['recipients' => 1]);
        Http::assertSent(fn (HttpRequest $request) => ($live($request)['e'] ?? null) === 'end'
            && $live($request)['title'] === 'Delivered' && $live($request)['dis'] === 1900000000);
        $this->assertSame('ended', LiveActivity::sole()->status);
    }

    public function test_one_call_reaches_iphone_and_android_of_a_person(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);
        $this->device('pixel-token', 'user-1', 'android', 'carillon-flutter/0.3.1');

        $this->start()->assertOk()->assertJson(['recipients' => 2]);
        $this->assertEqualsCanonicalizing(['started', 'active'], LiveActivity::pluck('status')->all());

        // The iPhone has no update token yet: pending; Android gets it now.
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/MRC-0428/notifications", ['event' => 'update', 'event_updates' => ['step' => 'sending']])
            ->assertOk()->assertJson(['recipients' => 1, 'pending' => 1]);
    }

    public function test_the_command_drives_an_activity(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);

        $this->artisan('carillon:live-activity', [
            'event' => 'start', 'app_id' => $this->project->app_id, 'activity_id' => 'T1',
            '--type' => 'DeliveryAttributes', '--subscription' => [$phone], '--title' => 'Test',
            '--state' => '{"step":"waiting"}', '--attributes' => '{"reference":"T1"}', '--end-after' => 1800,
        ])->assertSuccessful();
        $this->assertSame('started', LiveActivity::sole()->status);

        $this->artisan('carillon:live-activity', ['event' => 'end', 'app_id' => $this->project->app_id, 'activity_id' => 'T1', '--state' => '{"step":"done"}'])
            ->assertSuccessful();
        $this->assertSame(['event' => 'end', 'event_updates' => ['step' => 'done'], 'priority' => 10], LiveActivity::sole()->pending);
    }

    public function test_the_dashboard_starts_and_ends_a_test_activity(): void
    {
        $owner = User::factory()->admin()->create();
        $this->project->addMember($owner, ProjectRole::Owner);
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);

        $this->actingAs($owner)->post(route('projects.live-activities.start', $this->project), [
            'subscription_ids' => $phone, 'activity_type' => 'DeliveryAttributes', 'activity_id' => 'T2',
            'title' => 'Test', 'state' => '{"step":"waiting"}', 'attributes' => '{}', 'end_minutes' => 30,
        ])->assertRedirect()->assertSessionHas('status', '1 device reached.');
        $this->assertSame('started', LiveActivity::sole()->status);

        $this->actingAs($owner)->get(route('projects.live-activities.index', $this->project))->assertOk()->assertSee('T2');

        $this->actingAs($owner)->post(route('projects.live-activities.notify', $this->project), [
            'activity_id' => 'T2', 'event' => 'end', 'update_state' => '{"step":"done"}',
        ])->assertRedirect()->assertSessionHas('status', '0 devices reached · 1 waiting for the token of the app.');
    }

    public function test_an_iphone_without_notification_permission_can_show_live_activities(): void
    {
        // No APNs token before the permission: registered all the same.
        $phone = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'ios', 'enabled' => false, 'environment' => 'sandbox'])
            ->assertCreated()->json('id');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1'])->assertOk();

        $this->start(['include_aliases' => null, 'include_subscription_ids' => [$phone]])->assertOk()->assertJson(['recipients' => 1]);

        // Not part of the audience of notifications.
        $this->api()->postJson('/api/v1/notifications', ['app_id' => $this->project->app_id, 'included_segments' => ['All'], 'contents' => ['en' => 'Hi']])
            ->assertStatus(400)->assertJson(['errors' => ['All included players are not subscribed']]);

        // The permission comes later: the token takes over its old record.
        $old = $this->device('apns-token', 'user-9');
        $this->patchJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$phone}", ['token' => 'apns-token', 'enabled' => true])->assertOk();
        $this->assertNotNull(Subscription::find($old)->invalid_at);
        $this->assertSame('apns-token', Subscription::find($phone)->token);
    }

    public function test_other_platforms_still_need_a_token(): void
    {
        $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'android'])->assertStatus(400)->assertJson(['errors' => ['The token field is required.']]);
    }

    public function test_end_after_makes_the_activity_stale_by_default(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 'start-token-1']);
        $this->freezeTime();

        $this->start(['end_after' => 600])->assertOk();

        Http::assertSent(fn (HttpRequest $request) => ($request['aps']['event'] ?? null) === 'start' && $request['aps']['stale-date'] === now()->addSeconds(600)->getTimestamp());
    }

    public function test_an_end_is_sent_again_until_the_app_confirms_it(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'enter', ['activity_id' => 'A9', 'activity_type' => 'DeliveryAttributes', 'token' => 'update-a9']);
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/A9/notifications", ['event' => 'end', 'event_updates' => ['step' => 'done']])->assertOk();
        $ends = fn () => collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/update-a9') && $pair[0]['aps']['event'] === 'end')->count();
        $this->assertSame(1, $ends());

        // Not confirmed: again after 5 minutes, then once more, then never.
        $this->travel(6)->minutes();
        app(LiveActivities::class)->sweep();
        $this->assertSame(2, $ends());
        $this->travel(6)->minutes();
        app(LiveActivities::class)->sweep();
        $this->travel(6)->minutes();
        app(LiveActivities::class)->sweep();
        $this->assertSame(3, $ends());

        // Confirmed by the app (exit): no retry for another activity either.
        $this->sdk($phone, 'enter', ['activity_id' => 'A10', 'token' => 'update-a10']);
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/A10/notifications", ['event' => 'end'])->assertOk();
        $this->sdk($phone, 'exit', ['activity_id' => 'A10'])->assertOk();
        $this->travel(6)->minutes();
        app(LiveActivities::class)->sweep();
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/update-a10'))->count());
    }

    public function test_the_standard_activity_feeds_ios_and_android(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $this->sdk($phone, 'start-token', ['activity_type' => 'CarillonActivityAttributes', 'token' => 'start-token-1']);
        $this->device('pixel-token', 'user-1', 'android', 'carillon-flutter/0.3.0');

        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/activities/activity/CarillonActivityAttributes", [
            'activity_id' => 'ORDER-7',
            'include_aliases' => ['external_id' => ['user-1']],
            'live' => ['name' => 'Shop', 'icon' => 'shippingbox.fill', 'color' => '#0F766E', 'title' => 'Order on its way', 'body' => 'Arrives in 20 min', 'status' => '1/3', 'step' => 1, 'steps' => 3],
        ])->assertOk()->assertJson(['recipients' => 2]);

        $live = fn (HttpRequest $request) => json_decode($request['message']['data']['carillon_live'] ?? 'null', true);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/start-token-1')
            && $request['aps']['attributes'] === ['name' => 'Shop', 'icon' => 'shippingbox.fill', 'color' => '#0F766E', 'carillon' => ['activityId' => 'ORDER-7']]
            && $request['aps']['content-state'] === ['title' => 'Order on its way', 'body' => 'Arrives in 20 min', 'status' => '1/3', 'step' => 1, 'steps' => 3]
            && $request['aps']['alert'] === ['title' => 'Order on its way', 'body' => 'Arrives in 20 min']);
        Http::assertSent(fn (HttpRequest $request) => ($live($request)['e'] ?? null) === 'start'
            && $live($request)['title'] === 'Order on its way' && $live($request)['st'] === '1/3'
            && $live($request)['p'] === 1 && $live($request)['pm'] === 3 && $live($request)['seg'] === 3 && $live($request)['color'] === '#0F766E');

        // The iPhone sends its update token; an update changes only the step.
        $this->sdk($phone, 'enter', ['activity_id' => 'ORDER-7', 'activity_type' => 'CarillonActivityAttributes', 'token' => 'update-7']);
        $this->api()->postJson("/api/v1/apps/{$this->project->app_id}/live_activities/ORDER-7/notifications", [
            'event' => 'end', 'live' => ['status' => 'Delivered', 'step' => 3, 'state' => 'success'],
        ])->assertOk()->assertJson(['recipients' => 2]);

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/update-7')
            && $request['aps']['event'] === 'end'
            && $request['aps']['content-state'] === ['title' => 'Order on its way', 'body' => 'Arrives in 20 min', 'status' => 'Delivered', 'step' => 3, 'steps' => 3, 'state' => 'success']);
        Http::assertSent(fn (HttpRequest $request) => ($live($request)['e'] ?? null) === 'end'
            && $live($request)['p'] === 3 && $live($request)['pm'] === 3 && $live($request)['st'] === 'Delivered' && $live($request)['title'] === 'Order on its way');
    }

    public function test_the_calls_of_the_app_are_journaled_refused_ones_included(): void
    {
        $phone = $this->device('phone-token', 'user-1');
        $android = $this->device('android-token', null, 'android');
        $other = Project::factory()->create();
        $foreign = $this->postJson("/api/v1/sdk/apps/{$other->app_id}/subscriptions", ['type' => 'ios', 'token' => 'foreign', 'environment' => 'sandbox'])->assertCreated()->json('id');

        $this->sdk($phone, 'enter', ['activity_id' => 'MRC-1', 'activity_type' => 'DeliveryAttributes', 'token' => 'update-1'])->assertOk();
        $this->sdk($phone, 'enter', ['activity_id' => 'MRC-2'])->assertStatus(400);
        $this->sdk('9f1c2b3a-0000-4000-8000-000000000000', 'enter', ['activity_id' => 'MRC-3', 'token' => 'x'])->assertNotFound();
        $this->sdk($foreign, 'exit', ['activity_id' => 'MRC-4'])->assertNotFound();
        $this->sdk($android, 'start-token', ['activity_type' => 'DeliveryAttributes', 'token' => 't'])->assertNotFound();

        $outcomes = LiveActivityCall::query()->orderBy('id')->pluck('outcome', 'activity_id')->all();
        $this->assertSame(['MRC-1' => 'ok', 'MRC-2' => 'invalid', 'MRC-3' => 'unknown_device', 'MRC-4' => 'other_project'], array_slice($outcomes, 0, 4, true));
        $this->assertSame('not_ios', LiveActivityCall::query()->where('action', 'start-token')->value('outcome'));
        $this->assertSame($phone, LiveActivityCall::query()->where('activity_id', 'MRC-1')->value('subscription_id'));
        $this->assertNull(LiveActivityCall::query()->where('activity_id', 'MRC-4')->value('subscription_id'));
        $this->assertSame(0, LiveActivityCall::query()->where('project_id', $other->id)->count());

        $owner = User::factory()->admin()->create();
        $this->project->addMember($owner, ProjectRole::Owner);
        $this->actingAs($owner)->get(route('projects.live-activities.index', $this->project))->assertOk()
            ->assertSee('9f1c2b3a-0000-4000-8000-000000000000')->assertSee('MRC-4');

        $this->travel(LiveActivityCall::DAYS + 1)->days();
        $this->artisan('model:prune', ['--model' => [LiveActivityCall::class]]);
        $this->assertSame(0, LiveActivityCall::query()->count());
    }
}
