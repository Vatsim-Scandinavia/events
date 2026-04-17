<?php

namespace App\Console\Commands;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;

class CheckEnv extends Command
{
    protected $signature = 'env:check';

    protected $description = 'Verify all required environment variables are set';

    public function handle(): int
    {
        try {
            /** @var AppServiceProvider $provider */
            $provider = app()->getProvider(AppServiceProvider::class);
            $provider->validateRequiredConfig();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('All required environment variables are set.');
        return self::SUCCESS;
    }
}
