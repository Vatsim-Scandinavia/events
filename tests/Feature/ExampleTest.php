<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_the_shared_home_page(): void
    {
        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('welcome')->where('auth.user', null));
    }

    public function test_signed_in_users_can_visit_the_same_home_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('welcome')->where('auth.user.id', $user->id));
    }

    public function test_signed_in_users_are_redirected_home_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('home'));
    }
}
