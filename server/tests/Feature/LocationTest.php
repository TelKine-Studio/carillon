<?php

namespace Tests\Feature;

use App\Audience\Audience;
use App\Enums\ProjectRole;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocationTest extends TestCase
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

    public function test_the_sdk_reports_the_position_rounded_by_default(): void
    {
        $id = $this->device('t1', ['lat' => 5.325312, 'long' => -4.019876, 'location_accuracy' => 35]);

        $device = Subscription::find($id);
        $this->assertSame(5.33, $device->latitude);
        $this->assertSame(-4.02, $device->longitude);
        // Never more precise than the rounding.
        $this->assertSame(1100, $device->location_accuracy);
        $this->assertNotNull($device->located_at);

        $this->patchJson($this->sdk($id), ['lat' => 5.36, 'long' => -3.98])->assertOk()->assertJsonPath('location', true);
        $this->assertSame(5.36, $device->fresh()->latitude);

        // Without coordinates the last position stays.
        $this->patchJson($this->sdk($id), ['session' => true])->assertOk();
        $this->assertSame(5.36, $device->fresh()->latitude);

        $this->patchJson($this->sdk($id), ['lat' => 120, 'long' => 0])->assertStatus(400);

        $this->actingAs($this->owner)->get(route('projects.subscribers.show', [$this->project, $device->subscriber_id]))
            ->assertOk()->assertSee('5.36, -3.98')->assertSee('openstreetmap.org', false);
        // In the tables of the audience too.
        $this->actingAs($this->owner)->get(route('projects.subscribers.index', [$this->project, 'view' => 'devices']))->assertOk()->assertSee('5.36, -3.98');
        $this->actingAs($this->owner)->get(route('projects.subscribers.index', $this->project))->assertOk()->assertSee('5.36, -3.98');
    }

    public function test_the_app_or_the_project_can_stop_it(): void
    {
        $id = $this->device('t1', ['lat' => 5.32, 'long' => -4.02]);

        // The app (or the person) stops sharing: the position is forgotten.
        $this->patchJson($this->sdk($id), ['location_shared' => false, 'lat' => 5.32, 'long' => -4.02])->assertOk();
        $this->assertNull(Subscription::find($id)->latitude);
        $this->assertNull(Subscription::find($id)->located_at);

        $this->patchJson($this->sdk($id), ['lat' => 5.32, 'long' => -4.02])->assertOk();
        $this->assertNotNull(Subscription::find($id)->latitude);

        // Turned off in the project: nothing kept, and the SDK is told.
        $this->actingAs($this->owner)->put(route('projects.location.update', $this->project), ['collect_location' => '0'])->assertRedirect();
        $this->assertFalse($this->project->fresh()->collectsLocation());

        $this->patchJson($this->sdk($id), ['lat' => 5.32, 'long' => -4.02])->assertOk()->assertJsonPath('location', false);
        $this->assertNull(Subscription::find($id)->latitude);

        $this->actingAs($this->owner)->put(route('projects.location.update', $this->project), ['collect_location' => '1'])->assertRedirect();
        $this->assertTrue($this->project->fresh()->collectsLocation());
    }

    public function test_positions_can_be_erased_from_the_settings(): void
    {
        $id = $this->device('t1', ['lat' => 5.32, 'long' => -4.02]);

        $this->actingAs($this->owner)->delete(route('projects.location.destroy', $this->project))->assertRedirect();
        $this->assertNull(Subscription::find($id)->latitude);
    }

    public function test_segments_target_devices_within_a_radius(): void
    {
        $this->device('plateau', ['lat' => 5.3253, 'long' => -4.0200]);
        $this->device('cocody', ['lat' => 5.3600, 'long' => -3.9800]);   // ≈ 5.8 km
        $this->device('bouake', ['lat' => 7.6900, 'long' => -5.0300]);   // ≈ 285 km
        $this->device('unknown');

        $match = fn (int $radius) => app(Audience::class)
            ->query($this->project, ['filters' => [['field' => 'location', 'lat' => '5.3253', 'long' => '-4.0200', 'radius' => (string) $radius]]])
            ->orderBy('subscriptions.token')->pluck('subscriptions.token')->all();

        $this->assertSame(['plateau'], $match(2_000));
        $this->assertSame(['cocody', 'plateau'], $match(10_000));
        $this->assertSame(['bouake', 'cocody', 'plateau'], $match(300_000));

        $this->expectException(ValidationException::class);
        app(Audience::class)->query($this->project, ['filters' => [['field' => 'location', 'lat' => '5.3', 'radius' => '100']]])->get();
    }

    public function test_the_server_of_the_app_can_set_the_position(): void
    {
        [, $key] = ApiKey::generate($this->project, 'Backend');
        $id = $this->device('t1', ['external_id' => 'u1']);

        $this->withHeader('Authorization', 'Key '.$key)
            ->patchJson("/api/v1/apps/{$this->project->app_id}/users/by/external_id/u1", ['properties' => ['lat' => 6.8276, 'long' => -5.2893]])
            ->assertOk()->assertJsonPath('properties.lat', 6.83)->assertJsonPath('properties.long', -5.29);

        $this->assertSame(6.83, Subscription::find($id)->latitude);
    }

    public function test_a_segment_keeps_a_location_condition(): void
    {
        $this->device('plateau', ['lat' => 5.3253, 'long' => -4.0200]);
        $this->device('bouake', ['lat' => 7.69, 'long' => -5.03]);
        $filters = json_encode([['field' => 'location', 'lat' => '5.3253', 'long' => '-4.02', 'radius' => '3000']]);

        $this->actingAs($this->owner)->postJson(route('projects.segments.count', $this->project), ['filters' => $filters])->assertJsonPath('total', 1);

        $this->actingAs($this->owner)->post(route('projects.segments.store', $this->project), ['name' => 'Plateau', 'filters' => $filters])->assertRedirect();
        $this->assertSame([['field' => 'location', 'lat' => 5.3253, 'long' => -4.02, 'radius' => 3000]], $this->project->segments()->first()->filters);

        $this->actingAs($this->owner)->get(route('projects.segments.edit', [$this->project, $this->project->segments()->first()]))->assertOk();
        $this->actingAs($this->owner)->get(route('projects.settings', [$this->project, 'general']))->assertOk()->assertSee(__('Device location'));
    }
}
