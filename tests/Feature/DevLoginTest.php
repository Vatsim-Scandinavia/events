<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DevLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_login_is_off_unless_enabled(): void
    {
        config(['auth.dev_login' => false]);
        $user = User::factory()->create();

        $this->get('/login')->assertNotFound();
        $this->post("/login/{$user->id}")->assertNotFound();
        $this->assertGuest();
    }

    public function test_dev_login_is_never_available_in_production(): void
    {
        config(['auth.dev_login' => true]);
        $this->app->detectEnvironment(fn () => 'production');
        $admin = User::factory()->create();

        $this->get('/login')->assertNotFound();
        // CSRF checks only skip themselves in the testing environment, so send a valid token.
        $this->withSession(['_token' => 'token'])->post("/login/{$admin->id}", ['_token' => 'token'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_when_enabled_you_can_sign_in_as_any_user(): void
    {
        config(['auth.dev_login' => true]);
        $staff = User::factory()->staff()->create(['name' => 'Staff Member']);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/DevLogin')
            ->where('users.0.name', 'Staff Member')
            ->where('users.0.roles', ['Event Staff'])
        );

        $this->post("/login/{$staff->id}")->assertRedirect('/');
        $this->assertAuthenticatedAs($staff);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.user.roles', ['Event Staff'])->where('auth.user.can.manage-staffing', true)->where('auth.user.can.manage-users', false));

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
