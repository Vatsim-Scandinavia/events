<?php

use App\Models\IntegrationSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Event announcements moved from the core into the Discord Integration. Installs that
     * announced through DISCORD_EVENTS_WEBHOOK get it switched on, so they keep announcing.
     */
    public function up(): void
    {
        if (blank(config('integrations.settings.discord.events_webhook'))) {
            return;
        }

        $discord = IntegrationSetting::query()->firstOrNew(['key' => 'discord']);

        $discord->forceFill([
            'enabled' => true,
            'features' => array_values(array_unique([...$discord->features ?? [], 'announcements'])),
        ])->save();
    }

    /**
     * Switching it off again would stop announcements the admin may since have chosen.
     */
    public function down(): void
    {
        //
    }
};
