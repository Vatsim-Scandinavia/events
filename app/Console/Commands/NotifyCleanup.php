<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Helpers\EventHelper;
use App\Models\DiscordMessage;

class EventsCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup notifications for past events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all messages that have expired
        $messages = DiscordMessage::where('expires_at', '<=', now())->get();

        // Cleanup notifications for past events
        foreach ($messages as $message) {
            // Delete the message
            EventHelper::discordDelete($message->message_id);
            $this->info('Cleaned up message: ' . $message->message_id);
        }

        DiscordMessage::whereIn('message_id', $messages->pluck('message_id'))->delete();

        $this->info('Cleaned up ' . $messages->count() . ' past events');
    }
}
