<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that a user with the appropiate permissions can view the event index page.
     */
    public function test_user_with_permissions_can_view_event_index()
    {
        $this->seed();

        // Create a user and assign the 'user' role
        $user = User::factory()->create();
        $user->assignRole('user');

        // Act as the user and attempt to view the event index page
        $response = $this->actingAs($user)->get(route('events.index'));

        // Assert that the user can access the page and sees the list of events
        $response->assertStatus(200);
    }

    /**
     * Test that a guest can view a published event.
     */
    public function test_guest_can_view_published_event()
    {
        $this->seed();

        // Create a published event
        $event = Event::factory()->create(['status' => 'published']);

        // Attempt to view the published event as a guest
        $response = $this->get(route('events.show', $event));

        // Assert that the guest can access the page and sees the event details
        $response->assertStatus(200);
    }

    /**
     * Test that a user with appropiate permissions can view a draft event.
     */
    public function test_user_with_permissions_can_view_draft_event()
    {
        $this->seed();

        // Create a user and assign the 'administrator' role
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Create a draft event
        $event = Event::factory()->create(['status' => 'draft']);

        // Act as the user and attempt to view the draft event
        $response = $this->actingAs($user)->get(route('events.show', $event));

        // Assert that the user can access the page and sees the event details
        $response->assertStatus(200);
    }

    /**
     * Test that a user without appropiate permissions cannot view a draft event.
     */
    public function test_user_without_permissions_cannot_view_draft_event()
    {
        $this->seed();

        // Create a user and assign the 'user' role
        $user = User::factory()->create();
        $user->assignRole('user');

        // Create a draft event
        $event = Event::factory()->create(['status' => 'draft']);

        // Act as the user and attempt to view the draft event
        $response = $this->actingAs($user)->get(route('events.show', $event));

        // Assert that the user receives a 403 Forbidden response
        $response->assertStatus(403);
    }

    
}
