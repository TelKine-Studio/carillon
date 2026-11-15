<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    protected function project(User $owner, array $attributes = []): Project
    {
        $project = Project::factory()->create($attributes);
        $project->addMember($owner, ProjectRole::Owner);

        return $project;
    }

    public function test_a_project_is_created_with_its_creator_as_owner(): void
    {
        $this->actingAs($this->admin)->post('/projects', ['name' => 'Ma Boutique'])
            ->assertRedirect();

        $project = Project::sole();
        $this->assertSame('Ma Boutique', $project->name);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $project->app_id);
        $this->assertSame(ProjectRole::Owner, $this->admin->roleIn($project));

        $this->get("/projects/{$project->app_id}")->assertOk()->assertSee($project->app_id);
        foreach (['general', 'platforms', 'api-keys', 'members'] as $section) {
            $this->get("/projects/{$project->app_id}/settings/{$section}")->assertOk();
        }
    }

    public function test_only_admins_create_projects_unless_allowed(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/projects/create')->assertForbidden();

        app(Settings::class)->set(['projects.users_can_create' => true]);
        $this->actingAs($member)->post('/projects', ['name' => 'Side app'])->assertRedirect();
        $this->assertSame(ProjectRole::Owner, $member->roleIn(Project::sole()));
    }

    public function test_members_only_see_their_projects_and_act_according_to_their_role(): void
    {
        $viewer = User::factory()->create();
        $mine = $this->project($this->admin, ['name' => 'Shared']);
        $other = $this->project($this->admin, ['name' => 'Private']);
        $mine->addMember($viewer, ProjectRole::Viewer);

        $this->actingAs($viewer)->get('/projects')->assertSee('Shared')->assertDontSee('Private');
        $this->get("/projects/{$other->app_id}")->assertForbidden();

        // A viewer reads the settings but cannot change anything.
        $this->get("/projects/{$mine->app_id}/settings/platforms")->assertOk();
        $this->put("/projects/{$mine->app_id}", ['name' => 'Renamed'])->assertForbidden();
        $this->post("/projects/{$mine->app_id}/api-keys", ['name' => 'Backend'])->assertForbidden();
        $this->delete("/projects/{$mine->app_id}", ['confirm_name' => 'Shared'])->assertForbidden();
    }

    public function test_api_keys_are_shown_once_and_stored_hashed(): void
    {
        $project = $this->project($this->admin);

        $response = $this->actingAs($this->admin)->post("/projects/{$project->app_id}/api-keys", ['name' => 'Backend']);
        $plain = $response->getSession()->get('plain_api_key');

        $this->assertStringStartsWith('crl_', $plain);
        $key = ApiKey::sole();
        $this->assertNotSame($plain, $key->key_hash);
        $this->assertTrue(ApiKey::findByPlain($plain)->is($key));

        $this->delete("/projects/{$project->app_id}/api-keys/{$key->id}")->assertRedirect();
        $this->assertNull(ApiKey::findByPlain($plain));
    }

    public function test_apns_fcm_and_web_push_keys_are_validated_and_encrypted(): void
    {
        $project = $this->project($this->admin);
        $url = "/projects/{$project->app_id}/push";

        // Apple: a .p8 EC key.
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ec, $p8);
        $this->actingAs($this->admin)->put("{$url}/apns", [
            'key_id' => 'ABC123DEFG', 'team_id' => '4ZA5MXYWG7', 'bundle_id' => 'com.example.shop',
            'private_key' => UploadedFile::fake()->createWithContent('AuthKey_ABC123DEFG.p8', $p8),
        ])->assertSessionHasNoErrors();

        $this->put("{$url}/apns", [
            'key_id' => 'ABC123DEFG', 'team_id' => '4ZA5MXYWG7', 'bundle_id' => 'com.example.shop',
            'private_key' => UploadedFile::fake()->createWithContent('key.p8', 'not a key'),
        ])->assertSessionHasErrors('private_key');

        $apns = $project->credentials()->where('provider', 'apns')->sole();
        $this->assertTrue($apns->isUsable());
        $this->assertStringNotContainsString('PRIVATE KEY', $apns->getRawOriginal('secret'));

        // Firebase: a service account JSON.
        $rsa = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $pem);
        $account = json_encode(['type' => 'service_account', 'project_id' => 'shop-123', 'client_email' => 'push@shop-123.iam.gserviceaccount.com', 'private_key' => $pem]);
        $this->put("{$url}/fcm", ['service_account' => UploadedFile::fake()->createWithContent('shop.json', $account)])->assertSessionHasNoErrors();
        $this->put("{$url}/fcm", ['service_account' => UploadedFile::fake()->createWithContent('other.json', '{"type":"user"}')])->assertSessionHasErrors('service_account');

        // Web: VAPID keys generated on first save.
        $this->put("{$url}/webpush", ['subject' => 'mailto:dev@example.com'])->assertSessionHasNoErrors();

        $project->unsetRelation('credentials');
        $this->assertEqualsCanonicalizing(['apns', 'fcm', 'webpush'], $project->configuredProviders());
        $this->get("/projects/{$project->app_id}/settings/platforms")->assertSee('shop-123')->assertSee($project->credential('webpush')->detail('public_key'));

        $this->delete("{$url}/fcm")->assertRedirect();
        $this->assertNull($project->credentials()->where('provider', 'fcm')->first());
    }

    public function test_members_are_added_or_invited_with_a_role(): void
    {
        $project = $this->project($this->admin);
        $existing = User::factory()->create(['email' => 'awa@example.com']);

        $this->actingAs($this->admin)->post("/projects/{$project->app_id}/members", ['email' => 'awa@example.com', 'role' => 'editor'])->assertSessionHasNoErrors();
        $this->assertSame(ProjectRole::Editor, $existing->roleIn($project));

        // A new address: invitation, then access on acceptance.
        $response = $this->post("/projects/{$project->app_id}/members", ['email' => 'kofi@example.com', 'name' => 'Kofi', 'role' => 'viewer']);
        $link = $response->getSession()->get('invitation_url');
        $this->assertSame($project->id, Invitation::sole()->project_id);

        auth()->logout();
        $this->post(parse_url($link, PHP_URL_PATH), ['name' => 'Kofi', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect('/projects');
        $this->assertSame(ProjectRole::Viewer, User::where('email', 'kofi@example.com')->sole()->roleIn($project));
    }

    public function test_a_project_keeps_at_least_one_owner(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $admin = User::factory()->create();
        $project->addMember($admin, ProjectRole::Admin);

        // An admin cannot touch owners, and the last owner cannot step down.
        $this->actingAs($admin)->put("/projects/{$project->app_id}/members/{$owner->id}", ['role' => 'editor'])->assertSessionHasErrors('role');
        $this->actingAs($owner)->put("/projects/{$project->app_id}/members/{$owner->id}", ['role' => 'editor'])->assertSessionHasErrors('role');
        $this->actingAs($owner)->delete("/projects/{$project->app_id}/members/{$owner->id}")->assertSessionHasErrors('role');

        $this->actingAs($owner)->put("/projects/{$project->app_id}/members/{$admin->id}", ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete("/projects/{$project->app_id}/members/{$owner->id}")->assertRedirect('/projects');
        $this->assertNull($owner->roleIn($project));
    }

    public function test_deleting_a_project_requires_its_name(): void
    {
        $project = $this->project($this->admin, ['name' => 'Old app']);

        $this->actingAs($this->admin)->delete("/projects/{$project->app_id}", ['confirm_name' => 'nope'])->assertSessionHasErrors('confirm_name');
        $this->delete("/projects/{$project->app_id}", ['confirm_name' => 'Old app'])->assertRedirect('/projects');
        $this->assertSame(0, Project::count());
    }
}
