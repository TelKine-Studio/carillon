<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\OneSignalSync;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $this->project->addMember($owner, ProjectRole::Owner);
    }

    public function test_a_sending_key_cannot_read_or_delete_the_audience(): void
    {
        [, $send] = ApiKey::generate($this->project, 'backend', scope: 'send');
        [, $full] = ApiKey::generate($this->project, 'admin');
        $app = $this->project->app_id;
        Subscriber::create(['project_id' => $this->project->id, 'external_id' => 'u1']);
        $headers = fn ($key) => ['Authorization' => 'Key '.$key];

        $this->getJson("/api/v1/apps/$app/users/by/external_id/u1", $headers($send))->assertForbidden();
        $this->deleteJson("/api/v1/apps/$app/users/by/external_id/u1", [], $headers($send))->assertForbidden();
        $this->postJson('/api/v1/templates', ['name' => 'x', 'contents' => ['en' => 'x']], $headers($send))->assertForbidden();
        $this->getJson('/api/v1/notifications?app_id='.$app, $headers($send))->assertOk();
        $this->getJson("/api/v1/apps/$app/stats", $headers($send))->assertOk();
        $this->getJson("/api/v1/apps/$app/users/by/external_id/u1", $headers($full))->assertOk();
    }

    public function test_stats_tell_how_many_devices_still_run_onesignal(): void
    {
        [, $key] = ApiKey::generate($this->project, 'backend', scope: 'send');
        $subscriber = Subscriber::create(['project_id' => $this->project->id]);
        foreach (['onesignal-import', 'onesignal-import', 'onesignal-import', 'carillon-flutter/0.2.0'] as $i => $sdk) {
            Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $subscriber->id, 'type' => 'android', 'token' => "t$i", 'enabled' => true, 'sdk' => $sdk]);
        }

        $this->getJson("/api/v1/apps/{$this->project->app_id}/stats", ['Authorization' => 'Key '.$key])->assertOk()
            ->assertJsonPath('devices', 4)->assertJsonPath('legacy', 3)->assertJsonPath('carillon_sdk', 1)->assertJsonPath('legacy_share', 75)
            ->assertJsonPath('duplicates', 0);

        // A person with two reachable Android devices (old install left behind).
        $subscriber->forceFill(['external_id' => 'u1'])->save();
        $this->getJson("/api/v1/apps/{$this->project->app_id}/stats", ['Authorization' => 'Key '.$key])->assertJsonPath('duplicates', 1);
    }

    public function test_a_failing_sync_is_told_by_webhook_once(): void
    {
        Http::fake(['hooks.test/*' => Http::response('ok')]);
        Webhook::create(['project_id' => $this->project->id, 'url' => 'https://hooks.test/carillon', 'secret' => 'whsec_x', 'events' => ['onesignal_sync.failed'], 'enabled' => true]);
        $sync = OneSignalSync::create(['project_id' => $this->project->id, 'app_id' => 'os-app', 'api_key' => 'k', 'enabled' => true]);

        $sync->forceFill(['last_error' => 'Invalid REST API key'])->save();
        $sync->forceFill(['requested_at' => now()])->save(); // same error: no second call
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['type'] === 'onesignal_sync.failed' && $request['data']['error'] === 'Invalid REST API key');
    }

    public function test_the_sdk_gets_the_firebase_options_of_its_android_app(): void
    {
        $owner = $this->project->members()->first();
        $rsa = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $pem);
        $account = json_encode(['type' => 'service_account', 'project_id' => 'shop-123', 'client_email' => 'a@shop-123.iam.gserviceaccount.com', 'private_key' => $pem]);
        $services = fn (string $project) => json_encode(['project_info' => ['project_number' => '998877', 'project_id' => $project], 'client' => [
            ['client_info' => ['mobilesdk_app_id' => '1:998877:android:abc', 'android_client_info' => ['package_name' => 'ci.marecharge.app']], 'api_key' => [['current_key' => 'AIzaPublic']]],
        ]]);
        $file = fn (string $name, string $content) => UploadedFile::fake()->createWithContent($name, $content);

        $this->actingAs($owner)->put(route('projects.push.update', [$this->project, 'fcm']), ['service_account' => $file('sa.json', $account)])->assertSessionHasNoErrors();
        // Later, the google-services.json alone (the service account is kept).
        $this->put(route('projects.push.update', [$this->project, 'fcm']), ['google_services' => $file('google-services.json', $services('other-project'))])->assertSessionHasErrors('google_services');
        $this->put(route('projects.push.update', [$this->project, 'fcm']), ['google_services' => $file('google-services.json', $services('shop-123'))])->assertSessionHasNoErrors();
        $this->assertSame('a@shop-123.iam.gserviceaccount.com', $this->project->credential('fcm')->detail('client_email'));

        $base = "/api/v1/sdk/apps/{$this->project->app_id}/config";
        $this->getJson("$base?android_package=ci.marecharge.app")->assertOk()
            ->assertJsonPath('firebase', ['project_id' => 'shop-123', 'sender_id' => '998877', 'app_id' => '1:998877:android:abc', 'api_key' => 'AIzaPublic']);
        $this->getJson("$base?android_package=other.app")->assertOk()->assertJsonPath('firebase', null);
        $this->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertSee('ci.marecharge.app');
    }
}
