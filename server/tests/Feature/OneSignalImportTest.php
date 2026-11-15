<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\AudienceImport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertSame([1, 0, 1], [$first->created, $first->updated, $first->skipped]);
        $this->assertSame([0, 1, 1], [$second->created, $second->updated, $second->skipped]);

        $person = $this->project->subscribers()->where('external_id', 'u-9')->first();
        $this->assertSame(['America/Sao_Paulo', 'BR', 'pt'], [$person->timezone, $person->country, $person->language]);
        $this->assertSame(1, $this->project->subscriptions()->count());
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
}
