<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventInstance;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class EventService
{
    public function createEventWithInstances(array $data, $imageFile = null): Event
    {
        if($imageFile) {
            $data['image'] = $this->uploadBanner($imageFile);
        }

        return DB::transaction(function () use ($data) {
            $event = Event::create($data);
            $this->generateInstances($event, $data);
            return $event;
        });
    }

    public function updateEvent(Event $event, array $data, $imageFile = null): Event
    {
        if ($imageFile) {
            $this->deleteBanner($event->image);
            $data['image'] = $this->uploadBanner($imageFile);
        }

        return DB::transaction(function () use ($event, $data) {
            $event->update($data);
            
            if (!$event->recurrence_unit) {
                $event->instances()->first()?->update([
                    'start_time' => $data['start_date'],
                    'end_time'   => $data['end_date'],
                ]);
            } else {
                $this->syncInstances($event, $data);
            }

            return $event;
        });
    }

    public function generateInstances(Event $event, array $data): void
    {
        $start = $data['start_date'];
        $end = $data['end_date'];
        $duration = $start->diffInMinutes($end);

        if (!$event->recurrence_unit) {
            $event->instances()->create([
                'start_time' => $start,
                'end_time'   => $end,
            ]);
            return;
        }

        $instances = [];
        $seriesLimit = Carbon::parse($event->recurrence_end_date);
        $current = $start->copy();

        while ($current <= $seriesLimit) {
            $instances[] = [
                'event_id'   => $event->id,
                'start_time' => $current->toDateTimeString(),
                'end_time'   => $current->copy()->addMinutes($duration)->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $current->add($event->recurrence_unit, (int) $event->recurrence_interval);
        }

        EventInstance::insert($instances);
    }

    protected function syncInstances(Event $event, array $data): void
    {
        $newStart = $data['start_date'];
        $duration = $newStart->diffInMinutes($data['end_date']);

        $event->instances()->where('start_time', '>=', $newStart)->whereDoesntHave('staffing')->forceDelete();

        $excludedDates = $event->instances()
            ->onlyTrashed()
            ->pluck('start_time')
            ->map(fn($date) => \Carbon\Carbon::parse($date)->toDateTimeString())
            ->toArray();

        $limit = \Carbon\Carbon::parse($data['recurrence_end_date'] ?? $event->recurrence_end_date);
        $current = $newStart->copy();
        $batch = [];

        $interval = (int) ($data['recurrence_interval'] ?? $event->recurrence_interval) ?: 1;
        $unit = $data['recurrence_unit'] ?? $event->recurrence_unit;

        while ($current <= $limit) {
            $currentTimeString = $current->toDateTimeString();

            if (!in_array($currentTimeString, $excludedDates)) {
                $batch[] = [
                    'event_id'   => $event->id,
                    'start_time' => $currentTimeString,
                    'end_time'   => $current->copy()->addMinutes($duration)->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $current->add($unit, $interval);

            // Safety break
            if (count($batch) >= 500) break;
        }

        if (!empty($batch)) {
            \App\Models\EventInstance::insert($batch);
        }
    }

    private function uploadBanner($file): string
    {
        $name = now()->format('Y-m-d') . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('banners', $name, 'public') ? $name : '';
    }

    private function deleteBanner($filename): void
    {
        if ($filename && \Illuminate\Support\Facades\Storage::disk('public')->exists('banners/' . $filename)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete('banners/' . $filename);
        }
    }

    public function deleteEvent(Event $event): bool
    {
        return DB::transaction(function () use ($event) {
            $event->instances()->delete();
            $this->deleteBanner($event->image);
            return $event->delete();
        });
    }

    public function getNextInstance(int $eventId)
    {
        return EventInstance::where('event_id', $eventId)
            ->where('start_time', '>', now()) // Ensure the future instance start_time is strictly > setTestNow()
            ->orderBy('start_time', 'asc')
            ->first();
    }
}