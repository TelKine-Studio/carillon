<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inbox_of_a_person(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);
        $rsa = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $pem);
        PushCredential::create(['project_id' => $project->id, 'provider' => 'fcm', 'details' => ['project_id' => 'shop'], 'secret' => json_encode(['project_id' => 'shop', 'client_email' => 'a@shop.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 't']), 'fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);
        [, $key] = ApiKey::generate($project, 'test');

        $base = '/api/v1/sdk/apps/'.$project->app_id;
        $phone = $this->postJson("$base/subscriptions", ['type' => 'android', 'token' => 'phone', 'external_id' => 'u1', 'language' => 'fr'])->json('id');
        $tablet = $this->postJson("$base/subscriptions", ['type' => 'android', 'token' => 'tablet', 'external_id' => 'u1'])->json('id');

        $send = fn (array $extra) => $this->postJson('/api/v1/notifications', ['app_id' => $project->app_id, 'include_aliases' => ['external_id' => ['u1']], ...$extra], ['Authorization' => 'Key '.$key])->assertOk()->json('id');
        $first = $send(['headings' => ['en' => 'Hi', 'fr' => 'Salut'], 'contents' => ['en' => 'One', 'fr' => 'Un'], 'url' => 'https://shop.test']);
        $this->travel(1)->minutes();
        $second = $send(['contents' => ['en' => 'Two']]);
        $send(['contents' => ['en' => 'Hidden'], 'inbox' => false]);

        // The same list from both devices: newest first, in the language of the person, once each.
        $inbox = $this->getJson("$base/subscriptions/$tablet/inbox")->assertOk()->assertJsonPath('unread', 2);
        $this->assertSame([$second, $first], array_column($inbox->json('messages'), 'id'));
        $this->assertSame(['Salut', 'Un', 'https://shop.test'], [$inbox->json('messages.1.title'), $inbox->json('messages.1.body'), $inbox->json('messages.1.url')]);

        $this->postJson("$base/subscriptions/$phone/inbox", ['message_ids' => [$first]])->assertOk();
        $this->getJson("$base/subscriptions/$tablet/inbox")->assertJsonPath('unread', 1)->assertJsonPath('messages.1.read', true);
        $this->postJson("$base/subscriptions/$phone/inbox", ['all' => true])->assertOk();
        $this->getJson("$base/subscriptions/$phone/inbox")->assertJsonPath('unread', 0);
        $this->postJson("$base/subscriptions/$phone/inbox", ['message_ids' => [$second], 'action' => 'remove'])->assertOk();
        $this->assertSame([$first], array_column($this->getJson("$base/subscriptions/$phone/inbox")->json('messages'), 'id'));

        // The composer can keep a notification out of it.
        $this->actingAs($owner)->post(route('projects.messages.store', $project), [
            'texts' => [['language' => 'en', 'body' => 'Flash']], 'audience' => 'segments', 'included_segments' => ['Subscribed Users'], 'schedule' => 'now', 'inbox' => '0',
        ])->assertRedirect();
        $this->assertFalse($project->messages()->latest('created_at')->get()->firstWhere('contents.en', 'Flash')->option('inbox'));
    }
}
