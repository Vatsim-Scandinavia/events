<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:ensure-key')]
#[Description('Generate an application key only when no key is configured')]
class EnsureApplicationKey extends Command
{
    public function handle(): int
    {
        $configuredKey = config('app.key');

        if (filled($configuredKey)) {
            $this->info('Application key already configured.');

            return self::SUCCESS;
        }

        $exitCode = $this->call('key:generate', ['--no-interaction' => true]);

        return $exitCode === self::SUCCESS && filled(config('app.key'))
            ? self::SUCCESS
            : self::FAILURE;
    }
}
