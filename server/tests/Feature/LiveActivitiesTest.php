<?php

namespace Tests\Feature;

use App\LiveActivities\LiveActivities;
use App\Models\ApiKey;
use App\Models\LiveActivity;
use App\Models\LiveActivityStartToken;
use App\Models\Project;
use App\Models\PushCredential;
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

        Http::fake([
            'api.sandbox.push.apple.com/3/device/dead-start' => Http::response(['reason' => 'BadDeviceToken'], 400),
            'api.sandbox.push.apple.com/*' => Http::response(null, 200),
            'api.push.apple.com/*' => Http::response(null, 200),
        ]);
    }

    protected function device(string $token, ?string $externalId = null): string
    {
        $id = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'ios', 'token' => $token, 'environment' => 'sandbox'])
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

        $this->start()->assertOk()->assertJson(['recipients' => 0]);
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
        $this->assertNull($activity->update_token);

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
}
