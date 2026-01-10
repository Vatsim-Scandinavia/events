<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventInstance;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class EventService
{
    public function createEventWithInstances(array $data, $imageFile = null)
    {
        if ($imageFile) {
            $imageName = now()->format('Y-m-d') . '-' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $imageFile->storeAs('banners', $imageName, 'public');
            
            $data['image'] = $imageName;
        }

        return \DB::transaction(function () use ($data) {
            if (!isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }

            $event = Event::create($data);
            $this->generateInstances($event);
            
            return $event;
        });
    }

    public function generateInstances(Event $event)
    {
        $start = Carbon::parse($event->start_date);
        $end = Carbon::parse($event->end_date);
        
        $durationInMinutes = $start->diffInMinutes($end);

        if (!$event->recurrence_unit) {
            $event->instances()->create([
                'start_time' => $start,
                'end_time'   => $end,
            ]);
            return;
        }

        $instances = [];
        $seriesLimit = Carbon::parse($event->recurrence_end_date);
        $currentStart = $start->copy();

        while ($currentStart <= $seriesLimit) {

            $currentEnd = $currentStart->copy()->addMinutes($durationInMinutes);

            $instances[] = [
                'event_id'   => $event->id,
                'start_time' => $currentStart->toDateTimeString(),
                'end_time'   => $currentEnd->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $currentStart->add($event->recurrence_unit, (int) $event->recurrence_interval ?? 1);
        }

        EventInstance::insert($instances);
    }

    public function updateEvent(Event $event, array $data, $imageFile = null)
    {
        if ($imageFile) {
            if ($event->image) {
                \Storage::disk('public')->delete('banners/' . $event->image);
            }
            
            $imageName = now()->format('Y-m-d') . '-' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $imageFile->storeAs('banners', $imageName, 'public');
            $data['image'] = $imageName;
        }

        return \DB::transaction(function () use ($event, $data) {
            $event->update($data);

            if (!$event->recurrence_unit) {
                $event->instances()->update([
                    'start_time' => $event->start_date,
                    'end_time'   => $event->end_date,
                ]);
                return $event;
            }

            $this->syncInstances($event);

            return $event;
        });
    }

    public function deleteEvent(Event $event)
    {
        return \DB::transaction(function () use ($event) {
            $event->instances()->delete();
            $event->delete();

            return true;
        });
    }

    protected function syncInstances(Event $event)
    {
        $currentStart = \Carbon\Carbon::parse($event->start_date);
        $seriesLimit = \Carbon\Carbon::parse($event->recurrence_end_date);
        $duration = \Carbon\Carbon::parse($event->start_date)->diffInMinutes($event->end_date);

        $existingInstances = $event->instances()
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get();

        $instanceIndex = 0;

        while ($currentStart <= $seriesLimit) {
            $newStart = $currentStart->toDateTimeString();
            $newEnd = $currentStart->copy()->addMinutes($duration)->toDateTimeString();

            if (isset($existingInstances[$instanceIndex])) {
                $existingInstances[$instanceIndex]->update([
                    'start_time' => $newStart,
                    'end_time'   => $newEnd,
                ]);
            } else {
                $event->instances()->create([
                    'start_time' => $newStart,
                    'end_time'   => $newEnd,
                ]);
            }

            $currentStart->add($event->recurrence_unit, (int)$event->recurrence_interval);
            $instanceIndex++;
        }

        if ($instanceIndex < $existingInstances->count()) {
            $event->instances()
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->skip($instanceIndex)
                ->take($existingInstances->count() - $instanceIndex)
                ->delete();
        }
    }
}