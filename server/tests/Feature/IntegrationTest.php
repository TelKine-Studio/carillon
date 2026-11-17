<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Integrations\Catalog;
use App\Integrations\S3Export;
use App\Integrations\SqlSync;
use App\Integrations\Streams;
use App\Jobs\RunIntegration;
use App\Models\CustomEvent;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->admin()->create();
        $this->project = Project::factory()->create(['name' => 'Shop']);
        $this->project->addMember($this->owner, ProjectRole::Owner);
        Webhooks::flush();
    }

    protected function tearDown(): void
    {
        SqlSync::$connector = null;
        Webhooks::flush();
        parent::tearDown();
    }

    public function test_the_page_lists_the_catalog_and_every_form_renders(): void
    {
        $this->actingAs($this->owner)->get(route('projects.integrations.index', $this->project))
            ->assertOk()->assertSee('PostHog')->assertSee('Segment')->assertSee('PostgreSQL')->assertSee('Slack')->assertSee('n8n, Make, Zapier')
            ->assertSee(route('projects.integrations.create', [$this->project, 'posthog']))
            ->assertSee('<svg class="size-full"', false);
        $this->assertNull(Catalog::logo('../../.env'));
        $this->assertStringContainsString('<svg', Catalog::logo('slack'));

        foreach (['posthog', 'mixpanel', 'amplitude', 'slack', 'discord', 's3', 'segment', 'rudderstack', 'postgres', 'mysql'] as $card) {
            $this->actingAs($this->owner)->get(route('projects.integrations.create', [$this->project, $card]))->assertOk();
        }
        $this->actingAs($this->owner)->get(route('projects.integrations.create', [$this->project, 'webhooks']))->assertNotFound();

        $viewer = User::factory()->create();
        $this->project->addMember($viewer, ProjectRole::Viewer);
        $this->actingAs($viewer)->get(route('projects.integrations.index', $this->project))->assertOk()->assertDontSee(route('projects.imports.create', $this->project));
        $this->actingAs($viewer)->get(route('projects.integrations.create', [$this->project, 'posthog']))->assertForbidden();
    }

    public function test_events_are_streamed_to_posthog_with_the_external_id(): void
    {
        Http::fake(['us.i.posthog.com/*' => Http::response(['status' => 'Ok'])]);

        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'posthog', 'name' => 'Product analytics', 'region' => 'us', 'prefix' => 'Carillon', 'secret' => 'phc_test',
            'events' => ['notification.clicked'],
        ])->assertRedirect();
        $integration = Integration::sole();
        $this->assertSame('phc_test', $integration->secret);
        $this->assertSame(['notification.clicked'], $integration->events);

        Webhooks::dispatch($this->project, 'notification.received', ['external_id' => 'user-1']); // not followed
        Webhooks::dispatch($this->project, 'notification.clicked', ['external_id' => 'user-1', 'message_id' => 'm-1', 'platform' => 'ios']);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://us.i.posthog.com/i/v0/e/'
            && $request['api_key'] === 'phc_test'
            && $request['event'] === 'Carillon Notification clicked'
            && $request['distinct_id'] === 'user-1'
            && $request['properties']['message_id'] === 'm-1'
            && $request['properties']['carillon_project'] === 'Shop');
        $this->assertNotNull($integration->fresh()->last_success_at);

        // The test button sends at once and reports the result.
        $this->actingAs($this->owner)->post(route('projects.integrations.test', [$this->project, $integration]))->assertSessionHas('status');
        Http::assertSentCount(2);
    }

    public function test_requests_of_mixpanel_amplitude_slack_and_discord(): void
    {
        $payload = Webhooks::payload('message.sent', ['message_id' => 'm-1', 'name' => 'Black Friday', 'recipients' => 1200, 'successful' => 1180, 'failed' => 20]);
        $make = fn (string $kind, array $config = [], string $secret = 'key') => new Integration(['kind' => $kind, 'config' => $config, 'secret' => $secret, 'name' => $kind]);

        $mixpanel = Streams::request($make('mixpanel', ['region' => 'eu', 'prefix' => '']), $payload, 'Shop');
        $this->assertSame('https://api-eu.mixpanel.com/track', $mixpanel['url']);
        $this->assertTrue($mixpanel['form']);
        $event = json_decode($mixpanel['body']['data'], true);
        $this->assertSame('Message sent', $event['event']);
        $this->assertSame('key', $event['properties']['token']);
        $this->assertSame('carillon', $event['properties']['distinct_id']);

        $amplitude = Streams::request($make('amplitude', ['region' => 'eu']), $payload, 'Shop');
        $this->assertSame('https://api.eu.amplitude.com/2/httpapi', $amplitude['url']);
        $this->assertSame('Carillon Message sent', $amplitude['body']['events'][0]['event_type']);
        $this->assertSame($payload['id'], $amplitude['body']['events'][0]['insert_id']);

        $slack = Streams::request($make('slack', [], 'https://hooks.slack.com/services/T/B/x'), $payload, 'Shop');
        $this->assertSame('https://hooks.slack.com/services/T/B/x', $slack['url']);
        $this->assertStringContainsString('Black Friday', $slack['body']['text']);
        $this->assertStringContainsString('1 180', $slack['body']['text']);

        $discord = Streams::request($make('discord', [], 'https://discord.com/api/webhooks/1/x'), $payload, 'Shop');
        $this->assertStringContainsString('[Shop]', $discord['body']['content']);
        $this->assertSame([], $discord['body']['allowed_mentions']['parse']);

        // Slack and Discord URLs are checked.
        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'slack', 'name' => 'Alerts', 'secret' => 'https://evil.example.com/hook', 'events' => ['message.sent'],
        ])->assertSessionHasErrors('secret');
    }

    public function test_a_segment_compatible_source_identifies_users_and_tracks_events(): void
    {
        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'segment', 'name' => 'Segment', 'create_users' => '1', 'skip_traits' => 'email',
        ])->assertRedirect();
        $integration = Integration::sole();
        $this->assertStringStartsWith('cws_', $integration->secret);
        $url = "/api/v1/sources/segment/{$this->project->app_id}";

        $this->postJson($url, ['type' => 'identify', 'userId' => 'u-1'])->assertUnauthorized();
        $this->withBasicAuth('wrong', '')->postJson($url, ['type' => 'identify', 'userId' => 'u-1'])->assertUnauthorized();

        $this->withBasicAuth($integration->secret, '')->postJson($url, [
            'type' => 'identify', 'userId' => 'u-1',
            'traits' => ['plan' => 'pro', 'email' => 'secret@example.com', 'vip' => true, 'address' => ['city' => 'Abidjan', 'country' => 'CI']],
            'context' => ['locale' => 'fr-CI', 'timezone' => 'Africa/Abidjan'],
        ])->assertOk()->assertJson(['identify' => 1]);

        $user = Subscriber::query()->where('external_id', 'u-1')->sole();
        $this->assertSame(['plan' => 'pro', 'vip' => 'true', 'address.city' => 'Abidjan', 'address.country' => 'CI'], $user->tags);
        $this->assertSame('fr', $user->language);
        $this->assertSame('Africa/Abidjan', $user->timezone);
        $this->assertSame('CI', $user->country);

        // Batch through the HTTP API path of the Segment libraries, with a Bearer key.
        $this->withToken($integration->secret)->postJson($url.'/v1/batch', ['batch' => [
            ['type' => 'track', 'userId' => 'u-1', 'event' => 'Order Completed', 'properties' => ['revenue' => 25], 'timestamp' => '2026-11-16T10:00:00Z'],
            ['type' => 'track', 'userId' => 'unknown', 'event' => 'Order Completed'],
            ['type' => 'track', 'anonymousId' => 'a-1', 'event' => 'Viewed'],
            ['type' => 'screen', 'userId' => 'u-1', 'name' => 'Home'],
        ]])->assertOk()->assertJson(['track' => 2, 'skipped' => 2]);

        $events = CustomEvent::query()->where('subscriber_id', $user->id)->orderBy('name')->get();
        $this->assertSame(['Order Completed', 'screen: Home'], $events->pluck('name')->all());
        $this->assertEquals(25, $events[0]->value);
        $this->assertSame('2026-11-16', $events[0]->occurred_at->toDateString());
        $this->assertSame(['identify' => 0, 'track' => 2, 'skipped' => 2], $integration->fresh()->last_result);
    }

    public function test_a_sql_query_updates_the_tags_of_known_users(): void
    {
        $known = Subscriber::create(['project_id' => $this->project->id, 'external_id' => 'u-1', 'tags' => ['plan' => 'free', 'old' => 'x']]);
        // The database of the app, here an in-memory SQLite.
        SqlSync::$connector = function (Integration $integration, string $name) {
            config(["database.connections.$name" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::connection($name)->unprepared("CREATE TABLE users (id TEXT, plan TEXT, old TEXT, lang TEXT); INSERT INTO users VALUES ('u-1', 'pro', NULL, 'fr'), ('u-2', 'free', NULL, 'en')");
        };

        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'postgres', 'name' => 'App database', 'host' => 'db.example.com', 'port' => 5432, 'database' => 'app', 'username' => 'reader',
            'secret' => 'pw', 'sslmode' => 'require', 'query' => 'delete from users', 'id_column' => 'external_id', 'schedule' => 'hourly',
        ])->assertSessionHasErrors('query');

        $this->actingAs($this->owner)->post(route('projects.integrations.store', $this->project), [
            'card' => 'postgres', 'name' => 'App database', 'host' => 'db.example.com', 'port' => 5432, 'database' => 'app', 'username' => 'reader',
            'secret' => 'pw', 'sslmode' => 'require', 'query' => 'SELECT id AS external_id, plan, old, lang AS language FROM users;', 'id_column' => 'external_id', 'schedule' => 'hourly',
        ])->assertRedirect();
        $integration = Integration::sole();
        $this->assertSame('reader', $integration->setting('username'));
        $this->assertFalse($integration->setting('create_users'));

        $this->actingAs($this->owner)->post(route('projects.integrations.test', [$this->project, $integration]))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'external_id, plan, old, language'));

        RunIntegration::dispatchSync($integration->id);

        $this->assertSame(['plan' => 'pro'], $known->fresh()->tags);
        $this->assertSame('fr', $known->fresh()->language);
        $this->assertSame(0, Subscriber::query()->where('external_id', 'u-2')->count());
        $this->assertSame(['rows' => 2, 'updated' => 1, 'created' => 0, 'unknown' => 1], $integration->fresh()->last_result);

        // Missing users created when asked.
        $integration->update(['config' => ['create_users' => true] + $integration->config]);
        RunIntegration::dispatchSync($integration->id);
        $this->assertSame(['plan' => 'free'], Subscriber::query()->where('external_id', 'u-2')->sole()->tags);
    }

    public function test_a_failed_sync_is_recorded_and_announced_in_slack(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        SqlSync::$connector = function (Integration $integration, string $name) {
            config(["database.connections.$name" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        };
        $this->project->integrations()->create(['kind' => 'slack', 'name' => 'Alerts', 'secret' => 'https://hooks.slack.com/services/T/B/x', 'events' => ['integration.failed']]);
        $sync = $this->project->integrations()->create(['kind' => 'mysql', 'name' => 'Shop DB', 'secret' => 'pw', 'config' => ['query' => 'SELECT id AS external_id FROM missing_table']]);

        RunIntegration::dispatchSync($sync->id);

        $sync->refresh();
        $this->assertStringContainsString('missing_table', $sync->last_error);
        $this->assertSame(1, $sync->failures);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'Shop DB') && str_contains($request['text'], 'missing_table'));
    }

    public function test_the_send_history_of_a_day_is_exported_to_s3(): void
    {
        $this->travelTo('2026-11-17 04:15:00');
        Http::fake(['*' => Http::response('')]);
        $person = Subscriber::create(['project_id' => $this->project->id, 'external_id' => 'u-1']);
        $device = Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $person->id, 'type' => 'android', 'token' => 't-1']);
        $message = Message::create(['project_id' => $this->project->id, 'name' => 'Promo, "today"', 'contents' => ['en' => 'Hi'], 'targeting' => ['segments' => ['All']]]);
        DB::table('deliveries')->insert([
            ['message_id' => $message->id, 'subscription_id' => $device->id, 'status' => 'sent', 'sent_at' => '2026-11-16 09:00:00', 'clicked_at' => '2026-11-16 09:05:00'],
        ]);
        $other = Message::create(['project_id' => $this->project->id, 'name' => 'Older', 'contents' => ['en' => 'Hi'], 'targeting' => ['segments' => ['All']]]);
        DB::table('deliveries')->insert(['message_id' => $other->id, 'subscription_id' => $device->id, 'status' => 'sent', 'sent_at' => '2026-11-15 09:00:00']);

        $integration = $this->project->integrations()->create(['kind' => 's3', 'name' => 'Archive', 'secret' => 'secret-key', 'config' => [
            'bucket' => 'exports', 'region' => 'eu-west-3', 'prefix' => 'carillon-data/', 'key_id' => 'AKIDEXAMPLE', 'path_style' => false,
        ]]);
        RunIntegration::dispatchSync($integration->id, '2026-11-16');

        $key = "carillon-data/carillon/{$this->project->app_id}/deliveries/2026/11/16.csv.gz";
        Http::assertSent(function (HttpRequest $request) use ($key) {
            if ($request->method() !== 'PUT' || $request->url() !== "https://exports.s3.eu-west-3.amazonaws.com/$key") {
                return false;
            }
            $csv = gzdecode($request->body());
            $this->assertStringStartsWith('message_id,message_name,', $csv);
            $this->assertStringContainsString('"Promo, ""today""",push', $csv);
            $this->assertStringContainsString(',u-1,android,sent,', $csv);
            $this->assertStringNotContainsString('Older', $csv);
            $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20261117/eu-west-3/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=', $request->header('Authorization')[0]);
            $this->assertSame(hash('sha256', $request->body()), $request->header('x-amz-content-sha256')[0]);

            return true;
        });
        $this->assertSame(1, $integration->fresh()->last_result['rows']);
    }

    public function test_the_signature_matches_the_aws_reference(): void
    {
        // Example "PUT Object" of the AWS documentation (Signature V4, header-based auth),
        // reduced to the headers signed by Carillon.
        $headers = S3Export::sign('PUT', 'examplebucket.s3.amazonaws.com', '/test%24file.text', hash('sha256', 'Welcome to Amazon S3.'), 'us-east-1',
            'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', CarbonImmutable::parse('2013-05-24 00:00:00', 'UTC'));

        $this->assertSame('20130524T000000Z', $headers['x-amz-date']);
        $this->assertMatchesRegularExpression('#^AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=[0-9a-f]{64}$#', $headers['Authorization']);
    }
}
