<?php

namespace Tests\Feature\Models;

use App\Models\Event;
use App\Models\Staffing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StaffingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Staffing page can be rendered.
     */
    public function test_staffing_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the staffing page
        $response = $this->actingAs($user)->get(route('staffings.index'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * Staffing page cannot be accessed without correct permissions.
     */
    public function test_staffing_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the staffing page
        $response = $this->actingAs($user)->get(route('staffings.index'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * Staffing create page can be rendered.
     */
    public function test_staffing_create_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Render the form
        $response = $this->actingAs($user)->get(route('staffings.create'));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * Staffing create page cannot be accessed without correct permissions.
     */
    public function test_staffing_create_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Render the form
        $response = $this->actingAs($user)->get(route('staffings.create'));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * Staffing edit page can be rendered.
     */
    public function test_staffing_edit_page_can_be_rendered(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a staffing record
        $staffing = Staffing::factory()->create();

        // Render the form
        $response = $this->actingAs($user)->get(route('staffings.edit', $staffing));

        // Check status code
        $response->assertStatus(200);
    }

    /**
     * Staffing edit page cannot be accessed without correct permissions.
     */
    public function test_staffing_edit_page_cannot_be_rendered_without_correct_permissions(): void
    {
        // Setup user without permissions
        $user = User::factory()->create();

        // Create a staffing record
        $staffing = Staffing::factory()->create();

        // Render the form
        $response = $this->actingAs($user)->get(route('staffings.edit', $staffing));

        // Check status code
        $response->assertStatus(403);
    }

    /**
     * Test the deletion of a staffing record.
     */
    public function test_staffing_can_be_deleted(): void
    {
        // Setup user with permissions
        $user = $this->getUser();

        // Create a staffing record
        $staffing = Staffing::factory()->create();

        // Submit the form
        $response = $this->actingAs($user)->delete(route('staffings.destroy', $staffing));

        $response->assertRedirect(route('staffings.index')); // Check if redirected to the index page
        $response->assertSessionHas('success', 'Staffing deleted successfully.'); // Check if success message is set

        // Check if the record was deleted
        $this->assertNull(Staffing::find($staffing->id));
    }

    protected function getUser()
    {
        $user = User::factory()->create();
        $user->groups()->attach(1);

        return $user;
    }

    protected function ranNumbers()
    {
        $random_17_digits = mt_rand(1e16, 1e17 - 1);
        $random_two_digits = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);

        $random_19_digits = $random_17_digits.$random_two_digits;

        // If you need to use it as an integer, you can cast it to an integer
        return (int) $random_19_digits;
    }
}
