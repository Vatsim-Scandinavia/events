<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventDraftTest extends TestCase
{
    use RefreshDatabase;

    private Fir $copenhagen;

    private Fir $sweden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $this->sweden = Fir::factory()->create(['icao' => 'ESAA']);
    }

    public function test_creating_an_event_makes_a_draft(): void
    {
        $this->actingAs(User::factory()->staff($this->copenhagen)->create())->post('/events', [
            'fir_id' => $this->copenhagen->id,
            'name' => 'Copenhagen Fly-in',
            'event_type_id' => EventType::factory()->create()->id,
            'description' => 'Full coverage at Kastrup.',
            'airports' => ['EKCH'],
            'starts_at' => now()->addWeek()->format('Y-m-d 18:00'),
            'ends_at' => now()->addWeek()->format('Y-m-d 21:00'),
            'repeats' => 'none',
        ])->assertSessionHasNoErrors();

        $event = Event::query()->sole();
        $this->assertNull($event->published_at);
        $this->assertTrue($event->isDraft());
    }

    public function test_drafts_are_only_seen_by_staff_who_can_view_or_manage_events_in_their_fir(): void
    {
        $draft = Event::factory()->draft()->for($this->copenhagen)->create(['name' => 'Secret Fly-in']);
        Event::factory()->for($this->copenhagen)->create(['name' => 'Public Fly-in']);

        $cannotSee = [
            'a pilot' => null,
            'a controller' => User::factory()->controller()->create(),
            'vACC Staff in another FIR' => User::factory()->vaccStaff($this->sweden)->create(),
            'Event Staff in another FIR' => User::factory()->staff($this->sweden)->create(),
        ];

        foreach ($cannotSee as $who => $user) {
            if ($user) {
                $this->actingAs($user);
            }

            $this->get('/events')->assertInertia(fn (Assert $page) => $page
                ->where('events', fn ($events) => collect($events)->pluck('name')->all() === ['Public Fly-in'])
            );
            $this->get("/events/{$draft->id}")->assertNotFound();
        }

        $canSee = [
            'vACC Staff in its FIR' => User::factory()->vaccStaff($this->copenhagen)->create(),
            'Event Staff in its FIR' => User::factory()->staff($this->copenhagen)->create(),
            'Event Staff across the hub' => User::factory()->staff()->create(),
            'an Administrator' => User::factory()->admin()->create(),
        ];

        foreach ($canSee as $who => $user) {
            $this->actingAs($user)->get('/events')->assertInertia(fn (Assert $page) => $page
                ->where('events', fn ($events) => collect($events)->pluck('name')->sort()->values()->all() === ['Public Fly-in', 'Secret Fly-in'])
                ->where('events', fn ($events) => collect($events)->firstWhere('name', 'Secret Fly-in')['draft'] === true)
            );
            $this->actingAs($user)->get("/events/{$draft->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('event.draft', true)
            );
        }
    }

    public function test_event_staff_publish_a_draft_to_show_it_to_everyone(): void
    {
        $draft = Event::factory()->draft()->for($this->copenhagen)->create();
        $staff = User::factory()->staff($this->copenhagen)->create();

        $this->actingAs($staff)->get("/events/{$draft->id}")->assertInertia(fn (Assert $page) => $page
            ->where('event.draft', true)
            ->where('canEdit', true)
        );

        $this->travelTo(now()->startOfMinute());
        $this->actingAs($staff)->post("/events/{$draft->id}/publish")->assertRedirect("/events/{$draft->id}");

        $this->assertTrue($draft->fresh()->published_at->equalTo(now()));
        $this->assertFalse($draft->fresh()->isDraft());

        auth()->logout();
        $this->get("/events/{$draft->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('event.draft', false));
    }

    public function test_publishing_again_keeps_when_it_was_first_published(): void
    {
        $event = Event::factory()->for($this->copenhagen)->create(['published_at' => now()->subDay()]);
        $firstPublished = $event->published_at;

        $this->actingAs(User::factory()->staff($this->copenhagen)->create())->post("/events/{$event->id}/publish");

        $this->assertTrue($event->fresh()->published_at->equalTo($firstPublished));
    }

    public function test_only_people_who_manage_the_event_can_publish_it(): void
    {
        $draft = Event::factory()->draft()->for($this->copenhagen)->create();

        $this->post("/events/{$draft->id}/publish")->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->vaccStaff($this->copenhagen)->create())->post("/events/{$draft->id}/publish")->assertForbidden();
        $this->actingAs(User::factory()->staff($this->sweden)->create())->post("/events/{$draft->id}/publish")->assertForbidden();

        $this->assertTrue($draft->fresh()->isDraft());
    }

    public function test_events_from_before_drafts_count_as_published(): void
    {
        $event = Event::factory()->draft()->for($this->copenhagen)->create();
        $migration = require database_path('migrations/2026_10_23_171452_add_published_at_to_events_table.php');

        // Back to before drafts existed, then forward again.
        $migration->down();
        $this->assertFalse(Schema::hasColumn('events', 'published_at'));
        $migration->up();

        $this->assertFalse($event->fresh()->isDraft());
    }
}
