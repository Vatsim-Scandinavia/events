<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_local_password_login_is_not_available(): void
    {
        $this->post('/login', ['email' => 'controller@example.com', 'password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_authenticated_users_are_redirected_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_users_can_logout_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['private_data' => 'value'])->post(route('logout'))
            ->assertRedirect(route('home'))->assertSessionMissing('private_data');

        $this->assertGuest();
    }
}
