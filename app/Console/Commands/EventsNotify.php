<?php

namespace App\Console\Commands;

use App\Helpers\EventHelper;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled command to send Discord notifications for upcoming events.
 *
 * Checks for events starting within the next 2 hours and sends
 * announcement messages to configured Discord webhooks for public calendars.
 * Marks events as published to avoid duplicate notifications.
 */
class EventsNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Discord notifications for events starting within 2 hours';

    /**
     * Execute the console command.
     *
     * Finds unpublished events starting within the next 2 hours on public calendars,
     * sends Discord webhook notifications with event details and banner image,
     * and marks them as published to prevent duplicate notifications.
     *
     * @return int Command exit code (0 = success)
     */
    public function handle()
    {
        $events = Event::where('start_date', '>=', now())
            ->where('start_date', '<=', now()->addHours(2))
            ->where('published', false)
            ->get();

        $notifiedCount = 0;

        foreach ($events as $event) {
            if (!$event->calendar->public) {
                $this->info('Skipping event: '.$event->title.' (private calendar)');
                continue;
            }

            try {
                $result = EventHelper::discordPost(
                    $event->id,
                    EventHelper::discordMention()."\n:clock2: **".$event->title.'** is starting in two hours!',
                    $event->title,
                    $event->long_description,
                    asset('storage/banners/' . $event->image),
                    Carbon::parse($event->start_date),
                    Carbon::parse($event->end_date)
                );

                if ($result) {
                    $event->published = true;
                    $event->save();
                    $notifiedCount++;
                    $this->info('Notified about event: '.$event->title);
                } else {
                    $this->error('Failed to notify about event: '.$event->title);
                }
            } catch (\Exception $e) {
                $this->error('Failed to notify about event: '.$event->title.' - '.$e->getMessage());
                Log::error('Event notification failed', [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'exception' => $e->getMessage()
                ]);
            }
        }

        $this->info("Notified about {$notifiedCount} out of {$events->count()} upcoming events");
        return 0;
    }
}
