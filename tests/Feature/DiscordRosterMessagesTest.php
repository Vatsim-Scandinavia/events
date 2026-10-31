<?php

namespace Tests\Feature;

use App\Enums\RepeatInterval;
use App\Integrations\Discord\RosterMessage;
use App\Integrations\Discord\RosterMessages;
use App\Integrations\Discord\UpdateRosterMessage;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\Fir;
use App\Models\IntegrationSetting;
use App\Models\PositionShift;
use App\Models\User;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscordRosterMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123/roster-secret';

    private const OTHER_WEBHOOK = 'https://discord.com/api/webhooks/456/other-secret';

    /**
     * What Discord was asked to do, as "METHOD webhook-id path", with the body sent.
     *
     * @var list<array{0: string, 1: array<string, mixed>}>
     */
    private array $calls = [];

    /** Messages deleted in Discord, by id. */
    private array $deletedInDiscord = [];

    private bool $discordDown = false;

    private int $nextMessageId = 900;

    /** Runs while Discord takes a post, before it answers. */
    private ?Closure $whilePosting = null;

    private Fir $copenhagen;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        config(['integrations.settings.discord' => []]);
        $this->switchOn();
        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $this->staff = User::factory()->staff($this->copenhagen)->create();

        Http::fake(function (Request $request) {
            if (! str_starts_with($request->url(), 'https://discord.com/api/webhooks/')) {
                return Http::response(null, 500);
            }

            $path = str($request->url())->after('/api/webhooks/')->before('?')->explode('/');
            $call = strtoupper($request->method()).' '.$path[0].($path->count() > 2 ? ' '.$path->slice(2)->implode('/') : '');
            $this->calls[] = [$call, $request->data()];

            if ($this->discordDown) {
                return Http::response(['message' => 'Service unavailable'], 503);
            }

            if ($path[1] === 'deleted') {
                return Http::response(['message' => 'Unknown Webhook', 'code' => 10015], 404);
            }

            $messageId = $path[3] ?? null;

            return match (true) {
                $messageId !== null && in_array($messageId, $this->deletedInDiscord, true) => Http::response(['message' => 'Unknown Message', 'code' => 10008], 404),
                $request->method() === 'GET' => Http::response(['id' => $path[0], 'channel_id' => "channel-{$path[0]}", 'name' => 'Event Hub']),
                $request->method() === 'POST' => $this->posted($path[0]),
                $request->method() === 'DELETE' => Http::response(null, 204),
                default => Http::response(['id' => $messageId]),
            };
        });
    }

    public function test_staff_set_the_webhook_on_the_event_and_a_message_shows_the_next_dates_shifts(): void
    {
        [$event] = $this->shiftEvent();

        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('integrationFields.0.key', RosterMessages::FIELD)
            ->where('integrationFields.0.status', null));

        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->assertSame(['GET 123', 'POST 123'], $this->methods());
        $this->assertSame(['parse' => []], $this->calls[1][1]['allowed_mentions']);
        $embed = $this->calls[1][1]['embeds'][0];
        $this->assertSame('Copenhagen Tuesday', $embed['title']);
        $this->assertSame(url("/events/{$event->id}"), $embed['url']);
        $this->assertSame(
            "**Tue 10 Nov 2026, 16:00–20:00z**\n\n**EKCH\\_TWR**\n`16:00–18:00z` *open*\n`18:00–20:00z` *open*\n\n[Book on the Hub](".url("/events/{$event->id}").')',
            $embed['description'],
        );

        $message = RosterMessage::query()->sole();
        $this->assertSame('900', $message->message_id);
        $this->assertSame('channel-123', $message->channel_id);
        $this->assertStringNotContainsString('roster-secret', (string) DB::table('discord_roster_messages')->value('webhook'));

        // The webhook is never sent back; the page shows where it posts instead.
        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('integrationFields.0.value', null)
            ->where('integrationFields.0.status', 'Posts as Event Hub in channel channel-123'));
    }

    public function test_the_webhook_can_be_set_when_creating_the_event(): void
    {
        $this->actingAs($this->staff)->get('/events/create')->assertInertia(fn (Assert $page) => $page->where('integrationFields.0.key', RosterMessages::FIELD));

        $this->actingAs($this->staff)->post('/events', [
            'fir_id' => $this->copenhagen->id,
            'name' => 'Copenhagen Fly-in',
            'airports' => ['EKCH'],
            'routes' => [],
            'starts_at' => '2026-11-10 18:00',
            'ends_at' => '2026-11-10 21:00',
            'timezone' => 'UTC',
            'repeats' => 'none',
            'integration_fields' => [RosterMessages::FIELD => self::WEBHOOK],
        ])->assertSessionHasNoErrors()->assertRedirect();

        // A new event is a Draft, so nothing is posted yet.
        $this->assertSame(['GET 123'], $this->methods());
        $this->assertSame(Event::query()->sole()->id, RosterMessage::query()->sole()->event_id);
    }

    public function test_bookings_edit_the_same_message(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->actingAs(User::factory()->controller()->create(['name' => 'Jane *Doe*', 'vatsim_cid' => 1234567]))
            ->post("/occurrences/{$event->occurrences()->sole()->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();

        $this->assertSame(['GET 123', 'POST 123', 'PATCH 123 messages/900'], $this->methods());
        $this->assertStringContainsString("`16:00–18:00z` Jane \\*Doe\\* (1234567)\n`18:00–20:00z` *open*", $this->calls[2][1]['embeds'][0]['description']);

        // Nothing changed: the sweep leaves it alone.
        app(RosterMessages::class)->sweep();
        $this->assertCount(3, $this->calls);
    }

    public function test_an_interest_event_shows_its_roster_only_once_published(): void
    {
        $event = Event::factory()->withOpenInterest()->for($this->copenhagen)->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $occurrence = $event->occurrences()->sole();
        $tower = $event->positions()->create(['callsign' => 'EKCH_TWR']);
        $kim = User::factory()->controller()->create(['name' => 'Kim Berg', 'vatsim_cid' => 1000001]);
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $plan = fn (bool $publish) => $this->actingAs($this->staff)->put("/events/{$event->id}/staffing/plan", [
            'occurrence_id' => $occurrence->id,
            'positions' => [['key' => $tower->id, 'callsign' => 'EKCH_TWR']],
            'roster' => [['id' => $occurrence->rosterEntries()->value('id'), 'position' => $tower->id, 'user_id' => $kim->id, 'start' => 0, 'end' => 120]],
            'publish' => $publish,
        ])->assertSessionHasNoErrors();

        $plan(false);
        $this->assertStringContainsString('Roster not published yet.', $this->lastDescription());
        $this->assertStringNotContainsString('Kim', $this->lastDescription());

        $plan(true);
        $this->assertStringContainsString("**EKCH\\_TWR**\n`16:00–18:00z` Kim Berg (1000001)", $this->lastDescription());

        $this->actingAs($this->staff)->delete("/occurrences/{$occurrence->id}/roster/publish")->assertSessionHasNoErrors();
        $this->assertStringContainsString('Roster not published yet.', $this->lastDescription());
        $this->assertSame(['POST 123'], array_values(array_filter($this->methods(), fn (string $call) => str_starts_with($call, 'POST'))));
    }

    public function test_the_message_moves_on_once_its_date_ends_and_says_when_none_are_left(): void
    {
        [$event] = $this->shiftEvent();
        $event->update(['repeats' => RepeatInterval::Weekly, 'repeat_count' => 2]);
        $event->occurrences()->create(['starts_at' => '2026-11-17 16:00', 'ends_at' => '2026-11-17 20:00']);
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $this->assertStringStartsWith('**Tue 10 Nov 2026', $this->lastDescription());

        $this->travelTo(Carbon::parse('2026-11-10 20:01', 'UTC'));
        app(RosterMessages::class)->sweep();
        $this->assertSame('PATCH 123 messages/900', $this->lastCall());
        $this->assertStringStartsWith('**Tue 17 Nov 2026', $this->lastDescription());

        $this->travelTo(Carbon::parse('2026-11-17 20:01', 'UTC'));
        app(RosterMessages::class)->sweep();
        $this->assertSame('PATCH 123 messages/900', $this->lastCall());
        $this->assertSame('No upcoming dates.', $this->lastDescription());
    }

    public function test_the_sweep_runs_every_five_minutes_while_it_is_on(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('integrations:discord.roster');
    }

    public function test_cancelling_the_event_says_so_and_deleting_it_deletes_the_message(): void
    {
        [$event] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->actingAs($this->staff)->post("/events/{$event->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('Event cancelled.', $this->lastDescription());

        $this->actingAs($this->staff)->delete("/events/{$event->id}/cancel")->assertSessionHasNoErrors();
        $this->assertStringStartsWith('**Tue 10 Nov 2026', $this->lastDescription());

        $this->actingAs($this->staff)->delete("/events/{$event->id}")->assertSessionHasNoErrors();
        $this->assertSame('DELETE 123 messages/900', $this->lastCall());
        $this->assertSame(0, RosterMessage::query()->count());
    }

    public function test_a_message_deleted_in_discord_is_posted_again(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $this->deletedInDiscord[] = '900';

        $shift->update(['ends_offset' => 90]);
        UpdateRosterMessage::dispatch($event->id);

        $this->assertSame(['PATCH 123 messages/900', 'POST 123'], array_slice($this->methods(), 2));
        $this->assertSame('901', RosterMessage::query()->value('message_id'));
    }

    public function test_replacing_the_webhook_deletes_the_old_message_and_removing_it_deletes_the_message(): void
    {
        [$event] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->saveEvent($event, [RosterMessages::FIELD => self::OTHER_WEBHOOK]);
        $this->assertSame(['GET 456', 'DELETE 123 messages/900', 'POST 456'], array_slice($this->methods(), 2));
        $this->assertSame('channel-456', RosterMessage::query()->value('channel_id'));

        // Saving again without touching it keeps it.
        $this->saveEvent($event, []);
        $this->assertSame(1, RosterMessage::query()->count());

        $this->saveEvent($event, [], removed: [RosterMessages::FIELD]);
        $this->assertSame('DELETE 456 messages/901', $this->lastCall());
        $this->assertSame(0, RosterMessage::query()->count());
    }

    public function test_only_working_discord_webhooks_are_accepted(): void
    {
        [$event] = $this->shiftEvent();

        $this->saveEvent($event, [RosterMessages::FIELD => 'http://169.254.169.254/latest/meta-data'], errors: [
            'integration_fields.'.RosterMessages::FIELD => 'Paste a webhook URL copied from Discord, starting with https://discord.com/api/webhooks/.',
        ]);
        // Rules without a message of their own name the field by its label.
        $this->saveEvent($event, [RosterMessages::FIELD => 'https://discord.com/api/webhooks/123/'.str_repeat('a', 255)], errors: [
            'integration_fields.'.RosterMessages::FIELD => 'The Roster channel webhook field must not be greater than 255 characters.',
        ]);
        $this->assertSame([], $this->calls);

        $this->saveEvent($event, [RosterMessages::FIELD => 'https://discord.com/api/webhooks/789/deleted'], errors: [
            'integration_fields.'.RosterMessages::FIELD => 'Discord doesn’t know the webhook. It may have been deleted; copy a new one.',
        ]);
        $this->assertSame(0, RosterMessage::query()->count());
        // The rest of the save was rolled back with it.
        $this->assertSame('Copenhagen Tuesday', $event->fresh()->name);
        // And the webhook isn't kept in the session for the form to show again.
        $this->assertSame('Renamed', session()->getOldInput('name'));
        $this->assertNull(session()->getOldInput('integration_fields'));
    }

    public function test_a_webhook_set_while_discord_is_down_is_kept_and_posted_once_it_is_back(): void
    {
        [$event] = $this->shiftEvent();
        $this->discordDown = true;

        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->assertNull(RosterMessage::query()->value('channel_id'));
        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('integrationFields.0.status', 'Saved, but not checked yet: Discord couldn’t be reached. The message is posted once it can be.'));

        $this->discordDown = false;
        app(RosterMessages::class)->sweep();

        $this->assertSame('POST 123', $this->lastCall());
        $this->assertSame('channel-123', RosterMessage::query()->value('channel_id'));
        $this->assertSame('Event Hub', RosterMessage::query()->value('webhook_name'));
    }

    public function test_a_webhook_replaced_while_its_message_is_being_posted_still_leaves_one_message(): void
    {
        [$event] = $this->shiftEvent();
        $this->whilePosting = function () {
            $this->whilePosting = null;
            RosterMessage::query()->sole()->update(['webhook' => self::OTHER_WEBHOOK, 'channel_id' => 'channel-456']);
        };

        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        app(RosterMessages::class)->sweep();

        $this->assertSame(['GET 123', 'POST 123', 'DELETE 123 messages/900', 'POST 456'], $this->methods());
        $this->assertSame('901', RosterMessage::query()->value('message_id'));
        $this->assertSame(self::OTHER_WEBHOOK, RosterMessage::query()->sole()->posted_webhook);
    }

    public function test_a_removed_webhooks_message_is_still_deleted_once_discord_is_back(): void
    {
        [$event] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $this->discordDown = true;

        $this->saveEvent($event, [], removed: [RosterMessages::FIELD]);

        $this->assertSame(['Copenhagen Tuesday: roster message not updated. Discord couldn’t take it (HTTP 503). Try again later.'], app(RosterMessages::class)->needsAttention());
        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page->where('integrationFields.0.status', null));

        $this->discordDown = false;
        app(RosterMessages::class)->sweep();

        $this->assertSame('DELETE 123 messages/900', $this->lastCall());
        $this->assertSame(0, RosterMessage::query()->count());
    }

    public function test_discord_being_down_never_stops_a_booking_and_needs_attention(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $this->discordDown = true;

        $this->actingAs(User::factory()->controller()->create())
            ->post("/occurrences/{$event->occurrences()->sole()->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $shift->bookings()->count());
        // Nor a save of the event.
        $this->saveEvent($event, []);
        $this->assertSame(['Copenhagen Tuesday: roster message not updated. Discord couldn’t take it (HTTP 503). Try again later.'], app(RosterMessages::class)->needsAttention());

        $this->discordDown = false;
        app(RosterMessages::class)->sweep();
        $this->assertSame('PATCH 123 messages/900', $this->lastCall());
        $this->assertSame([], app(RosterMessages::class)->needsAttention());
    }

    public function test_nothing_is_posted_for_a_draft_until_it_is_published_and_the_announcement_links_the_channel(): void
    {
        [$event] = $this->shiftEvent();
        $event->forceFill(['published_at' => null])->save();
        $this->switchOn(['announcements', 'roster'], ['events_webhook' => 'https://discord.com/api/webhooks/777/events']);

        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);
        $this->assertSame(['GET 123'], $this->methods());

        $this->actingAs($this->staff)->post("/events/{$event->id}/publish")->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['GET 123', 'POST 777', 'POST 123'], $this->methods());

        $announcement = collect($this->calls)->first(fn (array $call) => $call[0] === 'POST 777')[1];
        $this->assertContains(['name' => 'Roster channel', 'value' => '<#channel-123>', 'inline' => false], $announcement['embeds'][0]['fields']);
    }

    public function test_the_field_is_gone_while_roster_messages_are_off_and_switching_off_deletes_the_messages(): void
    {
        [$event] = $this->shiftEvent();
        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/discord', [
            'enabled' => true,
            'features' => ['announcements'],
            'settings' => ['events_webhook' => 'https://discord.com/api/webhooks/777/events'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('DELETE 123 messages/900', $this->lastCall());
        $this->assertSame(0, RosterMessage::query()->count());
        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page->where('integrationFields', []));
    }

    public function test_a_long_roster_is_cut_short_with_a_link_to_the_hub(): void
    {
        [$event] = $this->shiftEvent();

        foreach (range(1, 60) as $number) {
            $position = $event->positions()->create(['callsign' => sprintf('EKDK_%02d_CTR', $number)]);
            $position->shifts()->createMany(array_map(fn (int $hour) => ['starts_offset' => $hour * 60, 'ends_offset' => ($hour + 1) * 60], range(0, 3)));
        }

        $this->saveEvent($event, [RosterMessages::FIELD => self::WEBHOOK]);

        $description = $this->lastDescription();
        $this->assertLessThanOrEqual(4096, mb_strlen($description));
        $this->assertMatchesRegularExpression('/…and \d+ more\. \[See the Hub\]\('.preg_quote(url("/events/{$event->id}"), '/').'\)$/', $description);
    }

    /**
     * A published event on 10 Nov 16:00–20:00z, staffed through two shifts on EKCH_TWR.
     *
     * @return array{0: Event, 1: PositionShift}
     */
    private function shiftEvent(): array
    {
        $event = Event::factory()->withShifts()->for($this->copenhagen)->create([
            'name' => 'Copenhagen Tuesday',
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);

        /** @var EventPosition $position */
        $position = $event->positions()->create(['callsign' => 'EKCH_TWR']);
        $first = $position->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $position->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);

        return [$event, $first];
    }

    /**
     * Save the event's form unchanged, apart from its Integration fields.
     *
     * @param  array<string, string>  $fields
     * @param  list<string>  $removed
     * @param  array<int|string, string>  $errors
     */
    private function saveEvent(Event $event, array $fields, array $removed = [], array $errors = []): void
    {
        $event->refresh();
        $response = $this->actingAs($this->staff)->put("/events/{$event->id}", [
            'fir_id' => $event->fir_id,
            'name' => $errors ? 'Renamed' : $event->name,
            'airports' => $event->airports,
            'routes' => [],
            'starts_at' => $event->starts_at->format('Y-m-d H:i'),
            'ends_at' => $event->ends_at->format('Y-m-d H:i'),
            'timezone' => 'UTC',
            'repeats' => $event->repeats->value,
            'repeat_ends' => 'count',
            'repeat_count' => $event->repeat_count ?? 1,
            'integration_fields' => $fields,
            'remove_integration_fields' => $removed,
        ]);

        $errors ? $response->assertSessionHasErrors($errors) : $response->assertSessionHasNoErrors()->assertRedirect();
    }

    /**
     * @param  list<string>  $features
     * @param  array<string, string>  $settings
     */
    private function switchOn(array $features = ['roster'], array $settings = []): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => 'discord'], [
            'enabled' => true,
            'features' => $features,
            'settings' => $settings,
        ]);
    }

    /**
     * Discord's answer to a post: the message, with where it went and as whom.
     */
    private function posted(string $webhookId): PromiseInterface
    {
        if ($this->whilePosting) {
            ($this->whilePosting)();
        }

        return Http::response([
            'id' => (string) $this->nextMessageId++,
            'channel_id' => "channel-{$webhookId}",
            'author' => ['username' => 'Event Hub'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function methods(): array
    {
        return array_column($this->calls, 0);
    }

    private function lastCall(): string
    {
        return $this->calls[array_key_last($this->calls)][0];
    }

    private function lastDescription(): string
    {
        $sent = collect($this->calls)->filter(fn (array $call) => isset($call[1]['embeds']))->last();

        return $sent[1]['embeds'][0]['description'];
    }
}
