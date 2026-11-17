<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_unreachable_devices_get_their_reason_back_from_the_deliveries(): void
    {
        $dead = Subscription::find($this->device('dead'));
        $reused = Subscription::find($this->device('reused'));
        $message = Message::create(['project_id' => $this->project->id, 'contents' => ['en' => 'Hi'], 'targeting' => ['segments' => ['All']], 'status' => 'sent']);
        DB::table('deliveries')->insert(['message_id' => $message->id, 'subscription_id' => $dead->id, 'status' => 'failed', 'error' => 'UNREGISTERED', 'sent_at' => now()]);
        DB::table('subscriptions')->where('id', $dead->id)->update(['invalid_at' => now()]);
        DB::table('subscriptions')->where('id', $reused->id)->update(['invalid_at' => now(), 'token' => null, 'token_hash' => null]);

        (require database_path('migrations/2026_11_24_120000_backfill_status_reasons.php'))->up();

        $this->assertSame(['rejected', 'UNREGISTERED'], [$dead->fresh()->status_reason, $dead->fresh()->status_detail]);
        $this->assertSame('token_reused', $reused->fresh()->status_reason);
    }

    public function test_never_subscribed_last_unsubscribed_and_usage_like_onesignal(): void
    {
        $never = $this->device('never', ['enabled' => false, 'permission' => 'not_determined', 'device_model' => 'Pixel-Never']);
        $left = $this->device('left', ['enabled' => true, 'permission' => 'granted', 'device_model' => 'Pixel-Left']);
        $this->device('stays', ['enabled' => true, 'permission' => 'granted', 'device_model' => 'Pixel-Stays']);

        $this->assertNull(Subscription::find($never)->subscribed_at);
        $this->assertNotNull(Subscription::find($left)->subscribed_at);

        $this->travel(5)->minutes();
        $this->patchJson($this->sdk($left), ['enabled' => false, 'permission' => 'denied'])->assertOk();
        $this->assertNotNull(Subscription::find($left)->unsubscribed_at);
        $this->assertNotNull(Subscription::find($left)->subscribed_at);

        // Heartbeats every minute while the app is open add up.
        $this->postJson($this->sdk($left).'/heartbeat')->assertOk();
        $this->travel(60)->seconds();
        $this->postJson($this->sdk($left).'/heartbeat')->assertOk();
        $this->travel(60)->seconds();
        $this->postJson($this->sdk($left).'/heartbeat')->assertOk();
        $this->assertGreaterThanOrEqual(120, Subscription::find($left)->usage_seconds);
        // A gap longer than the presence window is not usage.
        $this->travel(1)->hours();
        $before = Subscription::find($left)->usage_seconds;
        $this->postJson($this->sdk($left).'/heartbeat')->assertOk();
        $this->assertSame($before, Subscription::find($left)->usage_seconds);

        $page = fn (array $query) => $this->actingAs($this->owner)->get(route('projects.subscribers.index', [$this->project, 'view' => 'devices', ...$query]))->assertOk();
        $page([])->assertSee(__('Never subscribed'))->assertSee(__('Usage duration'))->assertSee(__('Last unsubscribed'))->assertSee('2 min');
        $page(['state' => 'never'])->assertSee('Pixel-Never')->assertDontSee('Pixel-Left')->assertDontSee('Pixel-Stays');
        $page(['state' => 'unsubscribed'])->assertSee('Pixel-Left')->assertDontSee('Pixel-Never');
        $page(['sort' => 'usage', 'dir' => 'desc'])->assertSeeInOrder(['Pixel-Left', 'Pixel-']);
        $page(['segment' => 'Android Users'])->assertSee('Pixel-Stays');
        $page(['segment' => 'iOS Users'])->assertDontSee('Pixel-Stays');
    }

    public function test_people_and_test_devices_tables(): void
    {
        $busy = $this->device('busy', ['external_id' => 'busy-person', 'device_model' => 'Pixel-Busy']);
        $this->device('idle', ['external_id' => 'idle-person', 'device_model' => 'Pixel-Idle']);
        Subscription::find($busy)->forceFill(['sessions' => 40, 'usage_seconds' => 3900, 'is_test' => true, 'test_name' => 'My phone'])->save();

        $page = fn (array $query) => $this->actingAs($this->owner)->get(route('projects.subscribers.index', [$this->project, ...$query]))->assertOk();

        $page([])->assertSee(__('Columns'))->assertSee(__('Usage duration'))->assertSee('1 h 05');
        $page(['sort' => 'sessions', 'dir' => 'desc'])->assertSeeInOrder(['busy-person', 'idle-person']);
        $page(['sort' => 'usage', 'dir' => 'asc'])->assertSeeInOrder(['idle-person', 'busy-person']);

        $page(['view' => 'test'])->assertSee('My phone')->assertSee('Pixel-Busy')->assertDontSee('Pixel-Idle')
            ->assertSee(__('Rename'))->assertDontSee('@js(', false);
    }
}
