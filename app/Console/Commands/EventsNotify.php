<?php

namespace App\Console\Commands;

use App\Helpers\EventHelper;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
    protected $description = 'Notify about upcoming events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all events that will start within the next 2 hours
        $events = Event::where('start_date', '>=', now())
            ->where('start_date', '<=', now()->addHours(2))
            ->where('published', false)
            ->get();

        // Notify about upcoming events
        foreach ($events as $event) {

            $result = EventHelper::discordPost(
                EventHelper::discordMention()."\n:clock2: **".$event->title.'** is starting in two hours!',
                $event->title,
                $event->long_description,
                asset('storage/banners/' . $event->image),
                Carbon::parse($event->start_date),
                Carbon::parse($event->end_date)
            );

            // Save the message as published
            if ($result) {
                $event->published = true;
                $event->save();
            } else {
                $this->error('Failed to notify about event: '.$event->title);
            }

            $this->info('Notified about event: '.$event->title);
        }

        $this->info('Notified about '.$events->count().' upcoming events');
    }
}
