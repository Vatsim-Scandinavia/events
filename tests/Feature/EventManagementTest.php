<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventRoster;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['get', 'events.index'])]
    #[TestWith(['get', 'events.create'])]
    #[TestWith(['get', 'events.show'])]
    #[TestWith(['get', 'events.edit'])]
    #[TestWith(['get', 'events.banner'])]
    #[TestWith(['post', 'events.store'])]
    #[TestWith(['post', 'events.markdown-preview'])]
    #[TestWith(['put', 'events.update'])]
    #[TestWith(['post', 'events.cancellations.store'])]
    #[TestWith(['delete', 'events.cancellations.destroy'])]
    public function test_guests_cannot_access_events(string $method, string $route): void
    {
        $event = Event::factory()->create();

        $this->{$method}(route($route, ['event' => $event]))->assertRedirectToRoute('login');

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('event_cancellations', 0);
    }

    #[TestWith([RoleName::EventCoordinator, true, true])]
    #[TestWith([RoleName::VaccStaff, true, false])]
    #[TestWith([RoleName::Controller, false, false])]
    #[TestWith([RoleName::Pilot, false, false])]
    #[TestWith([RoleName::Administrator, true, true])]
    public function test_event_permission_matrix(RoleName $role, bool $view, bool $edit): void
    {
        $event = Event::factory()->create();
        $user = $this->member($event->owner, $role);

        $this->assertSame($view, $user->can('view', $event));
        $this->assertSame($edit, $user->can('update', $event));
        $this->assertSame($edit, $user->can('manageOwner', $event));
        $this->assertSame($edit, $user->can('create', Event::class));
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can_view_events', $view));
    }

    public function test_drafts_are_scoped_and_unknown_or_other_fir_events_are_not_found(): void
    {
        $event = Event::factory()->create(['title' => 'Private event']);
        $other = Event::factory()->create(['title' => 'Unrelated event']);
        $user = $this->member($event->owner);

        $this->actingAs($user)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->component('events/index')->has('events.data', 1)->where('events.data.0.id', $event->id));
        $this->get(route('events.show', $other))->assertNotFound();
        $this->get(route('events.edit', $other))->assertNotFound();
        $this->get(route('events.banner', $other))->assertNotFound();
        $this->get(route('events.show', 99999))->assertNotFound();
    }

    public function test_coordinator_creates_a_private_draft_with_airports_local_schedule_and_audit(): void
    {
        $fir = Team::factory()->create();
        $user = $this->member($fir);
        $payload = $this->payload($fir);

        $this->actingAs($user)->post(route('events.store'), [...$payload, 'status' => 'published', 'banner_path' => '/secrets', 'id' => 9000])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('events.show', Event::firstOrFail());

        $event = Event::firstOrFail();
        $this->assertDatabaseHas('events', [
            'id' => $event->id, 'owner_team_id' => $fir->id, 'status' => 'draft', 'banner_path' => null,
            'local_start' => '2026-10-18T18:00', 'starts_at' => '2026-10-18 16:00:00', 'ends_at' => '2026-10-18 19:00:00',
        ]);
        $this->assertSame($payload['airport_ids'], $event->airports->modelKeys());
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'event', 'subject_id' => $event->id, 'event' => 'created', 'actor_cid' => $user->cid]);
        $this->assertDatabaseMissing('events', ['id' => 9000]);
    }

    public function test_staff_cannot_create_update_cancel_or_invite_and_other_firs_cannot_claim_ownership(): void
    {
        $event = Event::factory()->create();
        $payload = $this->payload($event->owner);
        $staff = $this->member($event->owner, RoleName::VaccStaff);

        $this->actingAs($staff)->post(route('events.store'), $payload)->assertForbidden();
        $this->put(route('events.update', $event), $payload)->assertForbidden();
        $this->post(route('events.cancellations.store', $event))->assertForbidden();
        $this->post(route('events.collaborations.store', $event), ['team_id' => Team::factory()->create()->id])->assertForbidden();
        $this->assertSame('draft', $event->fresh()->status);
        $this->assertDatabaseCount('event_collaborations', 0);

        $other = $this->member(Team::factory()->create());
        $this->actingAs($other)->post(route('events.store'), $payload)->assertSessionHasErrors('owner_team_id');
        $this->put(route('events.update', $event), $payload)->assertForbidden();
        $this->assertDatabaseCount('events', 1);
    }

    public function test_description_is_rendered_as_safe_markdown_and_banner_defaults_to_fallback(): void
    {
        $event = Event::factory()->create([
            'short_description' => '**Summary** [bad](javascript:alert(1)) <img src=x onerror=alert(2)>',
            'description' => "# Heading\n\n**Bold** [bad](javascript:alert(1)) <script>alert(2)</script>",
        ]);
        $user = $this->member($event->owner);

        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.banner_url', null)
            ->where('event.short_description_html', fn (string $html): bool => str_contains($html, '<strong>Summary</strong>')
                && ! str_contains($html, '<img') && ! str_contains($html, 'href="javascript:'))
            ->where('description_html', fn (string $html): bool => str_contains($html, '<h1>Heading</h1>')
                && str_contains($html, '<strong>Bold</strong>') && ! str_contains($html, '<script>') && ! str_contains($html, 'href="javascript:')));

        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.short_description_html', fn (string $html): bool => str_contains($html, '<strong>Summary</strong>')
                && ! str_contains($html, '<img') && ! str_contains($html, 'href="javascript:')));
    }

    public function test_markdown_preview_matches_saved_rendering_without_saving_changes(): void
    {
        $event = Event::factory()->create([
            'description' => "## Welcome\n\n**Bold** and *italic* [charts](https://example.com)\n\n- First\n- Second\n\n| Airport | Time |\n| --- | --- |\n| EKCH | 1800Z |\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(2))",
        ]);
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $response = $this->postJson(route('events.markdown-preview'), ['markdown' => $event->description])->assertOk();
        $html = $response->json('html');

        $this->assertStringContainsString('<h2>Welcome</h2>', $html);
        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<a href="https://example.com">charts</a>', $html);
        $this->assertStringContainsString('<li>First</li>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('description_html', $html));
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    #[TestWith([RoleName::VaccStaff])]
    #[TestWith([RoleName::Controller])]
    #[TestWith([RoleName::Pilot])]
    public function test_read_only_users_cannot_preview_event_markdown(RoleName $role): void
    {
        $user = $this->member(Team::factory()->create(), $role);

        $this->actingAs($user)->postJson(route('events.markdown-preview'), ['markdown' => '**Test**'])->assertForbidden();
    }

    public function test_preview_accepts_empty_text_and_rejects_invalid_or_oversized_text(): void
    {
        $this->actingAs($this->member(Team::factory()->create()));

        $this->postJson(route('events.markdown-preview'), ['markdown' => ''])->assertExactJson(['html' => '']);
        $this->postJson(route('events.markdown-preview'), [])->assertJsonValidationErrors('markdown');
        $this->postJson(route('events.markdown-preview'), ['markdown' => ['invalid']])->assertJsonValidationErrors('markdown');
        $this->postJson(route('events.markdown-preview'), ['markdown' => str_repeat('a', 50001)])->assertJsonValidationErrors('markdown');
    }

    public function test_descriptions_preserve_markdown_when_creating_and_editing_events(): void
    {
        $fir = Team::factory()->create();
        $payload = [...$this->payload($fir), 'short_description' => '**Staffed** evening', 'description' => "## Welcome\n\n- Bring charts\n- Enjoy the flight"];
        $this->actingAs($this->member($fir));

        $this->post(route('events.store'), $payload)->assertSessionHasNoErrors();
        $event = Event::firstOrFail();

        $this->assertSame($payload['short_description'], $event->short_description);
        $this->assertSame($payload['description'], $event->description);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.short_description', $payload['short_description'])->where('event.description', $payload['description']));

        $this->put(route('events.update', $event), [...$payload, 'short_description' => '*Updated* summary', 'description' => '> Updated briefing'])->assertSessionHasNoErrors();

        $this->assertSame('*Updated* summary', $event->fresh()->short_description);
        $this->assertSame('> Updated briefing', $event->fresh()->description);
    }

    public function test_banners_are_private_replaced_cleanly_and_removable(): void
    {
        Storage::fake('local');
        $fir = Team::factory()->create();
        $user = $this->member($fir);
        $payload = $this->payload($fir);

        $this->actingAs($user)->post(route('events.store'), [...$payload, 'banner' => $this->banner()])->assertSessionHasNoErrors();
        $event = Event::firstOrFail();
        $oldPath = $event->banner_path;
        Storage::disk('local')->assertExists($oldPath);
        $this->get(route('events.banner', $event))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->post(route('events.update', $event), [...$payload, '_method' => 'put', 'banner' => $this->banner()])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $newPath = $event->fresh()->banner_path;
        Storage::disk('local')->assertExists($newPath);

        $this->put(route('events.update', $event), [...$payload, 'remove_banner' => true])->assertSessionHasNoErrors();
        $this->assertNull($event->fresh()->banner_path);
        Storage::disk('local')->assertMissing($newPath);
    }

    public function test_cancelling_one_occurrence_preserves_the_series_and_is_idempotent(): void
    {
        $this->freezeTime();
        $event = Event::factory()->weekly(2)->create();
        $user = $this->member($event->owner);

        $this->actingAs($user)->post(route('events.cancellations.store', $event), ['occurrence_date' => '2026-10-18', 'reason' => 'No staffing'])
            ->assertSessionHasNoErrors();
        $this->post(route('events.cancellations.store', $event), ['occurrence_date' => '2026-10-18', 'reason' => 'Duplicate'])->assertSessionHasNoErrors();

        $this->assertSame('draft', $event->fresh()->status);
        $this->assertDatabaseHas('event_cancellations', ['event_id' => $event->id, 'occurrence_date' => '2026-10-18', 'reason' => 'No staffing']);
        $this->assertDatabaseCount('event_cancellations', 1);
        $this->assertSame(1, AuditLog::where('subject_type', 'event')->count());
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.0.status', 'scheduled')->where('occurrences.1.status', 'cancelled')->where('occurrences.2.status', 'scheduled'));
    }

    public function test_default_schedule_keeps_an_ongoing_overnight_event_but_explicit_dates_filter_by_start(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-09T00:30:00Z'));
        $event = Event::factory()->create([
            'timezone' => 'Europe/Copenhagen',
            'local_start' => '2026-09-08T23:00', 'local_end' => '2026-09-09T03:00',
            'starts_at' => '2026-09-08 21:00:00', 'ends_at' => '2026-09-09 01:00:00',
        ]);
        $user = $this->member($event->owner);

        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('from', '2026-09-09')->has('occurrences', 1)
            ->where('occurrences.0.date', '2026-09-08')->where('occurrences.0.ends_at', '2026-09-09T01:00:00+00:00'));
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-09-09']))
            ->assertInertia(fn (Assert $page) => $page->has('occurrences', 0));

        $this->travelTo(CarbonImmutable::parse('2026-09-09T01:00:00Z'));
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->has('occurrences', 0));
    }

    public function test_entire_series_cancellation_preserves_records_and_prevents_editing(): void
    {
        $event = Event::factory()->weekly()->create();
        $user = $this->member($event->owner);

        $this->actingAs($user)->post(route('events.cancellations.store', $event), ['reason' => 'Series discontinued'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled', 'cancellation_reason' => 'Series discontinued']);
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('can.edit', false)->where('occurrences.0.status', 'cancelled')->where('occurrences.1.status', 'cancelled'));
        $this->put(route('events.update', $event), $this->payload($event->owner))->assertSessionHasErrors('event');
        $this->assertSame('cancelled', $event->fresh()->status);
    }

    public function test_non_occurrence_dates_and_schedule_changes_after_cancellation_are_rejected(): void
    {
        $event = Event::factory()->weekly(2)->create();
        $user = $this->member($event->owner);

        $this->actingAs($user)->post(route('events.cancellations.store', $event), ['occurrence_date' => '2026-10-11'])
            ->assertSessionHasErrors(['occurrence_date' => 'Choose an occurrence in this event schedule.']);
        $this->assertDatabaseCount('event_cancellations', 0);

        EventCancellation::factory()->for($event)->create();
        $this->put(route('events.update', $event), $this->payload($event->owner))->assertSessionHasErrors('recurrence');
        $this->assertSame('2026-10-04T18:00', $event->fresh()->local_start);
        $this->assertDatabaseCount('event_cancellations', 1);
    }

    #[TestWith(['timezone', 'Mars/Olympus'])]
    #[TestWith(['local_end', '2026-10-18T17:00'])]
    #[TestWith(['local_start', '2026-02-30T18:00'])]
    #[TestWith(['recurrence_interval', 0])]
    #[TestWith(['recurrence_interval', 53])]
    #[TestWith(['recurrence', 'yearly'])]
    #[TestWith(['monthly_week', 9])]
    #[TestWith(['recurrence_until', '2026-10-01'])]
    #[TestWith(['airport_ids', []])]
    #[TestWith(['airport_ids', [99999]])]
    #[TestWith(['short_description', null])]
    public function test_invalid_event_details_are_rejected(string $field, mixed $value): void
    {
        $fir = Team::factory()->create();
        $user = $this->member($fir);

        $this->actingAs($user)->post(route('events.store'), [...$this->payload($fir), $field => $value])
            ->assertSessionHasErrors($field === 'airport_ids' && $value !== [] ? 'airport_ids.0' : $field);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_missing_required_fields_are_reported(): void
    {
        $user = $this->member(Team::factory()->create());

        $this->actingAs($user)->post(route('events.store'), [])->assertSessionHasErrors(['title', 'short_description', 'description', 'airport_ids', 'owner_team_id', 'timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_dst_gap_and_mismatched_monthly_week_are_rejected(): void
    {
        $fir = Team::factory()->create();
        $payload = $this->payload($fir);
        $this->actingAs($this->member($fir))->post(route('events.store'), [...$payload, 'local_start' => '2026-03-29T02:30', 'local_end' => '2026-03-29T04:00'])
            ->assertSessionHasErrors(['local_start' => 'This local time does not exist because the clocks change. Choose another time.']);
        $this->post(route('events.store'), [...$payload, 'recurrence' => 'monthly', 'monthly_week' => 2])->assertSessionHasErrors('monthly_week');
        $this->assertDatabaseCount('events', 0);
    }

    public function test_non_image_and_oversized_uploads_are_rejected_without_files(): void
    {
        Storage::fake('local');
        $fir = Team::factory()->create();
        $payload = $this->payload($fir);
        $this->actingAs($this->member($fir))->post(route('events.store'), [...$payload, 'banner' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('banner');
        $this->post(route('events.store'), [...$payload, 'banner' => $this->banner()->size(5121)])->assertSessionHasErrors('banner');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('events', 0);
    }

    public function test_firs_with_events_cannot_be_deleted(): void
    {
        $event = Event::factory()->create();
        $administrator = $this->member(Team::factory()->create(), RoleName::Administrator);

        $this->actingAs($administrator)->delete(route('firs.destroy', $event->owner))->assertSessionHasErrors('fir');

        $this->assertModelExists($event->owner);
        $this->assertModelExists($event);
    }

    public function test_private_banner_cannot_be_read_by_an_unrelated_fir(): void
    {
        Storage::fake('local');
        $path = $this->banner()->store('event-banners', 'local');
        $event = Event::factory()->create(['banner_path' => $path]);
        $outsider = $this->member(Team::factory()->create());

        $this->actingAs($outsider)->get(route('events.banner', $event))->assertNotFound();
        $this->actingAs($this->member($event->owner, RoleName::VaccStaff))->get(route('events.banner', $event))->assertOk();

        Storage::disk('local')->assertExists($path);
    }

    public function test_rejected_edits_remove_the_new_upload_and_keep_the_original_banner(): void
    {
        Storage::fake('local');
        $path = $this->banner()->store('event-banners', 'local');
        $event = Event::factory()->create(['status' => 'cancelled', 'banner_path' => $path]);
        $payload = $this->payload($event->owner);

        $this->actingAs($this->member($event->owner))->post(route('events.update', $event), [
            ...$payload, '_method' => 'put', 'banner' => $this->banner(),
        ])->assertSessionHasErrors('event');

        $this->assertSame([$path], Storage::disk('local')->allFiles());
        $this->assertSame($path, $event->fresh()->banner_path);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    public function test_content_can_change_after_cancellation_without_losing_the_cancelled_date(): void
    {
        $fir = Team::factory()->create();
        $payload = $this->payload($fir);
        $event = Event::factory()->weekly(2)->create([
            'owner_team_id' => $fir->id, 'local_start' => $payload['local_start'],
            'local_end' => $payload['local_end'], 'timezone' => $payload['timezone'],
        ]);
        EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-18']);

        $this->actingAs($this->member($fir))->put(route('events.update', $event), [...$payload, 'title' => 'Updated title'])->assertSessionHasNoErrors();

        $this->assertSame('Updated title', $event->fresh()->title);
        $this->assertDatabaseHas('event_cancellations', ['event_id' => $event->id, 'occurrence_date' => '2026-10-18']);
    }

    public function test_rosters_lock_the_event_schedule_but_allow_content_updates(): void
    {
        $fir = Team::factory()->create();
        $payload = $this->payload($fir);
        $event = Event::factory()->weekly(2)->create([
            'owner_team_id' => $fir->id, 'local_start' => $payload['local_start'],
            'local_end' => $payload['local_end'], 'timezone' => $payload['timezone'],
        ]);
        $roster = EventRoster::factory()->for($event)->create();
        $this->actingAs($this->member($fir));

        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.schedule_locked', true)->where('event.roster_exists', true));
        $this->put(route('events.update', $event), [...$payload, 'local_start' => '2026-10-18T19:00'])
            ->assertSessionHasErrors('recurrence');

        $this->assertSame('2026-10-18T18:00', $event->fresh()->local_start);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
        $this->assertModelExists($roster);

        $this->put(route('events.update', $event), [...$payload, 'title' => 'Updated briefing'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated briefing', $event->fresh()->title);
        $this->assertModelExists($roster);
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        if ($role !== RoleName::Pilot) {
            app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);
        }

        return $user;
    }

    private function payload(Team $fir): array
    {
        return [
            'owner_team_id' => $fir->id, 'title' => 'Sunday event', 'short_description' => 'A staffed evening', 'description' => '## Welcome',
            'airport_ids' => [Airport::factory()->create()->id], 'timezone' => 'Europe/Copenhagen',
            'local_start' => '2026-10-18T18:00', 'local_end' => '2026-10-18T21:00',
            'recurrence' => 'weekly', 'recurrence_interval' => 2, 'monthly_week' => null, 'recurrence_until' => null,
        ];
    }

    private function banner(): File
    {
        return UploadedFile::fake()->createWithContent('banner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }
}
