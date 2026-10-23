<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_show_the_hubs_own_error_page(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->has('vacc'));

        $this->get('/events/999999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_pages_you_may_not_see_show_the_error_page_with_your_session(): void
    {
        $controller = User::factory()->controller()->create(['name' => 'Tower Controller']);

        $this->actingAs($controller)
            ->get('/events/create')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403)->where('auth.user.name', 'Tower Controller'));
    }

    public function test_json_clients_still_get_json(): void
    {
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }
}
