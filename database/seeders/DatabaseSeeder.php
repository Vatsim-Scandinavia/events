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
        $this->call(EventSeeder::class);

        // A few people to sign in as, from the sign-in page's dev links: the VATSIM Connect
        // sandbox's test members, so a real sandbox sign-in lands on the same Accounts.
        $copenhagen = Fir::query()->where('icao', 'EKDK')->firstOrFail();

        User::factory()->admin()->create(['name' => 'Hub Admin', 'email' => 'admin@example.com', 'vatsim_cid' => 10000001]);
        User::factory()->staff()->withRole(Roles::ADMINISTRATOR)->create(['name' => 'Event Staff', 'email' => 'staff@example.com', 'vatsim_cid' => 10000002]);
        User::factory()->staff($copenhagen)->create(['name' => 'Copenhagen Event Staff', 'email' => 'ekdk-staff@example.com', 'vatsim_cid' => 10000003]);
        User::factory()->vaccStaff()->create(['name' => 'vACC Director', 'email' => 'director@example.com', 'vatsim_cid' => 10000004]);
        User::factory()->controller(ControllerRating::S2)->create(['name' => 'Tower Controller', 'email' => 'twr@example.com', 'vatsim_cid' => 10000005]);
        User::factory()->controller(ControllerRating::S3)->create(['name' => 'Approach Controller', 'email' => 'app@example.com', 'vatsim_cid' => 10000006]);
        User::factory()->controller(ControllerRating::C1)->create(['name' => 'Centre Controller', 'email' => 'ctr@example.com', 'vatsim_cid' => 10000007]);
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'vatsim_cid' => 10000008]);
    }
}
