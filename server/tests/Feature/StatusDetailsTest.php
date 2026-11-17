<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Why a device is unsubscribed or unreachable (OneSignal "status details"). */
class StatusDetailsTest extends TestCase
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
    }

    protected function device(string $token, array $extra = []): string
    {
        return $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'android', 'token' => $token, ...$extra])
            ->assertCreated()->json('id');
    }

    protected function sdk(string $id): string
    {
        return "/api/v1/sdk/apps/{$this->project->app_id}/subscriptions/{$id}";
    }

    public function test_the_sdk_tells_why_a_device_is_disabled(): void
    {
        $id = $this->device('t1', ['enabled' => true, 'permission' => 'granted']);
        $this->assertNull(Subscription::find($id)->statusDetails());

        $this->patchJson($this->sdk($id), ['enabled' => false, 'permission' => 'denied'])->assertOk();
        $device = Subscription::find($id);
        $this->assertSame('permission_denied', $device->status_reason);
        $this->assertSame(__('Notifications turned off on the device'), $device->statusDetails());
        $this->assertNotNull($device->status_changed_at);

        $this->patchJson($this->sdk($id), ['enabled' => false, 'permission' => 'granted'])->assertOk();
        $this->assertSame('opted_out', Subscription::find($id)->status_reason);

        $this->patchJson($this->sdk($id), ['enabled' => false, 'permission' => 'not_determined'])->assertOk();
        $this->assertSame('permission_not_asked', Subscription::find($id)->status_reason);

        // An older SDK sends no permission.
        $this->patchJson($this->sdk($id), ['enabled' => false])->assertOk();
        $this->assertSame('disabled_on_device', Subscription::find($id)->status_reason);

        // Subscribed again: nothing to explain.
        $this->patchJson($this->sdk($id), ['enabled' => true, 'permission' => 'granted'])->assertOk();
        $this->assertNull(Subscription::find($id)->status_reason);
    }

    public function test_a_token_taken_by_another_registration(): void
    {
        $old = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'ios', 'token' => 'shared'])->assertCreated()->json('id');
        $new = $this->postJson("/api/v1/sdk/apps/{$this->project->app_id}/subscriptions", ['type' => 'ios'])->assertCreated()->json('id');

        $this->patchJson($this->sdk($new), ['token' => 'shared'])->assertOk();

        $this->assertSame('token_reused', Subscription::find($old)->status_reason);
        $this->assertNull(Subscription::find($new)->status_reason);
    }

    public function test_the_audience_shows_and_filters_status_details(): void
    {
        $denied = $this->device('denied', ['enabled' => false, 'permission' => 'denied', 'device_model' => 'Pixel-Denied']);
        $this->device('fine', ['enabled' => true, 'permission' => 'granted', 'device_model' => 'Pixel-Fine']);
        $old = $this->device('old', ['device_model' => 'Pixel-Old']);
        Subscription::find($old)->forceFill(['enabled' => false])->save();

        $page = fn (array $query) => $this->actingAs($this->owner)->get(route('projects.subscribers.index', [$this->project, 'view' => 'devices', ...$query]))->assertOk();

        $page([])->assertSee(__('Status details'))->assertSee(__('Notifications turned off on the device'))->assertSee(__('Reason not recorded'));
        $page(['reason' => 'permission_denied'])->assertSee('Pixel-Denied')->assertDontSee('Pixel-Old')->assertDontSee('Pixel-Fine');
        $page(['reason' => 'unknown'])->assertSee('Pixel-Old')->assertDontSee('Pixel-Denied')->assertDontSee('Pixel-Fine');

        $person = Subscription::find($denied)->subscriber_id;
        $this->actingAs($this->owner)->get(route('projects.subscribers.show', [$this->project, $person]))
            ->assertOk()->assertSee(__('Status details'))->assertSee(__('Notifications turned off on the device'));
    }
}
