<?php

namespace Tests\Feature\Models;

use App\Models\Area;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * A users page can be rendered.
     */
    public function test_users_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser(1);

        // Render the users page
        $response = $this->actingAs($user)->get(route('users.index'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * A users page cannot be rendered.
     */
    public function test_users_page_cannot_be_rendered(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the users page
        $response = $this->actingAs($user)->get(route('users.index', $user));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * A users show page can be rendered.
     */
    public function test_users_show_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the users page
        $response = $this->actingAs($user)->get(route('users.show', $user));

        // Check status code
        $response->assertStatus(200);
    }

    protected function getUser($group = null)
    {
        $group == null ? null : $group;
        $area = Area::find(rand(1,5));
        $user = User::factory()->create();
        if ($group) {
            $user->groups()->attach($group, ['area_id' => $area->id]);
        }

        return $user;
    }
}