<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventCollaboration;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventRestorationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([RoleName::EventCoordinator])]
    #[TestWith([RoleName::Administrator])]
    public function test_cancelled_event_is_restored_to_draft_and_audited_once(RoleName $role): void
    {
        $event = Event::factory()->create([
            'status' => 'cancelled', 'cancelled_at' => '2026-09-01 12:00:00', 'cancellation_reason' => 'No staffing',
        ]);
        $user = $this->member($event->owner, $role);
        $url = route('events.show', ['event' => $event, 'from' => '2026-10-04']);

        $this->actingAs($user)->from($url)->delete(route('events.cancellations.destroy', $event), ['status' => 'published', 'title' => 'Unwanted change'])
            ->assertSessionHasNoErrors()->assertRedirect($url);
        $this->delete(route('events.cancellations.destroy', $event))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', [
            'id' => $event->id, 'status' => 'draft', 'cancelled_at' => null, 'cancellation_reason' => null,
            'title' => $event->title, 'local_start' => '2026-10-04T18:00', 'local_end' => '2026-10-04T21:00',
        ]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('event.status', 'draft')->where('can.edit', true)
            ->where('occurrences.0.status', 'scheduled')->where('occurrences.0.reason', null));
        $this->get(route('events.edit', $event))->assertOk();

        $audit = AuditLog::where('subject_type', 'event')->sole();
        $this->assertSame($user->cid, $audit->actor_cid);
        $this->assertSame($event->id, $audit->subject_id);
        $this->assertSame('updated', $audit->event);
        $this->assertSame(['status' => 'cancelled', 'cancellation_reason' => 'No staffing'], $audit->old_values);
        $this->assertSame(['status' => 'draft', 'cancellation_reason' => null], $audit->new_values);
    }

    public function test_restoring_a_series_preserves_individual_cancellations_and_its_schedule(): void
    {
        $event = Event::factory()->weekly(2)->create([
            'status' => 'cancelled', 'cancelled_at' => '2026-09-01 12:00:00', 'cancellation_reason' => 'Series paused',
            'timezone' => 'Europe/Copenhagen', 'recurrence_until' => '2026-11-01',
        ]);
        $cancellation = EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-18']);

        $this->actingAs($this->member($event->owner))->delete(route('events.cancellations.destroy', $event))->assertSessionHasNoErrors();

        $this->assertModelExists($cancellation);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'cancelled_at' => null, 'cancellation_reason' => null]);
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('event.schedule_locked', true)->has('occurrences', 3)
            ->where('occurrences.0.date', '2026-10-04')->where('occurrences.0.status', 'scheduled')
            ->where('occurrences.1.date', '2026-10-18')->where('occurrences.1.status', 'cancelled')
            ->where('occurrences.1.reason', 'Insufficient staffing')
            ->where('occurrences.2.date', '2026-11-01')->where('occurrences.2.status', 'scheduled')
            ->where('occurrences.2.starts_at', '2026-11-01T17:00:00+00:00'));
    }

    public function test_restoring_one_occurrence_preserves_other_dates_and_other_events_and_is_audited_once(): void
    {
        $event = Event::factory()->weekly()->create();
        $restored = EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-11']);
        $retained = EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-18']);
        $other = EventCancellation::factory()->for(Event::factory()->weekly())->create(['occurrence_date' => '2026-10-11']);
        $user = $this->member($event->owner);
        $url = route('events.show', ['event' => $event, 'from' => '2026-10-04']);

        $this->actingAs($user)->from($url)->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-11'])
            ->assertSessionHasNoErrors()->assertRedirect($url);
        $this->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-11'])->assertSessionHasNoErrors();

        $this->assertModelMissing($restored);
        $this->assertModelExists($retained);
        $this->assertModelExists($other);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('event.schedule_locked', true)
            ->where('occurrences.0.status', 'scheduled')
            ->where('occurrences.1.date', '2026-10-11')->where('occurrences.1.status', 'scheduled')
            ->where('occurrences.1.reason', null)->where('occurrences.1.starts_at', '2026-10-11T18:00:00+00:00')
            ->where('occurrences.2.status', 'cancelled'));

        $audit = AuditLog::where('subject_type', 'event')->sole();
        $this->assertSame($user->cid, $audit->actor_cid);
        $this->assertSame(['cancellations' => [
            ['occurrence_date' => '2026-10-11', 'reason' => 'Insufficient staffing'],
            ['occurrence_date' => '2026-10-18', 'reason' => 'Insufficient staffing'],
        ]], $audit->old_values);
        $this->assertSame(['cancellations' => [
            ['occurrence_date' => '2026-10-18', 'reason' => 'Insufficient staffing'],
        ]], $audit->new_values);
    }

    public function test_accepted_collaborator_can_restore_the_last_cancelled_occurrence(): void
    {
        $event = Event::factory()->weekly()->create();
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        $cancellation = EventCancellation::factory()->for($event)->create();

        $this->actingAs($this->member($collaboration->team))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-04'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($cancellation);
        $this->get(route('events.show', ['event' => $event, 'from' => '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('event.schedule_locked', false)->where('can.edit', true)->where('can.manage_owner', false)
            ->where('occurrences.0.status', 'scheduled'));
    }

    public function test_collaborator_cannot_restore_the_entire_event(): void
    {
        $event = Event::factory()->weekly()->create(['status' => 'cancelled']);
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();

        $this->actingAs($this->member($collaboration->team))->delete(route('events.cancellations.destroy', $event))->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    #[TestWith([null])]
    #[TestWith(['2026-10-04'])]
    public function test_read_only_staff_cannot_restore_events_or_occurrences(?string $date): void
    {
        $event = Event::factory()->weekly()->create(['status' => 'cancelled']);
        $cancellation = EventCancellation::factory()->for($event)->create();

        $this->actingAs($this->member($event->owner, RoleName::VaccStaff))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => $date])
            ->assertForbidden();

        $this->assertModelExists($cancellation);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    public function test_unrelated_and_pending_firs_cannot_restore_occurrences(): void
    {
        $event = Event::factory()->weekly()->create();
        $cancellation = EventCancellation::factory()->for($event)->create();
        $pending = EventCollaboration::factory()->for($event)->create();

        $this->actingAs($this->member(Team::factory()->create()))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-04'])
            ->assertForbidden();
        $this->actingAs($this->member($pending->team))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-04'])
            ->assertForbidden();

        $this->assertModelExists($cancellation);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    public function test_occurrence_cannot_be_restored_until_the_entire_event_is_restored(): void
    {
        $event = Event::factory()->weekly()->create(['status' => 'cancelled']);
        $cancellation = EventCancellation::factory()->for($event)->create();

        $this->actingAs($this->member($event->owner))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-10-04'])
            ->assertSessionHasErrors(['occurrence_date' => 'Restore the event before restoring individual occurrences.']);

        $this->assertModelExists($cancellation);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    #[TestWith(['not-a-date', 'The occurrence date field must match the format Y-m-d.'])]
    #[TestWith(['2026-10-11', 'Choose an occurrence in this event schedule.'])]
    #[TestWith(['2026-09-20', 'Choose an occurrence in this event schedule.'])]
    #[TestWith(['2026-11-15', 'Choose an occurrence in this event schedule.'])]
    public function test_invalid_restore_dates_are_rejected_without_changes(string $date, string $message): void
    {
        $event = Event::factory()->weekly(2)->create(['recurrence_until' => '2026-11-01']);
        $cancellation = EventCancellation::factory()->for($event)->create();

        $this->actingAs($this->member($event->owner))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => $date])
            ->assertSessionHasErrors(['occurrence_date' => $message]);

        $this->assertModelExists($cancellation);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    public function test_skipped_dst_occurrences_cannot_be_restored(): void
    {
        $event = Event::factory()->weekly()->create([
            'timezone' => 'Europe/Copenhagen', 'local_start' => '2026-03-22T02:30', 'local_end' => '2026-03-22T04:00',
        ]);

        $this->actingAs($this->member($event->owner))->delete(route('events.cancellations.destroy', $event), ['occurrence_date' => '2026-03-29'])
            ->assertSessionHasErrors(['occurrence_date' => 'Choose an occurrence in this event schedule.']);

        $this->assertDatabaseCount('event_cancellations', 0);
        $this->assertSame(0, AuditLog::where('subject_type', 'event')->count());
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);

        return $user;
    }
}
