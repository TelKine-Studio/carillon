<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSdkTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_cors_and_web_registration(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Site', 'created_by' => $user->id]);
        $project->addMember($user, ProjectRole::Owner);
        $base = '/api/v1/sdk/apps/'.$project->app_id;

        $this->getJson("$base/config")->assertOk()->assertJsonPath('web', null)->assertJsonPath('name', 'Site');

        PushCredential::create(['project_id' => $project->id, 'provider' => 'webpush', 'details' => ['public_key' => 'BPublicKey', 'subject' => 'mailto:a@b.c'], 'secret' => 'private']);
        $this->getJson("$base/config")->assertOk()->assertJsonPath('web.vapid_public_key', 'BPublicKey');
        $this->getJson("$base/icon")->assertNotFound();
        $this->getJson('/api/v1/sdk/apps/00000000-0000-0000-0000-000000000000/config')->assertNotFound();

        // Called from any website.
        $this->call('OPTIONS', "$base/subscriptions", server: [
            'HTTP_ORIGIN' => 'https://shop.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');

        $token = json_encode(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'expirationTime' => null, 'keys' => ['p256dh' => 'BKey', 'auth' => 'secret']]);
        $this->postJson("$base/subscriptions", ['type' => 'web', 'token' => $token, 'language' => 'fr', 'country' => 'FR', 'device_model' => 'Chrome 130', 'device_os' => 'macOS', 'sdk' => 'carillon-web/0.1.0'])
            ->assertCreated();
        $this->postJson("$base/subscriptions", ['type' => 'web', 'token' => '{"endpoint":"x"}'])->assertStatus(422);

        $this->assertFileExists(public_path('sdk/carillon.js'));
        $this->assertFileExists(public_path('sdk/carillon-sw.js'));
    }
}
