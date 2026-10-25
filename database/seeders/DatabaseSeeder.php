<?php

namespace Database\Seeders;

use App\Auth\Roles;
use App\Enums\ControllerRating;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->call('roles:sync');
        $this->call(EventSeeder::class);

        // A few people to sign in as from the dev login page.
        $copenhagen = Fir::query()->where('icao', 'EKDK')->firstOrFail();

        User::factory()->admin()->create(['name' => 'Hub Admin', 'email' => 'admin@example.com', 'vatsim_cid' => 1000000]);
        User::factory()->staff()->withRole(Roles::ADMINISTRATOR)->create(['name' => 'Event Staff', 'email' => 'staff@example.com', 'vatsim_cid' => 1000001]);
        User::factory()->staff($copenhagen)->create(['name' => 'Copenhagen Event Staff', 'email' => 'ekdk-staff@example.com', 'vatsim_cid' => 1000006]);
        User::factory()->vaccStaff()->create(['name' => 'vACC Director', 'email' => 'director@example.com', 'vatsim_cid' => 1000007]);
        User::factory()->controller(ControllerRating::S2)->withRole(Roles::CONTROLLER)->create(['name' => 'Tower Controller', 'email' => 'twr@example.com', 'vatsim_cid' => 1000002]);
        User::factory()->controller(ControllerRating::S3)->withRole(Roles::CONTROLLER)->create(['name' => 'Approach Controller', 'email' => 'app@example.com', 'vatsim_cid' => 1000003]);
        User::factory()->controller(ControllerRating::C1)->withRole(Roles::CONTROLLER)->create(['name' => 'Centre Controller', 'email' => 'ctr@example.com', 'vatsim_cid' => 1000004]);
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'vatsim_cid' => 1000005]);
    }
}
