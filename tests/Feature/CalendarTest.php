<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Test that a user with the appropriate permissions can view the calendar index page.
     */
    public function test_user_with_permissions_can_view_calendar_index()
    {
        $this->seed();

        // Create a user and assign the 'administrator' role
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Act as the user and attempt to view the calendar index page
        $response = $this->actingAs($user)->get(route('calendars.index'));

        // Assert that the user can access the page and sees the list of calendars
        $response->assertStatus(200);
    }

    /**
     * Test that a user without the appropriate permissions cannot view the calendar index page.
     */
    public function test_user_without_permissions_cannot_view_calendar_index()
    {
        $this->seed();

        // Create a user without any roles or permissions
        $user = User::factory()->create();
        $user->assignRole('user');

        // Act as the user and attempt to view the calendar index page
        $response = $this->actingAs($user)->get(route('calendars.index'));

        // Assert that the user receives a 403 Forbidden response
        $response->assertStatus(403);
    }

    /**
     * Test that a user with the appropriate permissions can create a calendar.
     */
    public function test_user_with_permissions_can_create_calendar()
    {
        $this->seed();

        // Create a user and assign the 'administrator' role
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Act as the user and attempt to create a calendar
        $response = $this->actingAs($user)->post(route('calendars.store'), [
            'title' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'visibility' => 'public',
        ]);

        // Assert that the calendar was created and the user is redirected to the calendar's page
        $response->assertRedirect();
        $this->assertDatabaseHas('calendars', [
            'title' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'visibility' => 'public',
            'created_by' => $user->id,
        ]);
    }

    /**
     * Test that a user without the appropriate permissions cannot create a calendar.
     */
    public function test_user_without_permissions_cannot_create_calendar()
    {
        $this->seed();

        // Create a user without any roles or permissions
        $user = User::factory()->create();
        $user->assignRole('user');

        // Act as the user and attempt to create a calendar
        $response = $this->actingAs($user)->post(route('calendars.store'), [
            'title' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'visibility' => 'public',
        ]);

        // Assert that the user receives a 403 Forbidden response
        $response->assertStatus(403);
        $this->assertDatabaseMissing('calendars', [
            'title' => 'Test Calendar',
            'description' => 'This is a test calendar.',
            'visibility' => 'public',
            'created_by' => $user->id,
        ]);
    }

    /**
     * Test that a user with the appropriate permissions can edit a calendar.
     */
    public function test_user_with_permissions_can_edit_calendar()
    {
        $this->seed();

        // Create a user and assign the 'administrator' role
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Create a calendar
        $calendar = Calendar::factory()->create([
            'created_by' => $user->id,
        ]);

        // Act as the user and attempt to edit the calendar
        $response = $this->actingAs($user)->put(route('calendars.update', $calendar), [
            'title' => 'Updated Calendar Title',
            'description' => 'Updated description.',
            'visibility' => 'private',
        ]);

        // Assert that the calendar was updated and the user is redirected to the calendar's page
        $response->assertRedirect();
        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'title' => 'Updated Calendar Title',
            'description' => 'Updated description.',
            'visibility' => 'private',
            'created_by' => $user->id,
        ]);
    }

    /**
     * Test that a user without the appropriate permissions cannot edit a calendar.
     */
    public function test_user_without_permissions_cannot_edit_calendar()
    {
        $this->seed();

        // Create a user without any roles or permissions
        $user = User::factory()->create();
        $user->assignRole('user');

        // Create a calendar
        $calendar = Calendar::factory()->create();

        // Act as the user and attempt to edit the calendar
        $response = $this->actingAs($user)->put(route('calendars.update', $calendar), [
            'title' => 'Updated Calendar Title',
            'description' => 'Updated description.',
            'visibility' => 'private',
        ]);

        // Assert that the user receives a 403 Forbidden response
        $response->assertStatus(403);
        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'title' => $calendar->title,
            'description' => $calendar->description,
            'visibility' => $calendar->visibility,
            'created_by' => $calendar->created_by,
        ]);
    }

    /**
     * Test that a user with the appropriate permissions can delete a calendar.
     */
    public function test_user_with_permissions_can_delete_calendar()
    {
        $this->seed();

        // Create a user and assign the 'administrator' role
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Create a calendar
        $calendar = Calendar::factory()->create([
            'created_by' => $user->id,
        ]);

        // Act as the user and attempt to delete the calendar
        $response = $this->actingAs($user)->delete(route('calendars.destroy', $calendar));

        // Assert that the calendar was deleted and the user is redirected to the calendars index page
        $response->assertRedirect(route('calendars.index'));
        $this->assertDatabaseMissing('calendars', [
            'id' => $calendar->id,
        ]);
    }

    /**
     * Test that a user without the appropriate permissions cannot delete a calendar.
     */
    public function test_user_without_permissions_cannot_delete_calendar()
    {
        $this->seed();

        // Create a user without any roles or permissions
        $user = User::factory()->create();
        $user->assignRole('user');

        // Create a calendar
        $calendar = Calendar::factory()->create();

        // Act as the user and attempt to delete the calendar
        $response = $this->actingAs($user)->delete(route('calendars.destroy', $calendar));

        // Assert that the user receives a 403 Forbidden response
        $response->assertStatus(403);
        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'title' => $calendar->title,
            'description' => $calendar->description,
            'visibility' => $calendar->visibility,
            'created_by' => $calendar->created_by,
        ]);
    }
}