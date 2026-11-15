<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $editor;

    protected Project $project;

    protected Subscriber $alice;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->admin()->create();
        $this->editor = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->project->addMember($this->editor, ProjectRole::Editor);

        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ec, $p8);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'apns', 'details' => ['key_id' => 'ABC123DEFG', 'team_id' => '4ZA5MXYWG7', 'bundle_id' => 'com.example'], 'secret' => $p8]);
        Http::fake(['api.push.apple.com/*' => Http::response(null, 200)]);

        $this->alice = Subscriber::create(['project_id' => $this->project->id, 'external_id' => 'alice', 'tags' => ['plan' => 'vip'], 'language' => 'fr']);
        Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $this->alice->id, 'type' => 'ios', 'token' => 'tok-alice', 'environment' => 'production', 'last_active_at' => now()]);
        $bob = Subscriber::create(['project_id' => $this->project->id, 'tags' => ['plan' => 'free']]);
        Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $bob->id, 'type' => 'ios', 'token' => 'tok-bob']);
    }

    protected function url(string $path = ''): string
    {
        return "/projects/{$this->project->app_id}{$path}";
    }

    public function test_every_page_renders(): void
    {
        $message = Message::create(['project_id' => $this->project->id, 'contents' => ['en' => 'Hello'], 'targeting' => ['segments' => ['All']], 'status' => 'sent', 'recipients' => 2, 'successful' => 2]);
        $segment = Segment::create(['project_id' => $this->project->id, 'name' => 'VIP', 'filters' => [['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'vip']]]);

        $this->actingAs($this->editor);
        foreach (['', '/messages', '/messages/create', "/messages/create?from={$message->id}", "/messages/{$message->id}", '/audience', "/audience/{$this->alice->id}", '/segments', '/segments/create', "/segments/{$segment->id}/edit"] as $path) {
            $this->get($this->url($path))->assertOk();
        }
        $this->get($this->url('/segments'))->assertSee('VIP');
    }

    public function test_the_composer_sends_with_languages_buttons_and_data(): void
    {
        $this->actingAs($this->editor)->post($this->url('/messages'), [
            'audience' => 'segments',
            'included_segments' => ['Subscribed Users'],
            'texts' => [['language' => 'fr', 'title' => 'Soldes', 'body' => '-20 %'], ['language' => 'en', 'title' => 'Sale', 'body' => '20% off']],
            'buttons' => [['text' => 'Voir', 'id' => 'see'], ['text' => '', 'id' => '']],
            'data' => [['key' => 'promo', 'value' => 'SOLDES20']],
            'url' => 'https://shop.example.com',
            'priority' => 10,
            'schedule' => 'now',
        ])->assertRedirect();

        $message = Message::sole();
        $this->assertSame(['fr' => '-20 %', 'en' => '20% off'], $message->contents);
        $this->assertSame([['id' => 'see', 'text' => 'Voir']], $message->option('buttons'));
        $this->assertSame(['promo' => 'SOLDES20'], $message->option('data'));
        $this->assertSame([2, 'sent', $this->editor->id], [$message->successful, $message->status, $message->created_by]);
    }

    public function test_the_composer_targets_users_estimates_and_schedules(): void
    {
        $this->actingAs($this->editor);

        $this->postJson($this->url('/messages/estimate'), ['audience' => 'users', 'external_ids' => "alice\nnobody"])->assertJsonPath('total', 1);
        $this->postJson($this->url('/messages/estimate'), ['audience' => 'segments', 'included_segments' => ['Subscribed Users']])->assertJsonPath('total', 2)->assertJsonPath('platforms.ios', 2);

        $this->post($this->url('/messages'), [
            'audience' => 'users', 'external_ids' => 'alice', 'texts' => [['language' => 'en', 'body' => 'Later']],
            'schedule' => 'later', 'send_at' => now()->addDay()->format('Y-m-d\TH:i'), 'timezone' => 'Africa/Abidjan',
        ])->assertRedirect();
        $this->assertSame('scheduled', Message::sole()->status);

        $this->post($this->url('/messages/'.Message::sole()->id.'/cancel'))->assertRedirect();
        $this->assertSame('canceled', Message::sole()->status);

        // Nobody matches: a readable error, nothing created.
        $this->post($this->url('/messages'), ['audience' => 'users', 'external_ids' => 'nobody', 'texts' => [['language' => 'en', 'body' => 'Hi']]])
            ->assertSessionHasErrors('alert');
        $this->assertSame(1, Message::count());
    }

    public function test_segments_are_built_counted_and_validated(): void
    {
        $this->actingAs($this->editor);
        $filters = json_encode([['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'vip'], ['operator' => 'OR'], ['field' => 'tag', 'key' => 'plan', 'relation' => '=', 'value' => 'free']]);

        $this->postJson($this->url('/segments/count'), ['filters' => $filters])->assertJsonPath('total', 2);
        $this->post($this->url('/segments'), ['name' => 'Everyone tagged', 'filters' => $filters])->assertRedirect();
        $this->assertCount(3, Segment::sole()->filters);

        $this->post($this->url('/segments'), ['name' => 'Active Users', 'filters' => $filters])->assertSessionHasErrors('name');
        $this->post($this->url('/segments'), ['name' => 'Bad', 'filters' => json_encode([['field' => 'nope', 'relation' => '=']])])->assertSessionHasErrors();
        $this->post($this->url('/segments'), ['name' => 'Empty', 'filters' => '[]'])->assertSessionHasErrors('filters');
    }

    public function test_tags_are_edited_and_tests_sent_from_a_person(): void
    {
        $this->actingAs($this->editor)->put($this->url("/audience/{$this->alice->id}/tags"), ['tags' => [['key' => 'plan', 'value' => 'gold'], ['key' => 'city', 'value' => 'Abidjan'], ['key' => '', 'value' => 'x']]])
            ->assertRedirect();
        $this->assertSame(['plan' => 'gold', 'city' => 'Abidjan'], $this->alice->fresh()->tags);

        $this->post($this->url("/audience/{$this->alice->id}/test"), ['body' => 'Ping'])->assertRedirect();
        $this->assertSame(1, Message::sole()->successful);
    }

    public function test_viewers_read_but_cannot_send(): void
    {
        $viewer = User::factory()->create();
        $this->project->addMember($viewer, ProjectRole::Viewer);

        $this->actingAs($viewer)->get($this->url('/messages'))->assertOk();
        $this->get($this->url('/messages/create'))->assertForbidden();
        $this->post($this->url('/messages'), ['texts' => [['language' => 'en', 'body' => 'x']], 'included_segments' => ['All']])->assertForbidden();
        $this->put($this->url("/audience/{$this->alice->id}/tags"), ['tags' => []])->assertForbidden();
        $this->post($this->url('/segments'), ['name' => 'x', 'filters' => '[]'])->assertForbidden();
    }
}
