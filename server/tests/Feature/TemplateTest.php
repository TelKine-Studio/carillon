<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
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
}
