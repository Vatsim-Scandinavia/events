<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('roster_id')->constrained('event_rosters')->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('roster_slots')->nullOnDelete();
            $table->unsignedBigInteger('user_cid');
            $table->foreign('user_cid')->references('cid')->on('users')->restrictOnDelete();
            $table->date('occurrence_date');
            $table->string('callsign', 30);
            $table->string('shift_name', 100);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->unique(['slot_id', 'occurrence_date']);
            $table->index(['roster_id', 'occurrence_date']);
            $table->index(['user_cid', 'starts_at', 'ends_at']);
        });
        Schema::table('roster_slots', function (Blueprint $table): void {
            $table->unsignedInteger('start_day_offset')->default(0);
            $table->string('start_time', 5)->default('00:00');
            $table->unsignedInteger('end_day_offset')->default(0);
            $table->string('end_time', 5)->default('00:00');
        });
        Schema::table('roster_interests', function (Blueprint $table): void {
            $table->dropUnique(['roster_id', 'user_cid']);
            $table->date('occurrence_date')->nullable();
            $table->json('position_callsigns')->nullable();
            $table->dateTime('occurrence_ends_at')->nullable();
        });

        DB::transaction(function (): void {
            foreach (DB::table('event_rosters')->select('event_id')->distinct()->orderBy('event_id')->get() as $group) {
                $event = DB::table('events')->where('id', $group->event_id)->first();
                $rosters = DB::table('event_rosters')->where('event_id', $group->event_id)->orderByDesc('updated_at')->orderByDesc('id')->get();
                $canonical = $rosters->first();
                $canonicalSlots = [];
                $canonicalPositions = DB::table('roster_positions')->where('roster_id', $canonical->id)->pluck('id', 'callsign')->all();
                $openedAt = $rosters->whereNotNull('opened_at')->min('opened_at');

                foreach ($rosters as $roster) {
                    $date = CarbonImmutable::parse($roster->occurrence_date, 'UTC');
                    $slots = DB::table('roster_slots')->join('roster_shifts', 'roster_shifts.id', '=', 'roster_slots.shift_id')
                        ->where('roster_shifts.roster_id', $roster->id)
                        ->select('roster_slots.*', 'roster_shifts.name as shift_name')->get();

                    foreach ($slots as $slot) {
                        $start = CarbonImmutable::parse($slot->starts_at, 'UTC')->setTimezone($event->timezone);
                        $end = CarbonImmutable::parse($slot->ends_at, 'UTC')->setTimezone($event->timezone);
                        $times = [
                            'start_day_offset' => (int) $date->diffInDays(CarbonImmutable::parse($start->toDateString(), 'UTC')),
                            'start_time' => $start->format('H:i'),
                            'end_day_offset' => (int) $date->diffInDays(CarbonImmutable::parse($end->toDateString(), 'UTC')),
                            'end_time' => $end->format('H:i'),
                        ];
                        DB::table('roster_slots')->where('id', $slot->id)->update($times);
                        $signature = json_encode([$slot->shift_name, $slot->callsign, $times], JSON_THROW_ON_ERROR);
                        if ($roster->id === $canonical->id) {
                            $canonicalSlots[$signature] = $slot->id;
                        }
                        if ($slot->booked_by !== null) {
                            DB::table('roster_bookings')->insert([
                                'roster_id' => $canonical->id, 'slot_id' => $canonicalSlots[$signature] ?? null,
                                'user_cid' => $slot->booked_by, 'occurrence_date' => $roster->occurrence_date,
                                'callsign' => $slot->callsign, 'shift_name' => $slot->shift_name,
                                'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at,
                                'created_at' => $slot->created_at, 'updated_at' => $slot->updated_at,
                            ]);
                        }
                    }

                    $endOffset = (int) CarbonImmutable::parse(substr($event->local_start, 0, 10), 'UTC')
                        ->diffInDays(CarbonImmutable::parse(substr($event->local_end, 0, 10), 'UTC'));
                    $occurrenceEnd = $this->resolveOccurrenceEnd(
                        $date->addDays($endOffset)->toDateString().substr($event->local_end, 10),
                        $event->timezone,
                    );
                    foreach (DB::table('roster_interests')->where('roster_id', $roster->id)->get() as $interest) {
                        $ids = json_decode($interest->position_ids, true, flags: JSON_THROW_ON_ERROR);
                        $callsigns = DB::table('roster_positions')->whereIn('id', $ids)->orderBy('id')->pluck('callsign')->all();
                        $mappedIds = array_values(array_filter(array_map(fn (string $callsign): ?int => $canonicalPositions[$callsign] ?? null, $callsigns)));
                        DB::table('roster_interests')->where('id', $interest->id)->update([
                            'roster_id' => $canonical->id, 'occurrence_date' => $roster->occurrence_date,
                            'position_ids' => json_encode($mappedIds, JSON_THROW_ON_ERROR),
                            'position_callsigns' => json_encode($callsigns, JSON_THROW_ON_ERROR),
                            'occurrence_ends_at' => $occurrenceEnd,
                        ]);
                    }
                    if ($roster->id !== $canonical->id) {
                        DB::table('event_rosters')->where('id', $roster->id)->delete();
                    }
                }
                DB::table('event_rosters')->where('id', $canonical->id)->update(['opened_at' => $openedAt]);
            }
        });

        Schema::table('event_rosters', function (Blueprint $table): void {
            $table->dropUnique(['event_id', 'occurrence_date']);
            $table->dropColumn('occurrence_date');
            $table->unique('event_id');
        });
        $bookingSlotIds = DB::table('roster_bookings')->whereNotNull('slot_id')->pluck('slot_id', 'id')->all();
        Schema::table('roster_slots', function (Blueprint $table): void {
            $table->dropForeign(['booked_by']);
            $table->dropIndex(['booked_by', 'starts_at', 'ends_at']);
            $table->dropColumn(['starts_at', 'ends_at', 'booked_by']);
        });
        /** SQLite rebuilds roster_slots for foreign-key changes, firing the booking null-on-delete constraint. */
        foreach ($bookingSlotIds as $bookingId => $slotId) {
            DB::table('roster_bookings')->where('id', $bookingId)->update(['slot_id' => $slotId]);
        }
        Schema::table('roster_interests', function (Blueprint $table): void {
            $table->date('occurrence_date')->nullable(false)->change();
            $table->json('position_callsigns')->nullable(false)->change();
            $table->dateTime('occurrence_ends_at')->nullable(false)->change();
            $table->unique(['roster_id', 'occurrence_date', 'user_cid'], 'roster_interests_occurrence_user_unique');
        });
    }

    /** Preserve the event schedule's first chronological occurrence of an ambiguous local time. */
    private function resolveOccurrenceEnd(string $local, string $timezone): string
    {
        $wall = CarbonImmutable::parse($local, 'UTC');
        $transitions = (new DateTimeZone($timezone))->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800);
        $candidates = [];
        foreach (array_unique(array_column($transitions ?: [], 'offset')) as $offset) {
            $candidate = $wall->subSeconds($offset);
            if ($candidate->setTimezone($timezone)->format('Y-m-d\TH:i') === $local) {
                $candidates[] = $candidate;
            }
        }
        usort($candidates, fn (CarbonImmutable $left, CarbonImmutable $right): int => $left->getTimestamp() <=> $right->getTimestamp());
        if ($candidates === []) {
            throw new RuntimeException('Cannot migrate a roster with an occurrence end that does not exist in its timezone.');
        }

        return $candidates[0]->format('Y-m-d H:i:s');
    }

    /** Populated templates require a forward migration to preserve occurrence history. */
    public function down(): void
    {
        if (DB::table('event_rosters')->exists()) {
            throw new RuntimeException('Cannot revert populated event roster templates without losing occurrence history. Use a forward migration.');
        }

        Schema::dropIfExists('roster_bookings');
        Schema::table('roster_interests', function (Blueprint $table): void {
            $table->dropUnique('roster_interests_occurrence_user_unique');
            $table->dropColumn(['occurrence_date', 'position_callsigns', 'occurrence_ends_at']);
            $table->unique(['roster_id', 'user_cid']);
        });
        Schema::table('roster_slots', function (Blueprint $table): void {
            $table->dropColumn(['start_day_offset', 'start_time', 'end_day_offset', 'end_time']);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->foreign('booked_by')->references('cid')->on('users')->restrictOnDelete();
            $table->index(['booked_by', 'starts_at', 'ends_at']);
        });
        Schema::table('event_rosters', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->date('occurrence_date');
            $table->unique(['event_id', 'occurrence_date']);
        });
    }
};
