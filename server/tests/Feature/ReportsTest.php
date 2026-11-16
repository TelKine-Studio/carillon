<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\CustomEvent;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\WeeklyReport;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_report_and_funnels(): void
    {
        $owner = User::factory()->create(['name' => 'Ada']);
        $quiet = User::factory()->create(['weekly_report' => false]);
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);
        $project->addMember($quiet, ProjectRole::Owner);

        $message = Message::create(['project_id' => $project->id, 'contents' => ['en' => 'Soldes'], 'name' => 'Soldes', 'targeting' => [], 'status' => 'sent', 'recipients' => 100, 'successful' => 90, 'received' => 70, 'clicked' => 18]);
        $subscriber = Subscriber::create(['project_id' => $project->id]);
        CustomEvent::query()->create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'message_id' => $message->id, 'attribution' => 'direct', 'name' => 'purchase', 'value' => 5000, 'occurred_at' => now()]);

        Notification::fake();
        $this->artisan('carillon:weekly-reports')->assertSuccessful();
        Notification::assertSentTo($owner, WeeklyReport::class, function (WeeklyReport $report) use ($owner) {
            $mail = $report->toMail($owner);
            $text = implode("\n", $mail->introLines);

            return str_contains($text, '**Shop**') && str_contains($text, '90 notifications delivered') && str_contains($text, '18 clicks (rate 20,0 %)')
                && str_contains($text, '1 conversions after a message') && str_contains($text, 'Soldes');
        });
        Notification::assertNotSentTo($quiet, WeeklyReport::class);

        $this->actingAs($owner)->get(route('projects.messages.show', [$project, $message]))->assertOk()->assertSee('Converted')->assertSee('20,0 %', false);
        $this->get(route('projects.stats', $project))->assertOk()->assertSee('From the notification to the conversion');

        // The preference lives in the profile.
        $this->put(route('profile.update'), ['name' => 'Ada', 'email' => $owner->email])->assertRedirect();
        $this->assertFalse($owner->fresh()->weekly_report);
    }

    public function test_system_health_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'system'))->assertOk()->assertSee('Never seen')->assertSee('Needs attention');

        Cache::put(SystemHealth::SCHEDULER_KEY, now()->getTimestamp());
        $health = SystemHealth::check();
        $this->assertTrue($health['checks']['scheduler']['ok']);
        $this->assertTrue($health['checks']['database']['ok']);

        $this->actingAs(User::factory()->create())->get(route('admin.settings.edit', 'system'))->assertForbidden();
    }
}
