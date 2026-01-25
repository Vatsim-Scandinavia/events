<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'moderator']);
        Role::create(['name' => 'user']);
        
        Http::fake([
            '*/webhooks/*' => Http::response(['success' => true], 200),
            '*/api/bookings/*' => Http::response(['booking' => ['id' => 12345]], 200),
        ]);
        
        Queue::fake();
    }

    protected function createAuthenticatedUser(string $role = 'user')
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_user_can_view_single_event()
    {
        $user = $this->createAuthenticatedUser('user');
        
        $calendar = Calendar::factory()->create(['is_public' => true]);
        
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'title' => 'Single Training Session',
            'recurrence_rule' => null,
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event', fn (Assert $page) => $page
                    ->where('id', $event->id)
                    ->where('title', 'Single Training Session')
                    ->etc()
                )
            );
    }

    public function test_user_can_view_recurring_event_with_staffing_and_instances()
    {
        $user = $this->createAuthenticatedUser('user');
        $calendar = Calendar::factory()->create(['is_public' => true]);
        
        $event = Event::factory()->create([
            'calendar_id' => $calendar->id,
            'title' => 'Weekly Fly-In',
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=SU',
            'start_datetime' => now()->addDay(),
        ]);

        $staffing = $event->staffings()->create(['name' => 'Enroute']);
        $staffing->positions()->create([
            'position_id' => 'ESAA_CTR',
            'position_name' => 'Sweden Control'
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event', fn (Assert $page) => $page
                    ->where('id', $event->id)
                    ->has('staffings', 1)
                    ->has('instances')
                    ->etc()
                )
            );
    }

    public function test_moderator_can_create_event()
    {
        $user = $this->createAuthenticatedUser('moderator');
        $calendar = Calendar::factory()->create();

        $response = $this->actingAs($user)->post('/events', [
            'calendar_id' => $calendar->id,
            'title' => 'Moderator Event',
            'short_description' => 'Test description',
            'long_description' => 'Detailed description',
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Moderator Event']);
    }

    public function test_regular_user_cannot_create_event()
    {
        $user = $this->createAuthenticatedUser('user');
        $calendar = Calendar::factory()->create();

        $this->actingAs($user)
            ->post('/events', [
                'calendar_id' => $calendar->id,
                'title' => 'Unauthorized Event',
            ])
            ->assertStatus(403);
    }

    public function test_moderator_can_delete_event()
    {
        $user = $this->createAuthenticatedUser('moderator');
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create(['calendar_id' => $calendar->id]);

        $response = $this->actingAs($user)->delete(route('events.destroy', $event));

        $response->assertRedirect('/events');
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_regular_user_cannot_delete_event()
    {
        $user = $this->createAuthenticatedUser('user');
        $calendar = Calendar::factory()->create();
        $event = Event::factory()->create(['calendar_id' => $calendar->id]);

        $this->actingAs($user)
            ->delete(route('events.destroy', $event))
            ->assertStatus(403);
    }

    public function test_event_with_banner_upload()
    {
        Storage::fake('public');
        $user = $this->createAuthenticatedUser('moderator');
        $calendar = Calendar::factory()->create();

        $file = UploadedFile::fake()->image('banner.jpg', 1920, 1080);

        $response = $this->actingAs($user)->post('/events', [
            'calendar_id' => $calendar->id,
            'title' => 'Banner Event',
            'short_description' => 'Desc',
            'long_description' => 'Long Desc',
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'banner' => $file,
        ]);

        $response->assertRedirect();
        $event = Event::where('title', 'Banner Event')->first();
        $this->assertNotNull($event->banner_path);
    }
}