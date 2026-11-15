<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_pages_follow_the_language_of_the_visitor(): void
    {
        // A signed-in user: their language.
        $user = User::factory()->create(['locale' => 'fr']);
        $this->actingAs($user)->get('/projects/missing')->assertNotFound()->assertSee('Page introuvable');
        auth()->logout();

        // Without session, the language of the browser.
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/projects/a/b/c/d')->assertSee('Page introuvable');
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/projects/a/b/c/d')->assertSee('Page not found');
    }

    public function test_errors_use_the_carillon_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects/does-not-exist')
            ->assertNotFound()
            ->assertSee(__('Page not found'))
            ->assertSee(__('Back to home'));

        $this->get('/m/backups/extra/segments')->assertNotFound()->assertSee(__('Error :code', ['code' => '404']));

        // The API keeps answering in JSON.
        $this->get('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/projects/demo/api-keys')->assertStatus(405)->assertSee(__('This address does not open in a browser'));
    }
}
