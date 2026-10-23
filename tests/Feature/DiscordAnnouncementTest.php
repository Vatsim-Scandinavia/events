<?php

namespace Tests\Feature;

use App\Enums\RepeatInterval;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DiscordAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123/secret';

    private Fir $copenhagen;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        config(['services.discord.events_webhook' => self::WEBHOOK]);
        Http::preventStrayRequests();

        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $this->staff = User::factory()->staff($this->copenhagen)->create();
    }

    private function weeklyDraft(): Event
    {
        return Event::factory()->draft()->for($this->copenhagen)->create([
            'name' => 'Copenhagen Tuesday @everyone',
            'starts_at' => Carbon::parse('2026-11-10 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 21:00', 'UTC'),
            'repeats' => RepeatInterval::Weekly,
            'repeat_count' => 3,
        ]);
    }

    public function test_publishing_posts_the_event_once(): void
    {
        Http::fake([self::WEBHOOK => Http::response(null, 204)]);
        $event = $this->weeklyDraft();
        $event->occurrences()->createMany([
            ['starts_at' => '2026-11-17 18:00', 'ends_at' => '2026-11-17 21:00'],
            ['starts_at' => '2026-11-24 18:00', 'ends_at' => '2026-11-24 21:00', 'cancelled_at' => now()],
        ]);

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish")->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post("/events/{$event->id}/publish");

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($event) {
            $embed = $request['embeds'][0];
            $fields = collect($embed['fields'])->pluck('value', 'name');

            return $request->url() === self::WEBHOOK
                && $request['allowed_mentions'] === ['parse' => []]
                && $embed['title'] === 'Copenhagen Tuesday @everyone'
                && $embed['url'] === url("/events/{$event->id}")
                && $fields['FIR'] === 'Copenhagen (EKDK)'
                // The cancelled date is left out.
                && $fields['Dates (UTC)'] === "Tue 10 Nov 2026, 18:00–21:00z\nTue 17 Nov 2026, 18:00–21:00z";
        });
    }

    public function test_the_banner_is_shown_when_the_event_has_one(): void
    {
        Http::fake([self::WEBHOOK => Http::response(null, 204)]);
        $event = $this->weeklyDraft();
        $event->update(['banner_path' => 'banners/tuesday.png']);

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish");

        Http::assertSent(fn (Request $request) => str_ends_with($request['embeds'][0]['image']['url'], 'banners/tuesday.png'));
    }

    public function test_without_a_webhook_nothing_is_posted(): void
    {
        config(['services.discord.events_webhook' => null]);
        Http::fake();
        $event = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish")->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertFalse($event->fresh()->isDraft());
    }

    public function test_a_failing_webhook_never_blocks_publishing(): void
    {
        Log::spy();
        Http::fake([self::WEBHOOK => Http::response('Bad gateway', 502)]);
        $failing = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$failing->id}/publish")
            ->assertRedirect("/events/{$failing->id}")
            ->assertSessionHasNoErrors();
        $this->assertFalse($failing->fresh()->isDraft());

        Http::fake([self::WEBHOOK => fn () => throw new ConnectionException('Timed out')]);
        $timingOut = Event::factory()->draft()->for($this->copenhagen)->create();

        $this->actingAs($this->staff)->post("/events/{$timingOut->id}/publish")->assertSessionHasNoErrors();
        $this->assertFalse($timingOut->fresh()->isDraft());

        Log::shouldHaveReceived('warning')->twice()->withArgs(fn (string $message) => $message === 'Could not announce the event on Discord.');
    }

    public function test_the_webhook_never_appears_in_the_logs(): void
    {
        Log::spy();
        // Connection errors name the URL they were calling, which here is the secret.
        Http::fake([self::WEBHOOK => fn () => throw new ConnectionException('cURL error 28: Operation timed out for '.self::WEBHOOK)]);
        $event = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish");

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), 'secret')
            && str_contains($context['exception'], 'Operation timed out for [webhook]'));
    }

    public function test_an_event_running_past_midnight_shows_the_day_it_ends(): void
    {
        Http::fake([self::WEBHOOK => Http::response(null, 204)]);
        $event = Event::factory()->draft()->for($this->copenhagen)->create([
            'starts_at' => Carbon::parse('2026-11-10 22:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-11 02:00', 'UTC'),
        ]);

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish");

        Http::assertSent(fn (Request $request) => collect($request['embeds'][0]['fields'])->pluck('value', 'name')['Dates (UTC)'] === 'Tue 10 Nov 2026, 22:00–Wed 11 Nov 02:00z');
    }

    public function test_nothing_is_posted_on_create_edit_cancel_or_delete(): void
    {
        Http::fake();
        $payload = [
            'fir_id' => $this->copenhagen->id,
            'name' => 'Copenhagen Fly-in',
            'event_type_id' => EventType::factory()->create()->id,
            'description' => 'Full coverage at Kastrup.',
            'airports' => ['EKCH'],
            'starts_at' => '2026-11-10 18:00',
            'ends_at' => '2026-11-10 21:00',
            'repeats' => 'none',
        ];

        $this->actingAs($this->staff)->post('/events', $payload)->assertSessionHasNoErrors();
        $event = Event::query()->sole();
        $event->publish();

        $this->actingAs($this->staff)->put("/events/{$event->id}", [...$payload, 'name' => 'Copenhagen Fly-in II'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post("/events/{$event->id}/cancel")->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->delete("/events/{$event->id}")->assertSessionHasNoErrors();

        Http::assertNothingSent();
    }
}
