<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Renders every page of the dashboard: none may fail, and none may contain a
 * Blade directive left as text (e.g. @js() in an attribute of a component,
 * which Blade does not compile and which breaks Alpine in the browser).
 */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_without_raw_directives(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::create(['name' => 'Shop', 'created_by' => $admin->id]);
        $project->addMember($admin, ProjectRole::Owner);
        $subscriber = Subscriber::create(['project_id' => $project->id, 'external_id' => 'u1']);
        Subscription::create(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => 'android', 'token' => 't', 'enabled' => true]);
        $message = Message::create(['project_id' => $project->id, 'contents' => ['en' => 'Hi'], 'targeting' => [], 'status' => 'sent']);
        $journey = $project->journeys()->create(['name' => 'J', 'status' => 'draft', 'trigger' => ['type' => 'new_device'], 'steps' => [['type' => 'push', 'body' => 'x']]]);
        $inApp = $project->inAppMessages()->create(['name' => 'I', 'status' => 'draft', 'layout' => 'modal', 'body' => 'x', 'trigger' => ['type' => 'app_open'], 'frequency' => 'once']);
        $bindings = ['project' => $project, 'message' => $message, 'subscriber' => $subscriber, 'journey' => $journey, 'inApp' => $inApp, 'section' => null];

        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || ! in_array('auth', $route->gatherMiddleware(), true)
                || Str::startsWith($uri, ['api/', 'sdk/', 'e/']) || str_contains($uri, 'export') || str_contains($uri, 'logout')) {
                continue;
            }
            $parameters = [];
            foreach ($route->parameterNames() as $name) {
                if (! array_key_exists($name, $bindings)) {
                    continue 2;
                }
                $parameters[$name] = $bindings[$name];
            }

            $response = $this->actingAs($admin)->get(route($route->getName(), array_filter($parameters, fn ($v) => $v !== null)));
            if ($response->headers->get('content-type') && ! str_contains($response->headers->get('content-type'), 'html')) {
                continue;
            }
            $this->assertLessThan(500, $response->status(), $uri.' failed: '.$response->status());
            if ($response->isOk()) {
                $this->assertDoesNotMatchRegularExpression('/@(js|json|lang|class|checked|selected|disabled)\(/', $response->getContent(), $uri.' contains a raw Blade directive');
                $checked++;
            }
        }

        $this->assertGreaterThan(25, $checked);
    }
}
