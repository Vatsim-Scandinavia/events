<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\ApiKey;

class CreateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apikey:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $choiceReadOnly = $this->choice('What type of API key do you want to create?', ['read-only', 'write']);
        $readOnly = $choiceReadOnly === 'read-only';

        $name = $this->ask('What is the name of the API key?');

        $apiKey = ApiKey::create([
            'name' => $name,
            'read_only' => $readOnly,
        ]);

        $this->info('API Key `'. $name .'` was created with the following details:');
        $this->line('Token: ' . $apiKey->id);
        $this->line('Read-only: ' . ($readOnly ? 'Yes' : 'No'));

        return Command::SUCCESS;
    }
}
