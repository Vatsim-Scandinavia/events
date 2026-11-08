<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Services\LinkPreview;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_describes_the_hub(): void
    {
        $siteName = app(VaccIdentity::class)->effective()['name'].' Event Hub';

        $this->get('/events')->assertOk()
            ->assertSee('<meta property="og:site_name" content="'.e($siteName).'">', false)
            ->assertSee('<meta property="og:title" content="'.e($siteName).'">', false)
            ->assertSee('<meta name="description" content="'.e(LinkPreview::TAGLINE).'">', false)
            ->assertSee('<meta property="og:url" content="'.url('/events').'">', false)
            ->assertDontSee('og:image', false);
    }

    public function test_an_event_shows_its_summary_and_banner(): void
    {
        $event = Event::factory()->create([
            'name' => 'Copenhagen "Calling" & Co',
            'summary' => 'Tower and approach at Kastrup.',
            'banner_path' => 'banners/calling.webp',
        ]);

        $this->get("/events/{$event->getRouteKey()}")->assertOk()
            ->assertSee('<meta property="og:title" content="Copenhagen &quot;Calling&quot; &amp; Co">', false)
            ->assertSee('<meta property="og:description" content="Tower and approach at Kastrup.">', false)
            ->assertSee('<meta property="og:url" content="'.route('events.show', $event).'">', false)
            ->assertSee('content="'.Storage::disk('public')->url('banners/calling.webp').'"', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_without_a_summary_it_says_when_and_where_and_a_cancelled_event_says_so(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $event = Event::factory()->create([
            'name' => 'Cross the Pond',
            'summary' => null,
            'banner_path' => null,
            'airports' => ['KJFK', 'EGLL'],
            'starts_at' => Carbon::parse('2026-11-17 10:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-17 22:00', 'UTC'),
            'cancelled_at' => now(),
        ]);

        $this->get("/events/{$event->getRouteKey()}")->assertOk()
            ->assertSee('<meta property="og:title" content="Cancelled: Cross the Pond">', false)
            ->assertSee('<meta name="description" content="Tue 17 Nov 2026 · 10:00–22:00z · KJFK, EGLL">', false)
            ->assertDontSee('og:image', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    public function test_overnight_dates_say_when_they_end_and_an_event_with_only_cancelled_dates_left_says_so(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $event = Event::factory()->create([
            'name' => 'Night Owls',
            'summary' => null,
            'airports' => ['EKCH'],
            'starts_at' => Carbon::parse('2026-11-17 22:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-18 02:00', 'UTC'),
        ]);
        $event->occurrences()->update(['cancelled_at' => now()]);

        $this->get("/events/{$event->getRouteKey()}")->assertOk()
            ->assertSee('<meta property="og:title" content="Cancelled: Night Owls">', false)
            ->assertSee('<meta name="description" content="Tue 17 Nov 2026 · 22:00z–Wed 18 Nov 02:00z · EKCH">', false);
    }

    public function test_an_svg_logo_is_not_offered_as_the_preview_image(): void
    {
        app(VaccIdentity::class)->saveLogo(['logo' => 'logos/logo.svg', 'icon' => 'logos/logo.svg', 'touch' => null, 'type' => 'image/svg+xml']);
        $event = Event::factory()->create(['banner_path' => null]);

        $this->get("/events/{$event->getRouteKey()}")->assertOk()->assertDontSee('og:image', false);

        app(VaccIdentity::class)->saveLogo(['logo' => 'logos/logo.png', 'icon' => 'logos/icon.png', 'touch' => 'logos/touch.png', 'type' => 'image/png']);

        $this->get("/events/{$event->getRouteKey()}")->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('logos/logo.png').'">', false);
    }

    public function test_a_draft_reveals_nothing_to_someone_who_cannot_see_it(): void
    {
        $event = Event::factory()->draft()->create(['name' => 'Secret Fly-in', 'summary' => 'Not announced yet.']);

        $this->get("/events/{$event->id}")->assertNotFound()
            ->assertDontSee('Secret Fly-in')
            ->assertDontSee('Not announced yet.');
    }
}
