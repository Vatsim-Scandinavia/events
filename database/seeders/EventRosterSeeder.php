<?php

namespace Database\Seeders;

use App\Actions\RecordAudit;
use App\Models\EventRoster;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventRosterSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $slotted = EventRoster::factory()->has(
                RosterShift::factory()->state(['name' => 'Main'])->has(RosterSlot::factory()->count(2), 'slots'),
                'shifts',
            )->create();
            $interest = EventRoster::factory()->openInterest()->has(RosterPosition::factory()->count(2), 'positions')->create();

            foreach ([$slotted, $interest] as $roster) {
                app(RecordAudit::class)->handle($roster->event, 'created', [], $roster->event->auditValues());
                app(RecordAudit::class)->handle($roster, 'created', [], $roster->auditValues());
            }
        });
    }
}
