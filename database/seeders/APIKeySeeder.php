<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class APIKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ApiKey::create([
            'id' => '51a64a9a-a243-4317-b1ac-080952b2ca05',
            'name' => 'Event',
            'readonly' => false,
            'created_at' => now()
        ]);
    }
}
