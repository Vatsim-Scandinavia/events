<?php

namespace Database\Seeders;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Seed the VATSIM Scandinavia FIRs, and sample events relative to today so there is
     * always something upcoming. All are published but the last, which stays a Draft.
     */
    public function run(): void
    {
        $this->call(FirSeeder::class);

        $firs = Fir::query()->pluck('id', 'icao');
        $types = EventType::query()->pluck('id', 'name');

        $events = [
            ['ESAA', 'Cross the Pond Eastbound', 'Special event', ['KJFK', 'EGLL'], 9, '10:00', 12],
            ['EKDK', 'Scandinavian Night Ops', 'Regional', ['EKCH', 'ESSA', 'ENGM', 'EFHK', 'BIKF', 'ENBR'], 15, '18:00', 4],
            ['EFIN', 'Alpine Approach Challenge', 'Training', ['LSZH', 'LOWI'], 17, '14:00', 3],
            ['EKDK', 'Copenhagen Fly-in', 'Fly-in', ['EKCH'], 23, '16:00', 4],
            ['EKDK', 'Billund Group Flight', 'Group flight', ['EKBI', 'EKAH'], 31, '17:00', 3],
            ['ENOR', 'Nordic Overload', 'Regional', ['EKCH', 'ESSA', 'ENGM', 'EFHK'], 44, '15:00', 6],
            ['BIRD', 'Winter Wonderland Fly-in', 'Fly-in', ['BIKF', 'BGBW'], 58, '13:00', 5],
        ];

        foreach ($events as [$fir, $name, $type, $airports, $inDays, $start, $hours]) {
            $startsAt = Carbon::today('UTC')->addDays($inDays)->setTimeFromTimeString($start);

            $details = $this->details()[$name] ?? [];
            $positions = $details['positions'] ?? [];
            unset($details['positions']);

            $event = Event::create([
                'fir_id' => $firs[$fir],
                'name' => $name,
                'event_type_id' => $types[$type],
                'airports' => $airports,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours($hours),
                ...$details,
            ]);

            foreach ($positions as $sort => $position) {
                $event->positions()->create([...$position, 'sort' => $sort]);
            }

            if ($name !== 'Winter Wonderland Fly-in') {
                $event->publish();
            }
        }

        $this->setUpStaffing();
    }

    /**
     * Copenhagen Fly-in takes shift bookings and Scandinavian Night Ops takes open interest,
     * so both staffing modes have something to try out locally.
     */
    private function setUpStaffing(): void
    {
        $flyIn = Event::where('name', 'Copenhagen Fly-in')->firstOrFail();
        $flyIn->update(['staffing_mode' => StaffingMode::Shifts]);

        foreach ($flyIn->positions as $position) {
            foreach ([[0, 120], [120, 240]] as [$from, $until]) {
                $position->shifts()->create(['starts_offset' => $from, 'ends_offset' => $until]);
            }
        }

        // The night ops window opened a few days ago, so interest can be shown straight away.
        Event::where('name', 'Scandinavian Night Ops')->firstOrFail()->update([
            'staffing_mode' => StaffingMode::OpenInterest,
            'interest_opens_hours' => 21 * 24,
            'interest_closes_hours' => 24,
        ]);
    }

    /**
     * Briefing details for the events that have them; the rest fall back to the bare listing.
     *
     * @return array<string, array<string, mixed>>
     */
    private function details(): array
    {
        return [
            'Cross the Pond Eastbound' => [
                'description' => "The biggest crossing of the year. Hundreds of pilots fly from New York to London under full ATC coverage on both sides of the Atlantic.\n\nSlots are required for departures from KJFK. Make sure you have your oceanic clearance before reaching your entry fix.\n\nOceanic clearances are issued by Gander Oceanic. Request yours at least 30 minutes before your entry point, stating your entry fix, estimated time over it, requested flight level and Mach number. Expect to be cleared on the track assigned in your booking.\n\nOn arrival into London, expect holding at BIGGIN, BOVINGDON, LAMBOURNE or OCKHAM depending on your routing. Keep your speed up until told otherwise and have the arrival charts ready before top of descent.\n\nPlease connect with your booked callsign and departure slot. Pilots without a slot may still fly but should expect delays on the ground.",
                'routes' => [
                    ['from' => 'KJFK', 'to' => 'EGLL', 'route' => 'MERIT HFD PUT BOS SSM 53N050W 54N040W 54N030W 54N020W DOGAL NEVRI OLAPO'],
                ],
                'positions' => [
                    ['callsign' => 'KJFK_TWR', 'frequency' => '119.100'],
                    ['callsign' => 'NY_CTR', 'frequency' => '125.325'],
                    ['callsign' => 'CZQX_FSS', 'frequency' => '134.700'],
                    ['callsign' => 'EGGX_FSS', 'frequency' => '127.650'],
                    ['callsign' => 'LON_S_CTR', 'frequency' => '129.425'],
                    ['callsign' => 'EGLL_N_APP', 'frequency' => '119.725'],
                ],
                'booking_url' => 'https://ctp.vatsim.net',
                'briefing_url' => 'https://ctp.vatsim.net',
            ],
            'Scandinavian Night Ops' => [
                'description' => "An evening tour of the Nordic capitals with approach and tower staffed at every field. Fly a single leg or link them all together.\n\nExpect busy frequencies during the first hour and plan for winter conditions.",
                'routes' => [
                    ['from' => 'EKCH', 'to' => 'ESSA', 'route' => 'SIMEG T317 ARS'],
                    ['from' => 'ESSA', 'to' => 'ENGM', 'route' => 'ABENI T333 ADOPI'],
                    ['from' => 'ENGM', 'to' => 'EKCH', 'route' => 'OSLOB M609 TUDLO'],
                    ['from' => 'EFHK', 'to' => 'ESSA', 'route' => 'RENTA M854 ELPUX'],
                ],
                'positions' => [
                    ['callsign' => 'EKCH_TWR', 'frequency' => '118.105'],
                    ['callsign' => 'EKCH_APP', 'frequency' => '119.805'],
                    ['callsign' => 'EKDK_CTR', 'frequency' => '136.485'],
                    ['callsign' => 'ESSA_TWR', 'frequency' => '118.505'],
                    ['callsign' => 'ENGM_TWR', 'frequency' => '118.305'],
                    ['callsign' => 'EFHK_APP', 'frequency' => '119.105'],
                ],
            ],
            'Copenhagen Fly-in' => [
                'description' => "Full ground-to-centre coverage at Copenhagen Kastrup. Bring any aircraft, fly any route in, and enjoy a fully staffed EKCH.\n\nPlease file your flight plan before calling for clearance.",
                'routes' => [
                    ['from' => 'EKBI', 'to' => 'EKCH', 'route' => 'TUDLO'],
                    ['from' => 'EDDH', 'to' => 'EKCH', 'route' => 'TUDLO'],
                    ['from' => 'ESSA', 'to' => 'EKCH', 'route' => 'ARS T317 SIMEG'],
                ],
                'positions' => [
                    ['callsign' => 'EKCH_DEL', 'frequency' => '119.905'],
                    ['callsign' => 'EKCH_GND', 'frequency' => '121.905'],
                    ['callsign' => 'EKCH_TWR', 'frequency' => '118.105'],
                    ['callsign' => 'EKCH_APP', 'frequency' => '119.805'],
                    ['callsign' => 'EKDK_CTR', 'frequency' => '136.485'],
                ],
                'booking_url' => 'https://www.vatsim.net/events',
            ],
        ];
    }
}
