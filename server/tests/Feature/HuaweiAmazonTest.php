<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HuaweiAmazonTest extends TestCase
{
    use RefreshDatabase;

    public function test_huawei_and_amazon_devices_receive_notifications(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);

        // Keys typed in the settings (secrets kept when left empty).
        $this->actingAs($owner)->put(route('projects.push.update', [$project, 'hms']), ['app_id' => '101234567', 'app_secret' => 'hms-secret'])->assertSessionHasNoErrors();
        $this->put(route('projects.push.update', [$project, 'adm']), ['client_id' => 'amzn1.application-oa2-client.1', 'client_secret' => 'adm-secret'])->assertSessionHasNoErrors();
        $this->put(route('projects.push.update', [$project, 'hms']), ['app_id' => '101234567'])->assertSessionHasNoErrors();
        $this->assertSame('hms-secret', $project->credential('hms')->secret);
        $this->get(route('projects.settings', [$project, 'platforms']))->assertOk()->assertSee('101234567')->assertSee('amzn1.application-oa2-client.1');

        // A phone registered by the SDK switches to Push Kit (setPushToken).
        $base = '/api/v1/sdk/apps/'.$project->app_id;
        $id = $this->postJson("$base/subscriptions", ['type' => 'android', 'token' => 'fcm-token', 'language' => 'fr'])->json('id');
        $this->patchJson("$base/subscriptions/$id", ['type' => 'huawei', 'token' => 'hms-good'])->assertOk();
        $this->assertSame('huawei', Subscription::find($id)->type);

        $subscriber = Subscriber::create(['project_id' => $project->id, 'language' => 'fr']);
        Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'huawei', 'token' => 'hms-dead', 'enabled' => true]);
        Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'amazon', 'token' => 'amzn1.adm-registration.good', 'enabled' => true]);
        Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'amazon', 'token' => 'amzn1.adm-registration.gone', 'enabled' => true]);

        Http::fake([
            'oauth-login.cloud.huawei.com/*' => Http::response(['access_token' => 'hms-token', 'expires_in' => 3600]),
            'push-api.cloud.huawei.com/*' => Http::response(['code' => '80100000', 'msg' => json_encode(['success' => 1, 'failure' => 1, 'illegal_tokens' => ['hms-dead']]), 'requestId' => '1']),
            'api.amazon.com/auth/O2/token' => Http::response(['access_token' => 'Atc|adm', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            'api.amazon.com/messaging/registrations/amzn1.adm-registration.gone/*' => Http::response(['reason' => 'Unregistered'], 400),
            'api.amazon.com/messaging/*' => Http::response(['registrationID' => 'amzn1.adm-registration.good']),
        ]);

        [, $key] = ApiKey::generate($project, 'test');
        $messageId = $this->postJson('/api/v1/notifications', [
            'app_id' => $project->app_id, 'included_segments' => ['Subscribed Users'],
            'headings' => ['en' => 'Hi', 'fr' => 'Salut'], 'contents' => ['en' => 'Sale', 'fr' => 'Soldes'], 'url' => 'https://shop.test/sale',
        ], ['Authorization' => 'Key '.$key])->assertOk()->json('id');

        $message = Message::find($messageId);
        $this->assertSame([2, 2], [$message->successful, $message->failed]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth-login.cloud.huawei.com/oauth2/v3/token' && $request['client_id'] === '101234567' && $request['client_secret'] === 'hms-secret');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://push-api.cloud.huawei.com/v1/101234567/messages:send'
            && $request->hasHeader('Authorization', 'Bearer hms-token')
            && $request['message']['token'] === ['hms-good', 'hms-dead']
            && $request['message']['android']['notification']['title'] === 'Salut'
            && $request['message']['android']['notification']['click_action'] === ['type' => 2, 'url' => 'https://shop.test/sale']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.amazon.com/auth/O2/token' && $request['scope'] === 'messaging:push');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.amazon.com/messaging/registrations/amzn1.adm-registration.good/messages'
            && $request->hasHeader('X-Amzn-Type-Version', 'com.amazon.device.messaging.ADMMessage@1.0')
            && json_decode($request['data']['carillon'], true)['body'] === 'Soldes');

        // Refused tokens are no longer targeted.
        $this->assertSame(['amzn1.adm-registration.gone', 'hms-dead'], Subscription::query()->whereNotNull('invalid_at')->orderBy('token')->pluck('token')->all());

        // Status details keep the answer of the provider.
        $gone = Subscription::query()->where('token', 'amzn1.adm-registration.gone')->first();
        $this->assertSame('rejected', $gone->status_reason);
        $this->assertSame('Unregistered', $gone->status_detail);
        $this->assertSame(__('Refused by :provider', ['provider' => 'Amazon']).' : Unregistered', $gone->statusDetails());
        $this->assertSame('rejected', Subscription::query()->where('token', 'hms-dead')->value('status_reason'));
    }
}
