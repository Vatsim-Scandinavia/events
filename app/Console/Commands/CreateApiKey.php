<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;

class CreateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:apikey';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create apikey';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $choices = [
            'NO, read only',
            'YES, allow editing data',
        ];
        $choice = $this->choice('Should the API key have edit rights?', $choices);
        $readonly = $choice == $choices[0];

        $name = $this->ask('What should we name the API Key?');

        $secret = Uuid::uuid4();
        ApiKey::create([
            'id' => $secret,
            'name' => $name,
            'readonly' => $readonly,
            'created_at' => now(),
        ]);

        $this->comment('API key `' . $name . '` has been created with following token: `' . $secret . '`');
    }
}
