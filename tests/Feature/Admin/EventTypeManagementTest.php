<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_people_who_manage_settings_can_open_the_event_type_pages(): void
    {
        $flyIn = EventType::query()->where('name', 'Fly-in')->sole();
        Event::factory()->count(2)->for($flyIn)->create();

        $this->get('/admin/event-types')->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/event-types')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->post('/admin/event-types', ['name' => 'Tour'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/event-types')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EventTypes/Index')
            ->where('types', fn ($types) => collect($types)->contains(['id' => $flyIn->id, 'name' => 'Fly-in', 'events' => 2]))
        );
    }

    public function test_an_admin_can_add_and_rename_a_type_with_a_unique_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/event-types', ['name' => ' Tour '])->assertRedirect('/admin/event-types');
        $tour = EventType::query()->where('name', 'Tour')->sole();

        $this->actingAs($admin)->post('/admin/event-types', ['name' => 'Fly-in'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put("/admin/event-types/{$tour->id}", ['name' => 'Tour'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/admin/event-types/{$tour->id}", ['name' => 'Airline tour'])->assertRedirect('/admin/event-types');
        $this->assertSame('Airline tour', $tour->fresh()->name);
    }

    public function test_events_of_a_deleted_type_show_as_general(): void
    {
        $type = EventType::factory()->create(['name' => 'Tour']);
        $event = Event::factory()->for($type)->create();

        $this->actingAs(User::factory()->admin()->create())->delete("/admin/event-types/{$type->id}")->assertRedirect('/admin/event-types');

        $this->assertModelMissing($type);
        $this->assertNull($event->fresh()->event_type_id);
        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.type', 'General'));
    }
}
