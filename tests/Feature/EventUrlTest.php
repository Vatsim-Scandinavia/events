<?php

namespace Tests\Feature;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_name_the_event_by_id_and_slug(): void
    {
        $slug = fn (string $name) => Event::factory()->create(['name' => $name])->getRouteKey();

        $this->assertMatchesRegularExpression('/^\d+-copenhagen-fly-in$/', $slug('Copenhagen Fly-in'));
        $this->assertMatchesRegularExpression('/^\d+-aero-oresund-alborg-night$/', $slug('Ærø & Øresund: Ålborg Night!'));
        // A name a URL can't carry leaves just the ID.
        $this->assertMatchesRegularExpression('/^\d+$/', $slug('日本語'));

        // Long names are cut at a word break.
        $long = $slug('Cross the Pond Eastbound 2026 with full coverage on both sides of the Atlantic Ocean');
        $this->assertSame('cross-the-pond-eastbound-2026-with-full-coverage-on-both', preg_replace('/^\d+-/', '', $long));
    }

    public function test_old_and_plain_links_move_permanently_to_the_current_one(): void
    {
        $event = Event::factory()->create(['name' => 'Copenhagen Fly-in']);
        $current = route('events.show', $event);

        $this->assertSame(url("/events/{$event->id}-copenhagen-fly-in"), $current);
        $this->get("/events/{$event->id}")->assertStatus(301)->assertRedirect($current);
        $this->get("/events/{$event->id}-an-old-name")->assertStatus(301)->assertRedirect($current);
        $this->get($current)->assertOk();
    }

    public function test_a_name_given_back_after_a_rename_never_traps_a_browser_in_a_loop(): void
    {
        $event = Event::factory()->create(['name' => 'Copenhagen Fly-in']);
        $first = route('events.show', $event);

        $event->update(['name' => 'Copenhagen Fly-in Typo']);
        $second = route('events.show', $event);
        $this->get($first)->assertStatus(301)->assertRedirect($second)->assertHeader('Cache-Control', 'no-store, private');

        // Back to the first name: a browser that kept the redirect above would now go round in a circle.
        $event->update(['name' => 'Copenhagen Fly-in']);
        $this->get($second)->assertStatus(301)->assertRedirect($first)->assertHeader('Cache-Control', 'no-store, private');
        $this->get($first)->assertOk();
    }

    public function test_staff_pages_redirect_too_keeping_the_query(): void
    {
        $event = Event::factory()->create(['name' => 'Copenhagen Fly-in', 'staffing_mode' => StaffingMode::Shifts]);
        $occurrence = $event->occurrences()->sole();
        $this->actingAs(User::factory()->staff($event->fir)->create());

        $this->get("/events/{$event->id}/edit")->assertStatus(301)->assertRedirect("/events/{$event->getRouteKey()}/edit");
        $this->get("/events/{$event->id}/staffing?occurrence={$occurrence->id}")->assertStatus(301)
            ->assertRedirect("/events/{$event->getRouteKey()}/staffing?occurrence={$occurrence->id}");
    }

    public function test_actions_take_any_form_of_the_link_without_redirecting(): void
    {
        $event = Event::factory()->draft()->create();

        $this->actingAs(User::factory()->staff($event->fir)->create())
            ->post("/events/{$event->id}/publish")->assertRedirect(route('events.show', $event));

        $this->assertFalse($event->fresh()->isDraft());
    }

    public function test_a_draft_gives_nothing_away_to_someone_who_cannot_see_it(): void
    {
        $event = Event::factory()->draft()->create(['name' => 'Secret Fly-in']);

        $this->get("/events/{$event->id}")->assertNotFound();
    }

    public function test_the_api_keeps_plain_ids(): void
    {
        $event = Event::factory()->create(['name' => 'Copenhagen Fly-in']);

        $this->getJson("/api/v1/events/{$event->id}")->assertOk()->assertJsonPath('data.url', route('events.show', $event));
        $this->getJson("/api/v1/events/{$event->getRouteKey()}")->assertNotFound();
    }
}
