<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Push\Payload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Project $project;

    protected string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $this->owner->id]);
        $this->project->addMember($this->owner, ProjectRole::Owner);
        PushCredential::create(['project_id' => $this->project->id, 'provider' => 'apns', 'details' => ['key_id' => 'ABCDEFGHIJ', 'team_id' => 'ABCDEFGHIJ', 'bundle_id' => 'com.shop'], 'secret' => 'key']);
        [, $this->key] = ApiKey::generate($this->project, 'test');

        $subscriber = Subscriber::create(['project_id' => $this->project->id]);
        Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $subscriber->id, 'type' => 'ios', 'token' => str_repeat('a', 64), 'enabled' => true]);
    }

    public function test_api_crud(): void
    {
        $headers = ['Authorization' => 'Key '.$this->key];

        $id = $this->postJson('/api/v1/templates', [
            'app_id' => $this->project->app_id,
            'name' => 'Shipped',
            'headings' => ['en' => 'Shipped', 'fr' => 'Expédiée'],
            'contents' => ['en' => 'Your order is on its way', 'fr' => 'Votre commande arrive'],
            'url' => 'https://shop.test/orders',
            'buttons' => [['id' => 'track', 'text' => 'Track']],
            'included_segments' => ['All'],
        ], $headers)->assertOk()->assertJsonPath('name', 'Shipped')->assertJsonPath('url', 'https://shop.test/orders')->json('id');

        // The audience is not part of a template.
        $this->assertArrayNotHasKey('included_segments', Template::find($id)->options);

        $this->getJson('/api/v1/templates', $headers)->assertOk()->assertJsonPath('total_count', 1)->assertJsonPath('templates.0.id', $id);

        $this->patchJson("/api/v1/templates/$id", ['big_picture' => 'https://shop.test/box.jpg'], $headers)
            ->assertOk()->assertJsonPath('big_picture', 'https://shop.test/box.jpg')->assertJsonPath('url', 'https://shop.test/orders');

        $this->deleteJson("/api/v1/templates/$id", [], $headers)->assertOk();
        $this->getJson("/api/v1/templates/$id", $headers)->assertNotFound();
        $this->getJson('/api/v1/templates/not-a-uuid', $headers)->assertNotFound();
    }

    public function test_sending_with_a_template(): void
    {
        $template = $this->project->templates()->create([
            'name' => 'Shipped',
            'headings' => ['en' => 'Shipped'],
            'contents' => ['en' => 'Your order is on its way'],
            'options' => ['url' => 'https://shop.test/orders', 'ttl' => 3600],
        ]);

        $id = $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'template_id' => $template->id,
            'included_segments' => ['Subscribed Users'],
            'url' => 'https://shop.test/orders/42',
        ], ['Authorization' => 'Key '.$this->key])->assertOk()->json('id');

        $message = $this->project->messages()->find($id);
        $this->assertSame($template->id, $message->template_id);
        $this->assertSame(['en' => 'Your order is on its way'], $message->contents);
        $this->assertSame('https://shop.test/orders/42', $message->option('url'));
        $this->assertSame(3600, $message->option('ttl'));

        $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'template_id' => '00000000-0000-0000-0000-000000000000',
            'included_segments' => ['Subscribed Users'],
        ], ['Authorization' => 'Key '.$this->key])->assertStatus(400)->assertJsonPath('errors.0', 'Template not found.');
    }

    public function test_dashboard(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('projects.templates.index', $this->project))->assertOk()->assertSee('No template yet');
        $this->get(route('projects.templates.create', $this->project))->assertOk()->assertSee('template_name', false);

        $this->post(route('projects.templates.store', $this->project), [
            'template_name' => 'Welcome',
            'texts' => [['language' => 'en', 'title' => 'Welcome', 'body' => 'Glad to see you']],
            'url' => 'https://shop.test',
            'buttons' => [['id' => 'start', 'text' => 'Start']],
            'priority' => 10,
        ])->assertRedirect(route('projects.templates.index', $this->project));

        $template = $this->project->templates()->first();
        $this->assertSame('Welcome', $template->name);
        $this->assertSame('https://shop.test', $template->option('url'));
        $this->assertSame('start', $template->option('buttons.0.id'));

        $this->get(route('projects.templates.index', $this->project))->assertOk()->assertSee('Welcome');
        $this->get(route('projects.templates.edit', [$this->project, $template]))->assertOk()->assertSee('Glad to see you');

        // The composer starts from it, and the message remembers it.
        $this->get(route('projects.messages.create', [$this->project, 'template' => $template->id]))->assertOk()->assertSee('Glad to see you');
        $this->post(route('projects.messages.store', $this->project), [
            'template_id' => $template->id,
            'texts' => [['language' => 'en', 'body' => 'Glad to see you']],
            'audience' => 'segments',
            'included_segments' => ['Subscribed Users'],
            'schedule' => 'now',
        ])->assertRedirect();
        $this->assertSame($template->id, $this->project->messages()->first()->template_id);

        $this->put(route('projects.templates.update', [$this->project, $template]), [
            'template_name' => 'Hello',
            'texts' => [['language' => 'en', 'body' => 'Hi']],
            'priority' => 10,
        ])->assertRedirect();
        $template->refresh();
        $this->assertSame('Hello', $template->name);
        $this->assertNull($template->option('url'));

        $this->delete(route('projects.templates.destroy', [$this->project, $template]))->assertRedirect();
        $this->assertDatabaseCount('templates', 0);
        $this->assertNull($this->project->messages()->first()->template_id);
    }

    public function test_email_templates_and_message_labels(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.templates.store', $this->project), [
            'template_name' => 'Newsletter',
            'channel' => 'email',
            'email_subject' => 'News of the week',
            'email_body' => '<p>Hello {{ tags.first_name }}</p>',
        ])->assertRedirect(route('projects.templates.index', $this->project))->assertSessionHasNoErrors();

        $template = $this->project->templates()->first();
        $this->assertSame('email', $template->option('target_channel'));
        $this->assertSame('News of the week', $template->option('email_subject'));
        $this->assertSame([], $template->contents);

        $this->get(route('projects.templates.index', $this->project))->assertOk()->assertSee('News of the week');
        $this->get(route('projects.templates.edit', [$this->project, $template]))->assertOk()->assertSee('News of the week');
        $this->get(route('projects.messages.create', [$this->project, 'template' => $template->id]))->assertOk()->assertSee('channel\u0022:\u0022email', false);

        // An email template needs a subject.
        $this->post(route('projects.templates.store', $this->project), ['template_name' => 'Empty', 'channel' => 'email', 'email_body' => '<p>x</p>'])
            ->assertSessionHasErrors('email_subject');

        // Labels: typed in the composer, shown and filtered in the list, edited on the report.
        $this->post(route('projects.messages.store', $this->project), [
            'texts' => [['language' => 'en', 'body' => 'Sale today']],
            'audience' => 'segments',
            'included_segments' => ['Subscribed Users'],
            'schedule' => 'now',
            'labels' => 'Promo, black friday, promo',
        ])->assertRedirect();
        $message = $this->project->messages()->latest()->first();
        $this->assertSame(['Promo', 'black friday'], $message->labels);

        $this->get(route('projects.messages.index', [$this->project, 'label' => 'Promo']))->assertOk()->assertSee('Sale today');
        $this->get(route('projects.messages.index', [$this->project, 'label' => 'other']))->assertOk()->assertDontSee('Sale today');

        $this->put(route('projects.messages.labels', [$this->project, $message]), ['labels' => 'vip'])->assertRedirect();
        $this->assertSame(['vip'], $message->fresh()->labels);
        $this->get(route('projects.messages.show', [$this->project, $message]))->assertOk()->assertSee('vip');
    }

    public function test_the_visual_email_design_is_kept_to_edit_again(): void
    {
        $this->actingAs($this->owner);
        $design = ['accent' => '#2563eb', 'background' => '#f4f4f5', 'blocks' => [['type' => 'heading', 'text' => 'Spring sale', 'align' => 'left']]];

        $this->post(route('projects.templates.store', $this->project), [
            'template_name' => 'Spring',
            'channel' => 'email',
            'email_subject' => 'Spring',
            'email_body' => '<h1>Spring sale</h1>',
            'email_design' => json_encode($design),
        ])->assertSessionHasNoErrors();

        $template = $this->project->templates()->first();
        $this->assertSame('Spring sale', $template->option('email_design.blocks.0.text'));
        $this->get(route('projects.templates.edit', [$this->project, $template]))->assertOk()->assertSee('#2563eb', false);

        // A broken design is ignored, the HTML stays.
        $this->post(route('projects.templates.store', $this->project), [
            'template_name' => 'Broken', 'channel' => 'email', 'email_subject' => 'x', 'email_body' => '<p>x</p>', 'email_design' => '{nope',
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->project->templates()->where('name', 'Broken')->first()->option('email_design'));
    }

    public function test_the_icon_of_the_notification(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.messages.store', $this->project), [
            'texts' => [['language' => 'en', 'body' => 'Hi']], 'audience' => 'segments', 'included_segments' => ['Subscribed Users'], 'schedule' => 'now',
            'icon' => 'https://shop.test/logo.png',
        ])->assertRedirect();
        $message = $this->project->messages()->first();
        $this->assertSame(['https://shop.test/logo.png', 'https://shop.test/logo.png'], [$message->option('large_icon'), $message->option('chrome_web_icon')]);
        $this->assertSame('https://shop.test/logo.png', json_decode(Payload::fcm($message, 'en', 't')['message']['data']['carillon'], true)['li']);

        // Without one, the icon of the project (when it has one).
        $other = Message::make(['contents' => ['en' => 'x']])->setRelation('project', $this->project);
        $this->assertNull(Payload::largeIcon($other));
        $this->project->forceFill(['icon_path' => 'icons/shop.png'])->save();
        $this->assertStringContainsString('/api/v1/sdk/apps/'.$this->project->app_id.'/icon', Payload::largeIcon($other));
        $this->get(route('projects.messages.create', $this->project))->assertOk()->assertSee('name="icon"', false);
    }

    public function test_pages_contain_no_uncompiled_blade_directives(): void
    {
        // @js() inside a component attribute is not compiled: Alpine then fails on it.
        $this->actingAs($this->owner);
        foreach ([route('projects.messages.create', $this->project), route('projects.settings', [$this->project, 'webhooks'])] as $url) {
            $response = $this->get($url);
            if ($response->isOk()) {
                $this->assertStringNotContainsString('@js(', $response->getContent(), $url);
            }
        }
    }
}
