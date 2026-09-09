<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRoster;
use App\Models\RosterBooking;
use App\Models\RosterInterest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class RosterTemplateMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_keeps_the_latest_template_and_preserves_matching_and_obsolete_slot_bookings(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->weekly()->create(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T18:00', 'local_end' => '2026-10-18T21:00', 'starts_at' => '2026-10-18 16:00:00', 'ends_at' => '2026-10-18 19:00:00']);
        $controller = User::factory()->create();
        $migration = $this->migration();
        $migration->down();
        $old = $this->legacyRoster($event, '2026-10-18', 'pre_slotted', '2026-09-01 12:00:00');
        $latest = $this->legacyRoster($event, '2026-10-25', 'pre_slotted', '2026-09-02 12:00:00');
        $oldShift = DB::table('roster_shifts')->insertGetId(['roster_id' => $old, 'name' => 'Early']);
        $latestShift = DB::table('roster_shifts')->insertGetId(['roster_id' => $latest, 'name' => 'Early']);
        $this->legacySlot($oldShift, 'EKCH_A_TWR', '2026-10-18 16:00:00', '2026-10-18 17:00:00', $controller);
        $this->legacySlot($oldShift, 'EKCH_APP', '2026-10-18 17:00:00', '2026-10-18 18:00:00', $controller);
        $canonicalSlot = $this->legacySlot($latestShift, 'EKCH_A_TWR', '2026-10-25 17:00:00', '2026-10-25 18:00:00', $controller);

        $migration->up();

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseHas('event_rosters', ['id' => $latest, 'event_id' => $event->id, 'mode' => 'pre_slotted']);
        $this->assertDatabaseCount('roster_slots', 1);
        $this->assertDatabaseHas('roster_slots', ['id' => $canonicalSlot, 'shift_id' => $latestShift, 'start_day_offset' => 0, 'start_time' => '18:00', 'end_time' => '19:00']);
        $this->assertDatabaseCount('roster_bookings', 3);
        $this->assertDatabaseHas('roster_bookings', ['roster_id' => $latest, 'slot_id' => $canonicalSlot, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-18', 'callsign' => 'EKCH_A_TWR', 'starts_at' => '2026-10-18 16:00:00']);
        $this->assertDatabaseHas('roster_bookings', ['roster_id' => $latest, 'slot_id' => $canonicalSlot, 'occurrence_date' => '2026-10-25', 'starts_at' => '2026-10-25 17:00:00']);
        $this->assertDatabaseHas('roster_bookings', ['roster_id' => $latest, 'slot_id' => null, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-18', 'callsign' => 'EKCH_APP', 'shift_name' => 'Early', 'starts_at' => '2026-10-18 17:00:00', 'ends_at' => '2026-10-18 18:00:00']);
    }

    public function test_upgrade_preserves_each_dates_interest_and_callsign_snapshots_when_old_positions_disappear(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->weekly()->create();
        $controller = User::factory()->create();
        $migration = $this->migration();
        $migration->down();
        $old = $this->legacyRoster($event, '2026-10-04', 'open_interest', '2026-09-01 12:00:00');
        $latest = $this->legacyRoster($event, '2026-10-11', 'open_interest', '2026-09-02 12:00:00');
        $oldTower = DB::table('roster_positions')->insertGetId(['roster_id' => $old, 'callsign' => 'EKCH_A_TWR']);
        $oldApproach = DB::table('roster_positions')->insertGetId(['roster_id' => $old, 'callsign' => 'EKCH_APP']);
        $newTower = DB::table('roster_positions')->insertGetId(['roster_id' => $latest, 'callsign' => 'EKCH_A_TWR']);
        $oldInterest = $this->legacyInterest($old, $controller, [$oldTower, $oldApproach], '2026-10-04');
        $latestInterest = $this->legacyInterest($latest, $controller, [$newTower], '2026-10-11');

        $migration->up();

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseCount('roster_positions', 1);
        $this->assertDatabaseCount('roster_interests', 2);
        $this->assertDatabaseHas('roster_interests', ['id' => $oldInterest, 'roster_id' => $latest, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-04', 'occurrence_ends_at' => '2026-10-04 21:00:00']);
        $this->assertDatabaseHas('roster_interests', ['id' => $latestInterest, 'roster_id' => $latest, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-11', 'occurrence_ends_at' => '2026-10-11 21:00:00']);
        $preserved = RosterInterest::findOrFail($oldInterest);
        $this->assertSame([$newTower], $preserved->position_ids);
        $this->assertSame(['EKCH_A_TWR', 'EKCH_APP'], $preserved->position_callsigns);
        $this->assertSame([['starts_at' => '2026-10-04T18:00', 'ends_at' => '2026-10-04T19:00']], $preserved->availability);
    }

    public function test_migrated_interest_uses_the_first_occurrence_of_a_repeated_local_end_time(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->weekly()->create([
            'timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T01:00', 'local_end' => '2026-10-18T02:30',
            'starts_at' => '2026-10-17 23:00:00', 'ends_at' => '2026-10-18 00:30:00',
        ]);
        $controller = User::factory()->create();
        $migration = $this->migration();
        $migration->down();
        $roster = $this->legacyRoster($event, '2026-10-25', 'open_interest', '2026-09-01 12:00:00');
        $position = DB::table('roster_positions')->insertGetId(['roster_id' => $roster, 'callsign' => 'EKCH_A_TWR']);
        $interest = DB::table('roster_interests')->insertGetId([
            'roster_id' => $roster, 'user_cid' => $controller->cid,
            'position_ids' => json_encode([$position], JSON_THROW_ON_ERROR),
            'availability' => json_encode([['starts_at' => '2026-10-24T23:30', 'ends_at' => '2026-10-25T00:30']], JSON_THROW_ON_ERROR),
        ]);

        $migration->up();

        $this->assertDatabaseHas('roster_interests', [
            'id' => $interest, 'roster_id' => $roster, 'user_cid' => $controller->cid,
            'occurrence_date' => '2026-10-25', 'occurrence_ends_at' => '2026-10-25 00:30:00',
        ]);
        $this->assertSame([['starts_at' => '2026-10-24T23:30', 'ends_at' => '2026-10-25T00:30']], RosterInterest::findOrFail($interest)->availability);
    }

    public function test_rollback_refuses_to_discard_populated_rosters_and_their_participation_history(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $booking = RosterBooking::factory()->create();
        $roster = EventRoster::findOrFail($booking->roster_id);

        try {
            $this->migration()->down();
            $this->fail('A populated roster must not be rolled back destructively.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot revert populated event roster templates without losing occurrence history. Use a forward migration.', $exception->getMessage());
        }

        $this->assertModelExists($roster);
        $this->assertModelExists($booking);
    }

    private function legacyRoster(Event $event, string $date, string $mode, string $updatedAt): int
    {
        return DB::table('event_rosters')->insertGetId(['event_id' => $event->id, 'occurrence_date' => $date, 'mode' => $mode, 'is_open' => true, 'opened_at' => '2026-09-01 12:00:00', 'created_at' => '2026-09-01 12:00:00', 'updated_at' => $updatedAt]);
    }

    private function legacySlot(int $shiftId, string $callsign, string $start, string $end, User $controller): int
    {
        return DB::table('roster_slots')->insertGetId(['shift_id' => $shiftId, 'callsign' => $callsign, 'starts_at' => $start, 'ends_at' => $end, 'booked_by' => $controller->cid, 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:00:00']);
    }

    /** @param list<int> $positions */
    private function legacyInterest(int $rosterId, User $controller, array $positions, string $date): int
    {
        return DB::table('roster_interests')->insertGetId(['roster_id' => $rosterId, 'user_cid' => $controller->cid, 'position_ids' => json_encode($positions, JSON_THROW_ON_ERROR), 'availability' => json_encode([['starts_at' => $date.'T18:00', 'ends_at' => $date.'T19:00']], JSON_THROW_ON_ERROR), 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:00:00']);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_09_114650_tie_roster_templates_to_events.php');
    }
}
