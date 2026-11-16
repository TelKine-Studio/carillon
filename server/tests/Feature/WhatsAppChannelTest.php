<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_templates_and_texts(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);

        $this->actingAs($owner)->put(route('projects.push.update', [$project, 'whatsapp']), [
            'phone_number_id' => '1234567890', 'business_account_id' => '9876543210', 'access_token' => 'EAAG-token',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($project->credential('whatsapp')->isUsable());
        $this->get(route('projects.settings', [$project, 'platforms']))->assertOk()->assertSee('9876543210');

        // The same number for SMS and WhatsApp.
        $base = '/api/v1/sdk/apps/'.$project->app_id;
        $device = $this->postJson("$base/subscriptions", ['type' => 'android', 'token' => 'fcm', 'tags' => ['first_name' => 'Ada'], 'language' => 'fr'])->json('id');
        $this->postJson("$base/subscriptions/$device/sms", ['phone_number' => '+225 07 08 09 10 11'])->assertCreated();
        $this->postJson("$base/subscriptions/$device/whatsapp", ['phone_number' => '+225 07 08 09 10 11'])->assertCreated();
        $this->assertSame(['sms', 'whatsapp'], $project->subscriptions()->where('token_hash', hash('sha256', '+2250708091011'))->orderBy('type')->pluck('type')->all());
        [, $key] = ApiKey::generate($project, 'test');
        $this->postJson('/api/v1/apps/'.$project->app_id.'/users', ['identity' => ['external_id' => 'u2'], 'subscriptions' => [['type' => 'WhatsApp', 'token' => '+2250102030405']]], ['Authorization' => 'Key '.$key])->assertCreated();

        Http::fake([
            'graph.facebook.com/v23.0/9876543210/message_templates*' => Http::response(['data' => [
                ['name' => 'promo', 'language' => 'fr', 'status' => 'APPROVED', 'category' => 'MARKETING', 'components' => [['type' => 'BODY', 'text' => 'Bonjour {{1}}, -{{2}} % aujourd’hui']]],
                ['name' => 'draft', 'language' => 'fr', 'status' => 'PENDING', 'components' => []],
            ]]),
            'graph.facebook.com/v23.0/1234567890/messages' => function (Request $request) {
                return $request['to'] === '2250102030405'
                    ? Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable', 'error_data' => ['details' => 'Not on WhatsApp']]], 400)
                    : Http::response(['messages' => [['id' => 'wamid.1']]]);
            },
        ]);

        $this->getJson(route('projects.whatsapp.templates', $project))->assertOk()
            ->assertJsonCount(1, 'templates')->assertJsonPath('templates.0.variables', ['1', '2']);

        // From the composer: a template with variables.
        $this->post(route('projects.messages.store', $project), [
            'channel' => 'whatsapp', 'whatsapp_mode' => 'template', 'whatsapp_template' => 'promo|fr',
            'whatsapp_variables' => ['1' => '{{ tags.first_name | default: "toi" }}', '2' => '20'], 'whatsapp_preview' => 'Bonjour Ada, -20 % aujourd’hui',
            'audience' => 'segments', 'included_segments' => ['Subscribed Users'], 'schedule' => 'now',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $message = Message::query()->where('channel', 'whatsapp')->first();
        $this->assertSame([2, 1, 1], [$message->recipients, $message->successful, $message->failed]);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages') && $request['to'] === '2250708091011'
            && $request['template']['name'] === 'promo' && $request['template']['language']['code'] === 'fr'
            && $request['template']['components'][0]['parameters'] === [['type' => 'text', 'text' => 'Ada'], ['type' => 'text', 'text' => '20']]);
        $this->assertNotNull($project->subscriptions()->where('type', 'whatsapp')->where('token_hash', hash('sha256', '+2250102030405'))->value('invalid_at'));
        // The SMS subscription of that number is untouched.
        $this->assertNull($project->subscriptions()->where('type', 'sms')->value('invalid_at'));
        $this->get(route('projects.messages.show', [$project, $message]))->assertOk()->assertSee('Bonjour Ada');

        // From the API: a text (24-hour window).
        $this->postJson('/api/v1/notifications', ['app_id' => $project->app_id, 'target_channel' => 'whatsapp', 'include_phone_numbers' => ['+2250708091011'], 'contents' => ['en' => 'Merci {{ tags.first_name }} !']], ['Authorization' => 'Key '.$key])
            ->assertOk()->assertJsonPath('recipients', 1);
        Http::assertSent(fn (Request $request) => ($request['type'] ?? null) === 'text' && $request['text']['body'] === 'Merci Ada !');
    }
}
