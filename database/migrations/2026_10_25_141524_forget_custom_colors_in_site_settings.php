<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Themes now come with their own colors, so the hero gradient and Brand colors admins
     * picked are forgotten. The only theme stored so far was the Brand theme, which is gone.
     */
    public function up(): void
    {
        DB::table('site_settings')->whereIn('key', ['hero_gradient', 'brand_colors', 'theme'])->delete();

        Cache::forget('site-settings');
    }

    /**
     * The forgotten colors can't be brought back.
     */
    public function down(): void
    {
        //
    }
};
