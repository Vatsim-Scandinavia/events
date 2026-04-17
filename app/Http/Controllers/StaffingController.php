<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Staffing;
use App\Models\StaffingSection;
use App\Models\StaffingPosition;
use App\Http\Resources\EventResource;
use App\Services\ControlCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StaffingController extends Controller
{
    /**
     * Show the staffing management page for an event.
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        $event->load(['calendar', 'occurrences', 'futureOccurrences', 'staffing.sections.positions']);

        return Inertia::render('Events/ManageStaffing', [
            'event'       => new EventResource($event),
            'ccPositions' => app(ControlCenterService::class)->getPositions(),
        ]);
    }

    /**
     * Save the full staffing structure for an event.
     * Replaces all sections and positions with the submitted data.
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'sections'                                => ['required', 'array'],
            'sections.*.title'                        => ['required', 'string', 'max:255'],
            'sections.*.positions'                    => ['required', 'array'],
            'sections.*.positions.*.position_id'      => ['required', 'string', 'max:50'],
            'sections.*.positions.*.position_name'    => ['required', 'string', 'max:255'],
            'sections.*.positions.*.start_time'       => ['nullable', 'date_format:H:i'],
            'sections.*.positions.*.end_time'         => ['nullable', 'date_format:H:i'],
            'sections.*.positions.*.is_local_booking' => ['boolean'],
        ]);

        // For non-local positions, the callsign must exist in Control Center.
        $ccCallsigns = collect(app(ControlCenterService::class)->getPositions())
            ->pluck('callsign')
            ->map(fn($c) => strtoupper($c))
            ->all();

        $ccErrors = [];
        foreach ($validated['sections'] as $si => $section) {
            foreach ($section['positions'] as $pi => $pos) {
                $isLocal = filter_var($pos['is_local_booking'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (!$isLocal && !in_array(strtoupper($pos['position_id']), $ccCallsigns, true)) {
                    $ccErrors["sections.{$si}.positions.{$pi}.position_id"] =
                        "'{$pos['position_id']}' is not a known Control Center position. Tick 'Local only' to use a custom callsign.";
                }
            }
        }

        if (!empty($ccErrors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($ccErrors);
        }

        DB::transaction(function () use ($event, $validated) {
            $staffing = Staffing::firstOrCreate(['event_id' => $event->id]);

            // Clear existing sections (cascades to positions via FK)
            $staffing->sections()->delete();

            foreach ($validated['sections'] as $sectionOrder => $sectionData) {
                $section = StaffingSection::create([
                    'staffing_id' => $staffing->id,
                    'title'       => $sectionData['title'],
                    'order'       => $sectionOrder,
                ]);

                foreach ($sectionData['positions'] as $positionOrder => $positionData) {
                    StaffingPosition::create([
                        'section_id'    => $section->id,
                        'position_id'   => strtoupper(trim($positionData['position_id'])),
                        'position_name' => $positionData['position_name'],
                        'start_time'    => $positionData['start_time'] ?: null,
                        'end_time'      => $positionData['end_time'] ?: null,
                        'is_local_booking' => $positionData['is_local_booking'] ?? false,
                        'order'         => $positionOrder,
                    ]);
                }
            }
        });

        return redirect()->route('events.show', $event)
            ->with('success', 'Staffing saved successfully.');
    }
}
