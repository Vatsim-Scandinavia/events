<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Service class for managing event lifecycle and instance generation.
 *
 * Handles event creation, updates, deletion, and the complex logic
 * of generating recurring event instances based on recurrence rules.
 */
class EventService
{
    /**
     * Creates a new event with its associated instances.
     *
     * For single events, creates one instance. For recurring events,
     * generates multiple instances based on recurrence rules.
     * All operations are wrapped in a database transaction.
     *
     * @param array $data Event data including title, dates, recurrence settings
     * @param \Illuminate\Http\UploadedFile|null $imageFile Optional event banner image
     * @return Event The created event with instances
     * @throws \Exception If transaction fails
     */
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

    /**
     * Updates an existing event and synchronizes its instances.
     *
     * For single events, updates the single instance dates.
     * For recurring events, removes old instances (without staffing) and
     * regenerates them based on the new recurrence rules.
     *
     * @param Event $event The event to update
     * @param array $data Updated event data
     * @param \Illuminate\Http\UploadedFile|null $imageFile Optional new banner image
     * @return Event The updated event
     * @throws \Exception If transaction fails
     */
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

    /**
     * Generates event instances based on recurrence rules.
     *
     * For single events, creates one instance.
     * For recurring events, generates instances from start_date to recurrence_end_date
     * at intervals specified by recurrence_unit and recurrence_interval.
     *
     * Example: recurrence_unit='week', recurrence_interval=2 creates bi-weekly instances.
     *
     * @param Event $event The parent event
     * @param array $data Event data containing start_date, end_date, recurrence settings
     * @return void
     */
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

    /**
     * Synchronizes existing instances with updated recurrence rules.
     *
     * Removes future instances that don't have staffing attached,
     * respects soft-deleted instances (manually removed dates),
     * and generates new instances based on updated rules.
     *
     * @param Event $event The event being updated
     * @param array $data New event data with updated dates/recurrence
     * @return void
     */
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

            if (count($batch) >= 500) {
                break; // Safety limit to prevent infinite loops or memory issues
            }
        }

        if (!empty($batch)) {
            \App\Models\EventInstance::insert($batch);
        }
    }

    /**
     * Uploads an event banner image to public storage.
     *
     * Generates a unique filename with date prefix and stores in banners directory.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded image file
     * @return string The stored filename (not full path)
     */
    private function uploadBanner($file): string
    {
        $name = now()->format('Y-m-d') . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('banners', $name, 'public') ? $name : '';
    }

    /**
     * Deletes an event banner image from public storage.
     *
     * @param string|null $filename The banner filename to delete
     * @return void
     */
    private function deleteBanner(?string $filename): void
    {
        if ($filename && Storage::disk('public')->exists('banners/' . $filename)) {
            Storage::disk('public')->delete('banners/' . $filename);
        }
    }

    /**
     * Deletes an event and all its instances.
     *
     * Soft deletes the event and its instances, and removes the banner image.
     * All operations are wrapped in a database transaction.
     *
     * @param Event $event The event to delete
     * @return bool True on success
     * @throws \Exception If transaction fails
     */
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