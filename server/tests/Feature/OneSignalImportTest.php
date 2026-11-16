<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Jobs\SyncOneSignal;
use App\Models\AudienceImport;
use App\Models\OneSignalSync;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneSignalImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $this->owner->id]);
        $this->project->addMember($this->owner, ProjectRole::Owner);
    }

    protected function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '\\');
        }
        rewind($handle);

        return stream_get_contents($handle);
    }

    public function test_players_export(): void
    {
        $ios = str_repeat('ab', 32);
        $content = "\xEF\xBB\xBF".$this->csv([
            ['id', 'identifier', 'session_count', 'language', 'timezone', 'game_version', 'device_os', 'device_type', 'device_model', 'tags', 'last_active', 'created_at', 'invalid_identifier', 'external_user_id', 'notification_types', 'test_type'],
            ['p1', $ios, '12', 'fr', '3600', '2.1.0', '17.4', '0', 'iPhone15,2', '{"plan":"pro","level":3}', '1731600000', '1700000000', 'f', 'user-1', '1', ''],
            ['p2', 'fcm-token-android', '3', 'en', '-18000', '2.0.0', '14', '1', 'Pixel 8', '', '2026-11-01 10:00:00', '', 'f', '', '-2', ''],
            ['p3', '{"endpoint":"https://fcm.googleapis.com/x"}', '1', 'en', '0', '', 'Chrome', '5', '', '', '', '', 'f', '', '1', ''],
            ['p4', 'not-hex', '1', 'en', '0', '', '', '0', '', '', '', '', 'f', '', '1', ''],
            ['p5', str_repeat('cd', 32), '1', 'en', '0', '', '', '0', '', '', '', '', 't', '', '1', ''],
            ['p6', str_repeat('ef', 32), '1', 'en', '0', '', '', '0', '', '', '', '', 'f', 'user-1', '1', '1'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('projects.imports.store', $this->project), ['file' => UploadedFile::fake()->createWithContent('export.csv', $content)])
            ->assertRedirect(route('projects.imports.create', $this->project));

        $import = AudienceImport::query()->first();
        $this->assertSame('done', $import->status, (string) $import->error);
        $this->assertSame(['rows' => 6, 'created' => 3, 'updated' => 0, 'skipped' => 3], $import->only(['rows', 'created', 'updated', 'skipped']));
        $this->assertSame(['web' => 1, 'invalid_token' => 2], $import->notes);
        Storage::disk('local')->assertMissing($import->path);

        $person = $this->project->subscribers()->where('external_id', 'user-1')->first();
        $this->assertSame(['plan' => 'pro', 'level' => '3'], $person->tags);
        $this->assertSame('fr', $person->language);
        $this->assertSame(2, $person->subscriptions()->count());

        $iphone = $person->subscriptions()->where('device_model', 'iPhone15,2')->first();
        $this->assertSame('production', $iphone->environment);
        $this->assertSame(12, $iphone->sessions);
        $this->assertTrue($iphone->enabled);
        $this->assertSame('sandbox', $person->subscriptions()->where('id', '!=', $iphone->id)->first()->environment);

        $pixel = $this->project->subscriptions()->where('type', 'android')->first();
        $this->assertFalse($pixel->enabled);

        // The SDK of the new app version finds the imported device.
        $this->postJson('/api/v1/sdk/apps/'.$this->project->app_id.'/subscriptions', ['type' => 'ios', 'token' => $ios])
            ->assertCreated()->assertJsonPath('id', $iphone->id)->assertJsonPath('external_id', 'user-1');

        $this->get(route('projects.imports.create', $this->project))->assertOk()->assertSee('export.csv');
    }

    public function test_subscriptions_export_gzipped_and_reimport(): void
    {
        $content = $this->csv([
            ['onesignal_id', 'id', 'type', 'token', 'enabled', 'external_id', 'language', 'timezone_id', 'country', 'app_version', 'device_model', 'device_os', 'session_count', 'last_active', 'tags'],
            ['o1', 's1', 'AndroidPush', 'fcm-1', 'true', 'u-9', 'pt', 'America/Sao_Paulo', 'br', '3.0', 'SM-S918B', '14', '5', '2026-11-10T08:00:00Z', '{"vip":"yes"}'],
            ['o2', 's2', 'Email', 'a@b.c', 'true', '', '', '', '', '', '', '', '', '', ''],
        ]);
        $file = UploadedFile::fake()->createWithContent('subscriptions.csv.gz', gzencode($content));

        $this->actingAs($this->owner)->post(route('projects.imports.store', $this->project), ['file' => $file])->assertRedirect();
        $this->actingAs($this->owner)->post(route('projects.imports.store', $this->project), ['file' => UploadedFile::fake()->createWithContent('again.csv', $content)])->assertRedirect();

        [$first, $second] = AudienceImport::query()->orderBy('id')->get();
        // The email address is imported too (email channel).
        $this->assertSame([2, 0, 0], [$first->created, $first->updated, $first->skipped]);
        $this->assertSame([0, 2, 0], [$second->created, $second->updated, $second->skipped]);

        $person = $this->project->subscribers()->where('external_id', 'u-9')->first();
        $this->assertSame(['America/Sao_Paulo', 'BR', 'pt'], [$person->timezone, $person->country, $person->language]);
        $this->assertSame(['android', 'email'], $this->project->subscriptions()->orderBy('type')->pluck('type')->all());
        $this->assertSame('a@b.c', $this->project->subscriptions()->where('type', 'email')->value('token'));
    }

    public function test_rejects_other_files(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.imports.store', $this->project), ['file' => UploadedFile::fake()->createWithContent('people.csv', "name,email\nAda,a@b.c\n")])
            ->assertRedirect();
        $import = AudienceImport::query()->first();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('not a OneSignal export', $import->error);

        $this->actingAs($this->owner)
            ->post(route('projects.imports.store', $this->project), ['file' => UploadedFile::fake()->create('photo.png', 10)])
            ->assertSessionHasErrors('file');
    }

    public function test_automatic_sync_fetches_the_export_through_the_onesignal_api(): void
    {
        $content = gzencode($this->csv([
            ['id', 'identifier', 'session_count', 'language', 'timezone', 'game_version', 'device_os', 'device_type', 'device_model', 'tags', 'last_active', 'created_at', 'invalid_identifier', 'external_user_id', 'notification_types', 'test_type'],
            ['p1', str_repeat('ab', 32), '4', 'fr', '0', '1.0.0', '18', '0', 'iPhone', '', '', '', 'f', 'user-7', '1', ''],
            ['p2', 'fcm-token-new', '1', 'fr', '0', '1.0.0', '14', '1', 'Pixel', '', '', '', 'f', '', '1', ''],
        ]));
        $ready = false;
        Http::fake([
            'api.onesignal.com/players/csv_export*' => Http::response(['csv_file_url' => 'https://onesignal-exports.example.com/export.csv.gz']),
            'onesignal-exports.example.com/*' => function () use (&$ready, $content) {
                return $ready ? Http::response($content) : Http::response('not ready', 404);
            },
        ]);

        $this->actingAs($this->owner)->put(route('projects.onesignal-sync.update', $this->project), [
            'onesignal_app_id' => 'e5b9d8a5-e094-43c5-a62a-74929ae9349b',
            'onesignal_api_key' => 'os_v2_app_secret',
            'onesignal_enabled' => '1',
        ])->assertSessionHasNoErrors();

        // Export requested with the key, file not ready yet: still waiting.
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'csv_export?app_id=e5b9d8a5')
            && $request->header('Authorization') === ['Key os_v2_app_secret']
            && in_array('external_user_id', $request['extra_fields'], true));
        $sync = OneSignalSync::query()->firstOrFail();
        $this->assertTrue($sync->isRunning());
        $this->assertSame(0, Subscription::count());
        $this->assertNotSame('os_v2_app_secret', \DB::table('onesignal_syncs')->value('api_key'));

        // The file is ready at the next check: imported like a manual export.
        $ready = true;
        (new SyncOneSignal($sync->id))->handle();

        $this->assertFalse($sync->fresh()->isRunning());
        $this->assertNotNull($sync->fresh()->last_synced_at);
        $this->assertSame(2, Subscription::count());
        $this->assertSame('onesignal-sync', AudienceImport::query()->value('source'));
        $this->assertSame('done', AudienceImport::query()->value('status'));
        $this->actingAs($this->owner)->get(route('projects.imports.create', $this->project))->assertOk()->assertSee('e5b9d8a5-e094-43c5-a62a-74929ae9349b');
    }

    public function test_automatic_sync_reports_a_refused_key(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['errors' => ['Access denied. Please include an Authorization header.']], 403)]);

        $this->actingAs($this->owner)->put(route('projects.onesignal-sync.update', $this->project), [
            'onesignal_app_id' => 'e5b9d8a5', 'onesignal_api_key' => 'wrong', 'onesignal_enabled' => '1',
        ]);

        $sync = OneSignalSync::query()->firstOrFail();
        $this->assertFalse($sync->isRunning());
        $this->assertStringContainsString('403', $sync->last_error);
        $this->assertStringContainsString('Access denied', $sync->last_error);
        Http::assertSent(fn (HttpRequest $request) => $request->header('Authorization') === ['Basic wrong']);
    }

    public function test_a_dashboard_export_without_tokens_explains_what_to_do(): void
    {
        $content = $this->csv([
            ['Channel', 'Status', 'Last session', 'Device', 'Subscription ID', 'OneSignal ID', 'App version', 'Timezone ID', 'Country', 'Language code', 'Tags'],
            ['Push', 'Subscribed', '16/11/26, 11:35:56', 'Samsung Galaxy S9 (10)', 'b85c45ac', '6a3021f2', '28', 'Africa/Abidjan', 'CI', 'French', ''],
        ]);

        $this->actingAs($this->owner)->post(route('projects.imports.store', $this->project), ['file' => UploadedFile::fake()->createWithContent('subscriptions_export.csv', $content)]);

        $this->assertSame('failed', AudienceImport::query()->value('status'));
        $this->assertStringContainsString('no push tokens', AudienceImport::query()->value('error'));
    }
}
