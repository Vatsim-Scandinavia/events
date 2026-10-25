<?php

namespace Tests\Feature;

use App\Enums\IntegrationRunStatus;
use App\Enums\StaffingMode;
use App\Integrations\RunSync;
use App\Models\CataloguePosition;
use App\Models\Event;
use App\Models\Fir;
use App\Models\IntegrationRun;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ControlCenterPositionSyncTest extends TestCase
{
    use RefreshDatabase;

    private const string POSITIONS_URL = 'cc.example/api/v1/positions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['integrations.settings.control-center' => []]);
        IntegrationSetting::query()->create([
            'key' => 'control-center',
            'enabled' => true,
            'features' => ['positions'],
            'settings' => ['url' => 'https://cc.example', 'token' => 'cc-key', 'areas' => 'denmark = EKDK'],
            'removal_limit' => 50,
        ]);
    }

    public function test_a_sync_adds_updates_and_removes_positions_to_match_control_center(): void
    {
        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.100');
        $this->catalogue('EKCH_DEL', 'Kastrup Delivery', '119.905');
        $this->catalogue('ESSA_TWR', 'Arlanda Tower', '118.500');
        Http::fake([self::POSITIONS_URL => Http::response(['data' => [
            ['callsign' => 'EKCH_TWR', 'name' => 'Kastrup Tower', 'frequency' => '118.105'],
            ['callsign' => 'EKCH_DEL', 'name' => 'Kastrup Delivery', 'frequency' => '119.905'],
            ['callsign' => 'ekch_app', 'name' => 'Copenhagen Approach', 'frequency' => '119.800'],
        ]])]);

        $this->sync();

        $this->assertSame(
            [['EKCH_APP', 'Copenhagen Approach', '119.800'], ['EKCH_DEL', 'Kastrup Delivery', '119.905'], ['EKCH_TWR', 'Kastrup Tower', '118.105']],
            CataloguePosition::query()->orderBy('callsign')->get()->map(fn (CataloguePosition $position) => [$position->callsign, $position->name, $position->frequency])->all(),
        );
        $run = $this->lastRun();
        $this->assertSame(IntegrationRunStatus::Succeeded, $run->status);
        $this->assertSame([1, 1, 1], [$run->added, $run->updated, $run->removed]);
    }

    public function test_positions_that_are_not_callsigns_are_skipped_and_odd_frequencies_dropped(): void
    {
        Http::fake([self::POSITIONS_URL => Http::response(['data' => [
            ['callsign' => 'EKCH_TWR', 'name' => 'Kastrup Tower', 'frequency' => '118,105'],
            ['callsign' => 'Kastrup', 'name' => 'Not a callsign', 'frequency' => null],
        ]])]);

        $this->sync();

        $this->assertNull(CataloguePosition::query()->sole()->frequency);
        $this->assertSame(['Skipped positions that aren’t callsigns: Kastrup.'], $this->lastRun()->warnings);
    }

    public function test_a_failed_fetch_changes_nothing(): void
    {
        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.105');
        Http::fake([self::POSITIONS_URL => Http::response(['data' => [['name' => 'No callsign']]])]);

        $this->sync();

        $this->assertSame(IntegrationRunStatus::Failed, $this->lastRun()->status);
        $this->assertSame('Control Center sent something the Hub couldn’t read.', $this->lastRun()->message);
        $this->assertSame(['EKCH_TWR'], CataloguePosition::query()->pluck('callsign')->all());
    }

    public function test_a_sync_removing_more_than_the_limit_is_held(): void
    {
        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.105');
        $this->catalogue('EKCH_APP', 'Copenhagen Approach', '119.800');
        $this->catalogue('EKCH_DEL', 'Kastrup Delivery', '119.905');
        Http::fake([self::POSITIONS_URL => Http::response(['data' => [['callsign' => 'EKCH_TWR', 'name' => 'Kastrup Tower', 'frequency' => '118.105']]])]);

        $this->sync();

        $this->assertSame(IntegrationRunStatus::Held, $this->lastRun()->status);
        $this->assertSame('It would remove 2 of 3 positions. Check Control Center, or approve to sync anyway.', $this->lastRun()->message);
        $this->assertSame(3, CataloguePosition::query()->count());

        $this->sync(approvedBy: User::factory()->admin()->create()->id);
        $this->assertSame(['EKCH_TWR'], CataloguePosition::query()->pluck('callsign')->all());
    }

    public function test_switching_it_off_empties_the_catalogue(): void
    {
        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.105');
        Fir::factory()->create(['icao' => 'EKDK']);
        Http::fake(['cc.example/api/v1/users' => Http::response(['data' => []])]);

        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/control-center', [
            'enabled' => true,
            'features' => [],
            'removal_limit' => 50,
            'settings' => ['url' => 'https://cc.example', 'token' => '', 'areas' => 'denmark = EKDK'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, CataloguePosition::query()->count());
    }

    public function test_the_staffing_planner_suggests_catalogue_positions(): void
    {
        $event = Event::factory()->withShifts()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get("/events/{$event->id}/staffing")->assertInertia(fn (Assert $page) => $page->where('catalogue', []));

        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.105');
        $this->catalogue('EKCH_APP', 'Copenhagen Approach', null);

        $this->actingAs($staff)->get("/events/{$event->id}/staffing")->assertInertia(fn (Assert $page) => $page->where('catalogue', [
            ['callsign' => 'EKCH_APP', 'name' => 'Copenhagen Approach', 'frequency' => null],
            ['callsign' => 'EKCH_TWR', 'name' => 'Kastrup Tower', 'frequency' => '118.105'],
        ]));
    }

    public function test_event_positions_keep_their_own_copy_and_free_typing_still_works(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $this->catalogue('EKCH_TWR', 'Kastrup Tower', '118.105');
        $event = Event::factory()->create([
            'staffing_mode' => StaffingMode::Shifts,
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);

        $this->actingAs(User::factory()->staff()->create())->put("/events/{$event->id}/staffing/plan", [
            'occurrence_id' => $event->occurrences()->sole()->id,
            'positions' => [['key' => -1, 'callsign' => 'EKCH_TWR', 'frequency' => '118.105'], ['key' => -2, 'callsign' => 'EKDK_X_CTR']],
            'shifts' => [],
        ])->assertSessionHasNoErrors();

        Http::fake([self::POSITIONS_URL => Http::response(['data' => [['callsign' => 'EKCH_TWR', 'name' => 'Kastrup Tower', 'frequency' => '118.300']]])]);
        $this->sync();
        $this->assertSame('118.300', CataloguePosition::query()->sole()->frequency);

        $this->assertSame(
            ['EKCH_TWR' => '118.105', 'EKDK_X_CTR' => null],
            $event->positions()->pluck('frequency', 'callsign')->all(),
        );
    }

    private function catalogue(string $callsign, string $name, ?string $frequency): void
    {
        CataloguePosition::query()->create(['callsign' => $callsign, 'name' => $name, 'frequency' => $frequency, 'integration' => 'control-center']);
    }

    private function sync(?int $approvedBy = null): void
    {
        app()->call([new RunSync('control-center', 'positions', $approvedBy), 'handle']);
    }

    private function lastRun(): IntegrationRun
    {
        return IntegrationRun::query()->latest('id')->firstOrFail();
    }
}
