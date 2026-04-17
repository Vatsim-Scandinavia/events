<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class CreateApiKey extends Command
{
    protected $signature = 'api-key:create {name : A descriptive name for the key (e.g. discord-bot)} {--read-only : Restrict this key to read-only (GET) requests}';

    protected $description = 'Create a new API key and display it once';

    public function handle(): int
    {
        $name     = $this->argument('name');
        $readOnly = $this->option('read-only');

        // Cryptographically random 32-byte token encoded as 64 hex characters.
        $plain = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        ApiKey::create([
            'name'      => $name,
            'key'       => $hash,
            'read_only' => $readOnly,
        ]);

        $this->info('API key created successfully.');
        $this->line('');
        $this->line('  Name      : ' . $name);
        $this->line('  Read-only : ' . ($readOnly ? 'yes' : 'no'));
        $this->line('  Key       : ' . $plain);
        $this->line('');
        $this->warn('Store this key securely — it will never be shown again.');

        return self::SUCCESS;
    }
}
