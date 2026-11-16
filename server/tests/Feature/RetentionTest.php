<?php

namespace Tests\Feature;

use App\Audience\Retention;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_cohorts_count_people_back_after_their_first_week(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00'));
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $owner->id]);
        $project->addMember($owner, ProjectRole::Owner);

        // Two people first seen two weeks ago; one of them comes back the week after.
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00'));
        $back = Subscriber::create(['project_id' => $project->id, 'last_active_at' => now()]);
        Subscriber::create(['project_id' => $project->id, 'last_active_at' => now()]);
        $this->assertSame(2, DB::table('activity_days')->count());

        $this->travelTo(CarbonImmutable::parse('2026-11-11 09:00'));
        $back->update(['last_active_at' => now()]);
        $back->update(['last_active_at' => now()->addHour()]); // same day: counted once
        $this->assertSame(3, DB::table('activity_days')->count());

        $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00'));
        $rows = collect(Retention::weekly($project));
        $cohort = $rows->first(fn ($row) => $row['start']->toDateString() === '2026-11-02');

        $this->assertSame(2, $cohort['size']);
        $this->assertEquals([100, 50, 0], array_slice($cohort['weeks'], 0, 3));
        $this->assertNull($cohort['weeks'][3]);

        $this->actingAs($owner)->get(route('projects.stats', $project))->assertOk()->assertSee('Retention by cohort');
    }
}
