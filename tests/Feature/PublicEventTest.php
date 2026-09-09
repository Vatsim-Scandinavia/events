<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventCollaboration;
use App\Models\EventRoster;
use App\Models\RosterBooking;
use App\Models\RosterInterest;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unrelated_signed_in_users_can_read_published_events_without_management_access(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create(['title' => 'Copenhagen evening']);
        Event::factory()->create(['title' => 'Private draft']);
        $airport = Airport::factory()->create(['icao' => 'EKCH']);
        $event->airports()->attach($airport);

        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->component('events/index')->where('auth.user', null)->has('events.data', 1)
            ->where('events.data.0.id', $event->id)->where('events.data.0.airports.0.icao', 'EKCH'));
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->component('events/show')->where('event.title', 'Copenhagen evening')->missing('can'));

        $outsider = $this->coordinator(Team::factory()->create());
        $auditCount = AuditLog::count();
        $this->actingAs($outsider)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.id', $event->id)->missing('can'));
        $this->get(route('events.edit', $event))->assertNotFound();

        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_unified_event_uris_are_public_and_only_editing_endpoints_require_login(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create();

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->component('events/index')->where('events.data.0.id', $event->id));
        $this->get('/events/'.$event->id)->assertInertia(fn (Assert $page) => $page
            ->component('events/show')->where('event.id', $event->id)->missing('can'));
        $this->get('/events/create')->assertRedirectToRoute('login');
        $this->get('/events/'.$event->id.'/edit')->assertRedirectToRoute('login');
        $this->get('/events/manage')->assertStatus(405);
        foreach (['/calendar', '/events/'.$event->id.'/manage', '/events/'.$event->id.'/manage/banner'] as $uri) {
            $this->get($uri)->assertNotFound();
        }

        $this->actingAs(User::factory()->create())->get('/events')->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->missing('can_create')->missing('invitations'));
        $this->get('/events/create')->assertForbidden();
        $this->get('/events/'.$event->id)->assertInertia(fn (Assert $page) => $page->where('event.id', $event->id)->missing('can'));
        $this->get('/events/'.$event->id.'/edit')->assertNotFound();
    }

    public function test_authorized_users_preview_private_drafts_and_banners_on_the_shared_paths(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-09-09 12:00:00');
        $path = 'event-banners/private-preview.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $event = Event::factory()->create(['banner_path' => $path]);
        $owner = $this->coordinator($event->owner);
        $outsider = $this->coordinator(Team::factory()->create());

        $this->get('/events/'.$event->id)->assertNotFound();
        $this->get('/events/'.$event->id.'/banner')->assertNotFound();
        $this->actingAs($owner)->get('/events/'.$event->id)->assertInertia(fn (Assert $page) => $page->component('events/show')->where('event.status', 'draft')->where('can.edit', true));
        $this->get('/events/'.$event->id.'/edit')->assertInertia(fn (Assert $page) => $page->component('events/form')->where('event.status', 'draft'));
        $this->get('/events/'.$event->id.'/banner')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($outsider)->get('/events/'.$event->id)->assertNotFound();
        $this->get('/events/'.$event->id.'/edit')->assertNotFound();
        $this->get('/events/'.$event->id.'/banner')->assertNotFound();
        Storage::disk('local')->assertExists($path);
    }

    #[TestWith(['draft', false])]
    #[TestWith(['draft', true])]
    #[TestWith(['published', false])]
    #[TestWith(['cancelled', false])]
    public function test_events_without_both_publication_stamp_and_public_status_are_hidden_including_banners(string $status, bool $stamped): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create(['status' => $status, 'published_at' => $stamped ? now() : null, 'banner_path' => 'event-banners/private.png']);

        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
        $this->get(route('events.show', $event))->assertNotFound();
        $this->get(route('events.banner', $event))->assertNotFound();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unknown_public_event_and_banner_ids_return_not_found(): void
    {
        $this->get(route('events.show', 99999))->assertNotFound();
        $this->get(route('events.banner', 99999))->assertNotFound();
    }

    public function test_public_event_payloads_allow_only_display_fields_and_never_expose_roster_members_or_invitations(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->rostered()->create();
        $roster = EventRoster::factory()->for($event)->open()->create();
        $controller = User::factory()->create(['name_full' => 'Private Controller Person']);
        $shift = RosterShift::factory()->create(['roster_id' => $roster->id, 'name' => 'Private coordinator notes']);
        $slot = RosterSlot::factory()->create(['shift_id' => $shift->id]);
        RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id, 'user_cid' => $controller->cid]);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $controller->cid, 'position_ids' => [$position->id]]);
        EventCollaboration::factory()->for($event)->create();
        $owner = $this->coordinator($event->owner);
        $auditCount = AuditLog::count();

        $pageResponse = $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event', function (Collection $data): bool {
                $this->assertPublicEventKeys($data->all(), true);

                return true;
            })
            ->where('auth.user', null)->missing('can')->missing('roster')->missing('collaborations')->missing('invitations')
            ->missing('firs')->missing('can')->missing('canManage')->missing('currentUserCid'));
        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0', function (Collection $data): bool {
                $this->assertPublicEventKeys($data->all(), false);

                return true;
            })->missing('invitations')->missing('can_create'));
        $this->get(route('events.roster.show', $event))->assertRedirectToRoute('login');

        $response = $this->actingAs($owner)->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Version' => $pageResponse->viewData('page')['version'],
        ])->get('/events/'.$event->id)->assertOk()
            ->assertJsonPath('component', 'events/show')->assertJsonPath('props.can.edit', true)
            ->assertJsonPath('props.can.manage_owner', true)->assertJsonPath('props.event.roster_enabled', true)
            ->assertJsonMissingPaths(['props.roster', 'props.invitations', 'props.currentUserCid', 'props.can_view_management']);
        $this->assertPublicEventKeys($response->json('props.event'), true, true);

        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_publishing_an_event_does_not_expose_its_unopened_roster_to_unrelated_users(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->rostered()->create();
        $roster = EventRoster::factory()->for($event)->create();
        $otherFir = Team::factory()->create();
        $coordinator = $this->coordinator($otherFir);
        $controller = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($controller, RoleName::Controller, $otherFir);
        $auditCount = AuditLog::count();

        foreach ([$coordinator, $controller] as $user) {
            $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.id', $event->id));
            $this->get(route('events.roster.show', $event))->assertNotFound();
            $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page->has('rosters.data', 0));
            $this->assertFalse($user->can('view', $event));
            $this->assertFalse($user->can('view', $roster));
        }

        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_public_markdown_preserves_formatting_and_strips_raw_html_and_unsafe_links(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create([
            'short_description' => '**Staffed** [bad](javascript:alert(1)) <img src=x onerror=alert(2)>',
            'description' => "## Briefing\n\n**Bring charts** [safe](https://example.com/charts) [bad](javascript:alert(1)) <script>alert(2)</script>",
        ]);

        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.short_description_html', fn (string $html): bool => str_contains($html, '<strong>Staffed</strong>') && ! str_contains($html, '<img') && ! str_contains($html, 'href="javascript:'))
            ->where('event.description_html', fn (string $html): bool => str_contains($html, '<h2>Briefing</h2>') && str_contains($html, '<strong>Bring charts</strong>')
                && str_contains($html, 'href="https://example.com/charts"') && ! str_contains($html, '<script>') && ! str_contains($html, 'href="javascript:')));
        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.short_description_html', fn (string $html): bool => str_contains($html, '<strong>Staffed</strong>') && ! str_contains($html, '<img') && ! str_contains($html, 'href="javascript:')));
    }

    public function test_public_banner_access_is_revoked_immediately_when_the_event_is_unpublished(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-09-09 12:00:00');
        $path = 'event-banners/public.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $event = Event::factory()->published()->create(['banner_path' => $path]);
        $owner = $this->coordinator($event->owner);

        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.banner_url', route('events.banner', $event)));
        $this->get('/events/'.$event->id.'/banner')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->actingAs($owner)->delete(route('events.publication.destroy', $event))->assertSessionHasNoErrors();

        $this->actingAsGuest()->get(route('events.show', $event))->assertNotFound();
        $this->get(route('events.banner', $event))->assertNotFound();
        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
        Storage::disk('local')->assertExists($path);
    }

    public function test_missing_public_banner_files_and_absent_banner_paths_return_not_found(): void
    {
        Storage::fake('local');
        $event = Event::factory()->published()->create();
        $missingFile = Event::factory()->published()->create(['banner_path' => 'event-banners/missing.png']);

        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.banner_url', null));
        $this->get(route('events.banner', $event))->assertNotFound();
        $this->get(route('events.banner', $missingFile))->assertNotFound();
    }

    public function test_public_event_listing_search_only_returns_matching_published_events(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $matching = Event::factory()->published()->create(['title' => 'Tower evening']);
        Event::factory()->published()->create(['title' => 'Approach evening']);
        Event::factory()->create(['title' => 'Tower private draft']);

        $this->get(route('events.index', ['search' => '  Tower  ']))->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Tower')->has('events.data', 1)->where('events.data.0.id', $matching->id));
    }

    public function test_public_event_listing_paginates_without_private_events_affecting_the_total(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        Event::factory()->published()->count(13)->create();
        Event::factory()->count(2)->create();

        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 12)->where('events.total', 13)->where('events.last_page', 2));
        $this->get(route('events.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)->where('events.current_page', 2));
    }

    #[TestWith(['page', 0])]
    #[TestWith(['page', 'invalid'])]
    #[TestWith(['search', ['invalid']])]
    #[TestWith(['from', 'not-a-date'])]
    public function test_invalid_public_event_listing_filters_are_rejected(string $field, mixed $value): void
    {
        $this->get(route('events.index', [$field => $value]))->assertSessionHasErrors($field);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_public_occurrences_preserve_local_times_and_individual_cancellations_across_dst(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->weekly()->create([
            'timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T18:00', 'local_end' => '2026-10-18T21:00',
            'starts_at' => '2026-10-18 16:00:00', 'ends_at' => '2026-10-18 19:00:00', 'recurrence_until' => '2026-11-01',
        ]);
        EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-25', 'reason' => 'No staffing']);

        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-18']))->assertInertia(fn (Assert $page) => $page
            ->has('occurrences', 3)->where('occurrences.0.starts_at', '2026-10-18T16:00:00+00:00')
            ->where('occurrences.1.starts_at', '2026-10-25T17:00:00+00:00')->where('occurrences.1.status', 'cancelled')->where('occurrences.1.reason', 'No staffing')
            ->where('occurrences.2.date', '2026-11-01')->where('next_from', null));
    }

    public function test_public_occurrence_pagination_is_bounded_and_starts_the_next_page_without_duplicates(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->weekly()->create();

        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->has('occurrences', 12)->where('occurrences.0.date', '2026-10-04')->where('occurrences.11.date', '2026-12-20')->where('next_from', '2026-12-27'));
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-12-27']))->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.0.date', '2026-12-27'));
    }

    /** @param array<string, mixed> $data */
    private function assertPublicEventKeys(array $data, bool $detail, bool $staff = false): void
    {
        $expected = ['id', 'title', 'status', 'timezone', 'local_start', 'recurrence', 'recurrence_interval', 'monthly_week', 'short_description_html', 'recurrence_until', 'starts_at', 'ends_at', 'owner', 'airports', 'banner_url', 'occurrence'];
        if ($detail) {
            $expected = [...$expected, 'description_html', 'cancellation_reason'];
        }
        if ($staff) {
            $expected = [...$expected, 'roster_enabled', 'roster_exists'];
        }
        $actual = array_keys($data);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
        $this->assertSame(['id', 'code', 'name'], array_keys($data['owner']));
    }

    private function coordinator(Team $fir): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, RoleName::EventCoordinator, $fir);

        return $user;
    }
}
