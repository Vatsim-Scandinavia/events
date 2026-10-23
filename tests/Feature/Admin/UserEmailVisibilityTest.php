<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserEmailVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function userManager(): User
    {
        $manager = User::factory()->create(['name' => 'Anna']);
        $manager->assignRole(Role::create(['name' => 'User Manager', 'permissions' => [Permission::ManageUsers->value]]), Fir::factory()->create());

        return $manager;
    }

    public function test_only_administrators_are_sent_email_addresses(): void
    {
        $kim = User::factory()->create(['name' => 'Kim', 'email' => 'kim@example.org']);
        $manager = $this->userManager();

        $this->actingAs($manager)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('users.data', fn ($users) => collect($users)->every(fn ($user) => ! array_key_exists('email', $user)))
        );
        $this->actingAs($manager)->get("/admin/users/{$kim->id}")
            ->assertDontSee('kim@example.org', false)
            ->assertInertia(fn (Assert $page) => $page->where('user.name', 'Kim')->missing('user.email'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get("/admin/users/{$kim->id}")->assertInertia(fn (Assert $page) => $page->where('user.email', 'kim@example.org'));
        $this->actingAs($admin)->get('/admin/users?search=Kim')->assertInertia(fn (Assert $page) => $page->where('users.data.0.email', 'kim@example.org'));
    }

    public function test_only_administrators_can_search_by_email(): void
    {
        User::factory()->create(['name' => 'Kim', 'email' => 'secret-handle@example.org']);

        $this->actingAs($this->userManager())->get('/admin/users?search=secret-handle')->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
        $this->actingAs(User::factory()->admin()->create())->get('/admin/users?search=secret-handle')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Kim')
        );
    }
}
