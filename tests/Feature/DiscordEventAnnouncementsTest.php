<?php

namespace Tests\Feature;

use App\Enums\RepeatInterval;
use App\Integrations\Discord\Discord;
use App\Integrations\Integrations;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DiscordEventAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123/secret';

    private Fir $copenhagen;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        config(['integrations.settings.discord' => []]);
        $this->switchOn();
        Http::preventStrayRequests();

        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $this->staff = User::factory()->staff($this->copenhagen)->create();
    }

    /**
     * @param  list<string>  $features
     */
    private function switchOn(bool $enabled = true, array $features = ['announcements']): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => 'discord'], [
            'enabled' => $enabled,
            'features' => $features,
            'settings' => ['events_webhook' => self::WEBHOOK],
        ]);
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

    public function test_nothing_is_posted_while_announcements_or_discord_are_off(): void
    {
        Http::fake();
        $this->switchOn(features: []);
        $featureOff = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$featureOff->id}/publish")->assertSessionHasNoErrors();

        $this->switchOn(enabled: false);
        $integrationOff = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$integrationOff->id}/publish")->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertFalse($featureOff->fresh()->isDraft());
        $this->assertFalse($integrationOff->fresh()->isDraft());
    }

    public function test_the_webhook_set_in_the_environment_is_used(): void
    {
        $locked = 'https://discord.com/api/webhooks/456/from-env';
        config(['integrations.settings.discord' => ['events_webhook' => $locked]]);
        Http::fake([$locked => Http::response(null, 204)]);
        $event = $this->weeklyDraft();

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish");

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === $locked);
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

    public function test_only_publishing_posts_not_create_edit_cancel_or_delete(): void
    {
        Http::fake([self::WEBHOOK => Http::response(null, 204)]);
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

        // Only the announcement from publishing it.
        Http::assertSentCount(1);
    }

    public function test_an_admin_can_switch_announcements_on_once_discord_knows_the_webhook(): void
    {
        IntegrationSetting::query()->delete();
        $admin = User::factory()->admin()->create();
        $webhook = 'https://discord.com/api/webhooks/789/new-token';
        Http::fake([$webhook => Http::response(['id' => '789', 'channel_id' => '1001'])]);

        $this->actingAs($admin)->put('/admin/integrations/discord', [
            'enabled' => true,
            'features' => ['announcements'],
            'settings' => ['events_webhook' => $webhook],
        ])->assertSessionHasNoErrors();

        $integrations = app(Integrations::class);
        $discord = app(Discord::class);
        $this->assertTrue($integrations->isOn($discord, $discord->feature('announcements')));
        $this->assertSame($webhook, $integrations->configuration($discord)->get('events_webhook'));
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === $webhook);
    }

    public function test_announcements_stay_off_without_a_webhook_discord_knows(): void
    {
        IntegrationSetting::query()->delete();
        $admin = User::factory()->admin()->create();
        $deleted = 'https://discord.com/api/webhooks/789/deleted';
        Http::fake([$deleted => Http::response(['message' => 'Unknown Webhook'], 404)]);
        $payload = ['enabled' => true, 'features' => ['announcements']];

        $this->actingAs($admin)->put('/admin/integrations/discord', [...$payload, 'settings' => ['events_webhook' => '']])
            ->assertSessionHasErrors(['enabled' => 'Couldn’t connect, so it stays off: Enter the events channel webhook first.']);
        $this->actingAs($admin)->put('/admin/integrations/discord', [...$payload, 'settings' => ['events_webhook' => 'https://example.com/hook']])
            ->assertSessionHasErrors('settings.events_webhook');
        $this->actingAs($admin)->put('/admin/integrations/discord', [...$payload, 'settings' => ['events_webhook' => $deleted]])
            ->assertSessionHasErrors(['enabled' => 'Couldn’t connect, so it stays off: Discord doesn’t know the events channel webhook. It may have been deleted; copy a new one.']);

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_upgrading_switches_announcements_on_where_the_webhook_was_set_in_the_environment(): void
    {
        $migration = require database_path('migrations/2026_10_30_184405_switch_on_discord_announcements_where_the_webhook_is_set.php');
        IntegrationSetting::query()->delete();

        $migration->up();
        $this->assertDatabaseCount('integration_settings', 0);

        config(['integrations.settings.discord' => ['events_webhook' => self::WEBHOOK]]);
        $migration->up();

        $discord = app(Discord::class);
        $this->assertTrue(app(Integrations::class)->isOn($discord, $discord->feature('announcements')));
    }
}
