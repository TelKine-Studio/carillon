<?php

namespace Tests\Feature;

use App\Actions\CreateMessage;
use App\Audience\Audience;
use App\Enums\ProjectRole;
use App\Jobs\DispatchMessage;
use App\Jobs\SendBatch;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Push\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimezoneDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_times_of_day(): void
    {
        $this->assertSame('09:00', LocalTime::parse('9:00AM')->format());
        $this->assertSame('21:30', LocalTime::parse('9:30 pm')->format());
        $this->assertSame('00:15', LocalTime::parse('12:15AM')->format());
        $this->assertSame('12:00', LocalTime::parse('12:00PM')->format());
        $this->assertSame('18:05', LocalTime::parse('18:05')->format());
        $this->assertNull(LocalTime::parse('25:00'));
        $this->assertNull(LocalTime::parse('13:00PM'));
        $this->assertNull(LocalTime::parse('soon'));
    }

    public function test_next_occurrence_in_a_time_zone(): void
    {
        $from = CarbonImmutable::parse('2026-11-15 10:00', 'UTC');
        $nine = LocalTime::parse('9:00AM');

        // 11:00 in Paris: 9:00 has passed, tomorrow 8:00 UTC.
        $this->assertSame('2026-11-16 08:00', $nine->nextIn('Europe/Paris', $from)->format('Y-m-d H:i'));
        // 05:00 in New York: today 14:00 UTC.
        $this->assertSame('2026-11-15 14:00', $nine->nextIn('America/New_York', $from)->format('Y-m-d H:i'));
        // Unknown zone: UTC.
        $this->assertSame('2026-11-16 09:00', $nine->nextIn('Nowhere/City', $from)->format('Y-m-d H:i'));
    }

    public function test_batches_are_delayed_per_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'UTC'));

        $project = $this->project();
        foreach (['Europe/Paris', 'Europe/Paris', 'America/New_York', 'Asia/Tokyo', null] as $i => $zone) {
            $this->device($project, $zone, $i);
        }

        Bus::fake();
        $message = app(CreateMessage::class)->handle($project, [
            'included_segments' => ['Subscribed Users'],
            'contents' => ['en' => 'Good morning'],
            'delayed_option' => 'timezone',
            'delivery_time_of_day' => '9:00AM',
        ]);
        $this->assertSame('09:00', $message->option('delivery_time_of_day'));

        (new DispatchMessage($message->id))->handle(app(Audience::class));

        Bus::assertBatched(function ($batch) {
            $at = collect($batch->jobs)->mapWithKeys(fn (SendBatch $job) => [count($job->subscriptionIds).':'.($job->delay?->utc()->format('Y-m-d H:i') ?? 'now') => true]);

            return $batch->jobs->count() === 4
                && $at->has('2:2026-11-16 08:00')   // Paris
                && $at->has('1:2026-11-15 14:00')   // New York
                && $at->has('1:2026-11-16 00:00')   // Tokyo
                && $at->has('1:2026-11-16 09:00');  // unknown: UTC
        });

        $this->assertTrue($message->fresh()->isCancelable());
    }

    public function test_a_time_is_required(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateMessage::class)->handle($this->project(), [
            'included_segments' => ['Subscribed Users'],
            'contents' => ['en' => 'Hi'],
            'delayed_option' => 'timezone',
        ]);
    }

    public function test_the_composer_sends_at_local_time(): void
    {
        $project = $this->project();
        $this->device($project, 'Europe/Paris', 1);
        Bus::fake();

        $this->actingAs($this->owner)
            ->post(route('projects.messages.store', $project), [
                'texts' => [['language' => 'en', 'body' => 'Hello']],
                'audience' => 'segments',
                'included_segments' => ['Subscribed Users'],
                'schedule' => 'timezone',
                'delivery_time' => '18:30',
            ])->assertRedirect();

        $this->assertSame('18:30', $project->messages()->first()->option('delivery_time_of_day'));
    }

    protected ?User $owner = null;

    protected function project(): Project
    {
        $user = $this->owner = User::factory()->create();
        $project = Project::create(['name' => 'App', 'created_by' => $user->id]);
        $project->addMember($user, ProjectRole::Owner);
        PushCredential::create(['project_id' => $project->id, 'provider' => 'apns', 'details' => ['key_id' => 'ABCDEFGHIJ', 'team_id' => 'ABCDEFGHIJ', 'bundle_id' => 'net.test.app'], 'secret' => 'key']);

        return $project;
    }

    protected function device(Project $project, ?string $timezone, int $i): Subscription
    {
        $subscriber = Subscriber::create(['project_id' => $project->id, 'timezone' => $timezone]);

        return Subscription::create([
            'project_id' => $project->id,
            'subscriber_id' => $subscriber->id,
            'type' => 'ios',
            'token' => str_repeat((string) $i, 64),
            'enabled' => true,
        ]);
    }
}
