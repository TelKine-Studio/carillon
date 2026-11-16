<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Journeys\JourneyRunner;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Sms\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $this->owner->id]);
        $this->project->addMember($this->owner, ProjectRole::Owner);
    }

    public function test_phone_numbers_and_parts(): void
    {
        $this->assertSame('+2250708091011', Phone::normalize('+225 07 08 09 10 11'));
        $this->assertSame('+33612345678', Phone::normalize('0033 6-12-34-56-78'));
        $this->assertNull(Phone::normalize('0708091011'));
        $this->assertSame(1, Phone::segments(str_repeat('é', 160)));
        $this->assertSame(2, Phone::segments(str_repeat('a', 161)));
        $this->assertSame(2, Phone::segments(str_repeat('✨', 71)));
    }

    public function test_twilio_settings_and_sending(): void
    {
        $sid = 'AC'.str_repeat('a1', 16);
        $this->actingAs($this->owner)->put(route('projects.push.update', [$this->project, 'sms']), [
            'account_sid' => $sid, 'from' => '+225 01 02 03 04 05', 'auth_token' => 'token-123',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $credential = $this->project->credential('sms');
        $this->assertSame('+2250102030405', $credential->detail('from'));
        $this->assertSame('token-123', $credential->secret);
        $this->assertTrue($credential->isUsable());

        // The token is kept when left empty.
        $this->put(route('projects.push.update', [$this->project, 'sms']), ['account_sid' => $sid, 'from' => 'MaBoutique'])->assertSessionHasNoErrors();
        $this->assertSame('token-123', $credential->fresh()->secret);
        $this->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertSee('MaBoutique');

        [, $key] = ApiKey::generate($this->project, 'test');
        $base = '/api/v1/sdk/apps/'.$this->project->app_id;
        $device = $this->postJson("$base/subscriptions", ['type' => 'android', 'token' => 'fcm-token', 'tags' => ['first_name' => 'Ada'], 'language' => 'fr'])->json('id');
        $this->postJson("$base/subscriptions/$device/sms", ['phone_number' => '+225 07 08 09 10 11'])->assertCreated()->assertJsonPath('phone_number', '+2250708091011');
        $this->postJson("$base/subscriptions/$device/sms", ['phone_number' => '0708'])->assertStatus(422);
        $this->postJson('/api/v1/apps/'.$this->project->app_id.'/users', ['identity' => ['external_id' => 'u2'], 'subscriptions' => [['type' => 'SMS', 'token' => '+33 6 12 34 56 78']]], ['Authorization' => 'Key '.$key])->assertCreated();

        Http::fake([
            'api.twilio.com/*' => function (Request $request) {
                return str_contains($request->body(), '33612345678')
                    ? Http::response(['code' => 21211, 'message' => 'Invalid To number'], 400)
                    : Http::response(['sid' => 'SM1'], 201);
            },
        ]);

        $id = $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'target_channel' => 'sms',
            'included_segments' => ['Subscribed Users'],
            'contents' => ['en' => 'Hello', 'fr' => 'Bonjour {{ tags.first_name | default: "toi" }} !'],
        ], ['Authorization' => 'Key '.$key])->assertOk()->assertJsonPath('recipients', 2)->json('id');

        $message = Message::find($id);
        $this->assertSame('sms', $message->channel);
        $this->assertSame([1, 1], [$message->successful, $message->failed]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), "Accounts/$sid/Messages.json")
            && $request['To'] === '+2250708091011' && $request['Body'] === 'Bonjour Ada !' && $request['From'] === 'MaBoutique');
        // The refused number is dead: not targeted any more.
        $this->assertNotNull($this->project->subscriptions()->where('type', 'sms')->where('token_hash', hash('sha256', '+33612345678'))->value('invalid_at'));

        // Specific numbers, from the composer.
        $this->post(route('projects.messages.store', $this->project), [
            'channel' => 'sms', 'audience' => 'devices', 'phone_numbers' => "+225 07 08 09 10 11\n", 'sms_body' => 'Promo', 'schedule' => 'now',
        ])->assertRedirect();
        $last = $this->project->messages()->whereKeyNot($id)->first();
        $this->assertSame(['sms', 1], [$last->channel, $last->recipients]);
        $this->get(route('projects.messages.show', [$this->project, $last]))->assertOk()->assertSee('Promo');
        $this->get(route('projects.messages.create', $this->project))->assertOk()->assertSee('sms_body', false);
    }

    public function test_journeys_can_send_sms(): void
    {
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'sms', 'details' => ['driver' => 'twilio', 'account_sid' => 'AC'.str_repeat('b', 32), 'from' => '+2250102030405'], 'secret' => 't']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $journey = $this->project->journeys()->create(['name' => 'Welcome', 'status' => 'active', 'trigger' => ['type' => 'tag', 'key' => 'plan', 'value' => 'pro'], 'steps' => [['type' => 'sms', 'body' => 'Bienvenue {{ tags.plan }}']]]);

        $subscriber = Subscriber::create(['project_id' => $this->project->id, 'tags' => ['plan' => 'pro']]);
        Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $subscriber->id, 'type' => 'sms', 'token' => '+2250708091011', 'enabled' => true]);
        $runner = app(JourneyRunner::class);
        $runner->tagsChanged($subscriber, null);
        $runner->run();

        $message = $journey->messages()->first();
        $this->assertSame(['sms', 1], [$message->channel, $message->successful]);
        Http::assertSent(fn (Request $request) => $request['Body'] === 'Bienvenue pro');
    }

    public function test_sending_with_orange(): void
    {
        $this->actingAs($this->owner)->put(route('projects.push.update', [$this->project, 'sms']), [
            'driver' => 'orange', 'client_id' => 'client-1', 'client_secret' => 'secret-1', 'sender_address' => '+225 0000', 'sender_name' => 'MaRecharge',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $credential = $this->project->credential('sms');
        $this->assertSame(['orange', '+2250000', 'secret-1'], [$credential->detail('driver'), $credential->detail('sender_address'), $credential->secret]);
        $this->assertTrue($credential->isUsable());
        $this->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertSee('client-1');

        // Switching provider asks for the new secret.
        $this->put(route('projects.push.update', [$this->project, 'sms']), ['driver' => 'twilio', 'account_sid' => 'AC'.str_repeat('c', 32), 'from' => 'Shop'])->assertSessionHasErrors('auth_token');

        $subscriber = Subscriber::create(['project_id' => $this->project->id, 'tags' => ['first_name' => 'Ada']]);
        foreach (['+2250708091011', '+2250102030405'] as $phone) {
            Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $subscriber->id, 'type' => 'sms', 'token' => $phone, 'enabled' => true]);
        }

        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response(['token_type' => 'Bearer', 'access_token' => 'tok-1', 'expires_in' => 3600]),
            'api.orange.com/smsmessaging/*' => function (Request $request) {
                return $request['outboundSMSMessageRequest']['address'] === 'tel:+2250102030405'
                    ? Http::response(['requestError' => ['serviceException' => ['messageId' => 'SVC0004', 'text' => 'No valid addresses provided in message part %1', 'variables' => 'address']]], 400)
                    : Http::response(['outboundSMSMessageRequest' => []], 201);
            },
        ]);

        [, $key] = ApiKey::generate($this->project, 'test');
        $id = $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id, 'target_channel' => 'sms', 'included_segments' => ['Subscribed Users'], 'contents' => ['en' => 'Salut {{ tags.first_name }}'],
        ], ['Authorization' => 'Key '.$key])->assertOk()->json('id');

        $message = Message::find($id);
        $this->assertSame([1, 1], [$message->successful, $message->failed]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.orange.com/oauth/v3/token' && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-1:secret-1')) && $request['grant_type'] === 'client_credentials');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests'
            && $request->hasHeader('Authorization', 'Bearer tok-1')
            && $request['outboundSMSMessageRequest']['senderAddress'] === 'tel:+2250000'
            && $request['outboundSMSMessageRequest']['senderName'] === 'MaRecharge'
            && $request['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] === 'Salut Ada');
        // One token for the whole message.
        Http::assertSentCount(3);
        $this->assertNotNull($this->project->subscriptions()->where('token_hash', hash('sha256', '+2250102030405'))->value('invalid_at'));
    }
}
