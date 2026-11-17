<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhook;
use App\Support\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Fixes of the security audit of November 2026. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->project->addMember($this->owner, ProjectRole::Owner);
    }

    protected function sdk(string $path = ''): string
    {
        return "/api/v1/sdk/apps/{$this->project->app_id}/subscriptions".$path;
    }

    public function test_password_reset_links_use_the_url_of_the_instance_not_the_host_header(): void
    {
        Notification::fake();
        app(Settings::class)->set(['app.url' => 'https://carillon.example.com']);
        $this->post('http://evil.test/forgot-password', ['email' => $this->owner->email], ['X-Forwarded-Host' => 'evil.test']);

        Notification::assertSentTo($this->owner, ResetPassword::class, function (ResetPassword $notification) {
            $url = $notification->toMail($this->owner)->actionUrl;
            $this->assertStringStartsWith('https://carillon.example.com/reset-password/', $url);

            return true;
        });
    }

    public function test_forwarded_headers_are_only_believed_from_private_proxies(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('X-Forwarded-For', '1.2.3.4')
            ->get('/login');
        $this->assertSame('203.0.113.7', request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])->withHeader('X-Forwarded-For', '41.202.10.20')
            ->get('/login');
        $this->assertSame('41.202.10.20', request()->ip());
    }

    public function test_an_admin_cannot_demote_an_owner_by_adding_them_again(): void
    {
        $admin = User::factory()->create();
        $this->project->addMember($admin, ProjectRole::Admin);

        $this->actingAs($admin)->post(route('projects.members.store', $this->project), ['email' => $this->owner->email, 'role' => 'viewer'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($this->owner->roleIn($this->project->fresh())->isOwner());
    }

    public function test_web_push_endpoints_must_be_browser_push_services(): void
    {
        $token = fn (string $endpoint) => json_encode(['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey', 'auth' => 'auth']]);

        foreach (['http://127.0.0.1:6379/', 'https://169.254.169.254/latest', 'https://evil.test/fcm.googleapis.com', 'https://fcm.googleapis.com.evil.test/x', 'https://fcm.googleapis.com:8443/x'] as $endpoint) {
            $this->postJson($this->sdk(), ['type' => 'web', 'token' => $token($endpoint)])->assertStatus(422);
        }
        $this->postJson($this->sdk(), ['type' => 'web', 'token' => $token('https://fcm.googleapis.com/fcm/send/abc')])->assertCreated();
        $this->postJson($this->sdk(), ['type' => 'web', 'token' => $token('https://updates.push.services.mozilla.com/wpush/v2/abc')])->assertCreated();
    }

    public function test_known_email_and_phone_contacts_cannot_be_read_or_changed_with_the_app_id(): void
    {
        $person = Subscriber::create(['project_id' => $this->project->id, 'external_id' => 'user-1', 'tags' => ['plan' => 'gold']]);
        $email = Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $person->id, 'type' => 'email', 'token' => 'ada@example.com', 'enabled' => false]);

        // Re-subscribing (or reading) someone by their address is refused.
        $this->postJson($this->sdk(), ['type' => 'email', 'token' => 'ada@example.com', 'enabled' => true])->assertStatus(409)->assertJsonMissing(['plan' => 'gold']);
        $this->assertFalse($email->fresh()->enabled);

        // Moving it to the device of someone else neither.
        $device = $this->postJson($this->sdk(), ['type' => 'android', 'token' => 'fcm-1'])->json('id');
        $this->postJson($this->sdk("/$device/email"), ['email' => 'ada@example.com'])->assertStatus(409);
        $this->assertSame($person->id, $email->fresh()->subscriber_id);

        // The same user on a new device may.
        $this->postJson($this->sdk("/$device/login"), ['external_id' => 'user-1'])->assertOk();
        $this->postJson($this->sdk("/$device/email"), ['email' => 'ada@example.com'])->assertOk();
    }

    public function test_numbers_added_by_apps_do_not_start_new_device_journeys(): void
    {
        $this->postJson($this->sdk(), ['type' => 'sms', 'token' => '+2250700000001'])->assertCreated();
        $this->assertSame(0, \DB::table('journey_entries')->count());
    }

    public function test_identity_verification_signs_external_ids(): void
    {
        $this->actingAs($this->owner)->put(route('projects.identity.update', $this->project), ['rotate' => 1]);
        $this->actingAs($this->owner)->put(route('projects.identity.update', $this->project), ['identity_verification' => 1]);
        $project = $this->project->fresh();
        $this->assertTrue($project->verifiesIdentity());
        $secret = Crypt::decryptString($project->settings['identity_secret']);

        $device = $this->postJson($this->sdk(), ['type' => 'android', 'token' => 'fcm-2'])->json('id');
        $this->postJson($this->sdk("/$device/login"), ['external_id' => 'user-9'])->assertUnauthorized();
        $this->postJson($this->sdk("/$device/login"), ['external_id' => 'user-9', 'identity_hash' => hash_hmac('sha256', 'user-10', $secret)])->assertUnauthorized();
        $this->postJson($this->sdk("/$device/login"), ['external_id' => 'user-9', 'identity_hash' => hash_hmac('sha256', 'user-9', $secret)])->assertOk()->assertJson(['external_id' => 'user-9']);
        $this->postJson($this->sdk(), ['type' => 'android', 'token' => 'fcm-3', 'external_id' => 'user-9'])->assertUnauthorized();

        $this->actingAs($this->owner)->get(route('projects.settings', [$this->project, 'api-keys']))->assertOk()->assertSee('HMAC-SHA256');
    }

    public function test_webhooks_and_integrations_cannot_reach_private_networks_unless_allowed(): void
    {
        foreach (['http://127.0.0.1:8080/hook', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/x', 'http://10.0.0.5/x'] as $url) {
            $this->actingAs($this->owner)->post(route('projects.webhooks.store', $this->project), ['url' => $url, 'events' => ['message.sent']])->assertSessionHasErrors('url');
        }
        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'postgres', 'name' => 'DB', 'host' => '127.0.0.1', 'port' => 5432, 'database' => 'app', 'username' => 'u', 'secret' => 'p',
            'query' => 'SELECT id AS external_id FROM users', 'id_column' => 'external_id', 'schedule' => 'daily',
        ])->assertSessionHasErrors('host');
        $this->assertSame(0, Webhook::count());

        app(Settings::class)->set(['security.private_network' => true]);
        $this->actingAs($this->owner)->post(route('projects.webhooks.store', $this->project), ['url' => 'http://10.0.0.5/hook', 'events' => ['message.sent']])->assertSessionHasNoErrors();
    }

    public function test_in_app_buttons_cannot_run_javascript(): void
    {
        $this->actingAs($this->owner)->post(route('projects.in-app.store', $this->project), [
            'name' => 'Promo', 'type' => 'modal', 'title' => 'Hi', 'trigger' => 'session',
            'buttons' => [['text' => 'Go', 'action' => 'url', 'url' => ' JavaScript:alert(1)']],
        ])->assertSessionHasErrors('buttons.0.url');
    }

    public function test_an_open_session_cannot_replace_two_factor_or_the_email_without_the_password(): void
    {
        $user = $this->owner;
        $user->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['a']])->save();
        $secret = $user->two_factor_secret;

        $this->actingAs($user)->post('/profile/two-factor');
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
        $this->assertTrue($user->fresh()->hasTwoFactor());

        $this->actingAs($user)->post('/profile/two-factor/recovery-codes')->assertSessionHasErrors('codes_password');
        $this->actingAs($user)->post('/profile/two-factor/recovery-codes', ['codes_password' => 'password'])->assertSessionHas('recovery_codes');

        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => 'thief@evil.test'])->assertSessionHasErrors('email_password');
        $this->assertNotSame('thief@evil.test', $user->fresh()->email);
        $this->actingAs($user)->put('/profile', ['name' => 'New name', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->actingAs($user)->put('/profile', ['name' => 'New name', 'email' => 'new@example.com', 'email_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_viewers_do_not_see_the_secret_address_of_email_bounces(): void
    {
        $viewer = User::factory()->create();
        $this->project->addMember($viewer, ProjectRole::Viewer);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'email', 'details' => ['driver' => 'postmark', 'from_address' => 'a@b.c', 'webhook_secret' => 'bounce-secret-123'], 'secret' => 'x']);

        $this->actingAs($viewer)->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertDontSee('bounce-secret-123');
        $this->actingAs($this->owner)->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertSee('bounce-secret-123');
    }

    public function test_https_headers_and_redirects_follow_the_instance_url(): void
    {
        app(Settings::class)->set(['app.url' => 'https://carillon.example.com']);

        $this->get('http://carillon.example.com/projects')->assertRedirect('https://carillon.example.com/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeader('Content-Security-Policy');
        // Another host (an IP address, a misconfigured URL): no HSTS.
        $this->get('http://10.0.0.9:8080/projects')->assertRedirect()->assertHeaderMissing('Strict-Transport-Security');
    }
}
