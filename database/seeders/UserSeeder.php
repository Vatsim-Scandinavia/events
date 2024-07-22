<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = Group::all();

        for($i = 1; $i <= 11; $i++) {
            $first_name = 'Web';
            $last_name = 'X';
            $email = 'auth.dev' . $i .'@vatsim.net';
            $group = null;

            switch($i) {
                case 1:
                    $last_name = 'One';
                    break;
                case 2:
                    $last_name = 'Two';
                    break;
                case 3:
                    $last_name = 'Three';
                    break;
                case 4:
                    $last_name = 'Four';
                    break;
                case 5:
                    $last_name = 'Five';
                    break;
                case 6:
                    $last_name = 'Six';
                    break;
                case 7:
                    $last_name = 'Seven';
                    break;
                case 8:
                    $last_name = 'Eight';
                    break;
                case 9:
                    $last_name = 'Nine';
                    break;
                case 10:
                    $first_name = 'Team';
                    $last_name = 'Web';
                    $email = 'noreply@vatsim.net';
                    $group = 1;
                    break;
                case 11:
                    $first_name = 'Suspended';
                    $last_name = 'User';
                    $email = 'suspended@vatsim.net';
                    break;
            }

            $user = User::factory()->create([
                'id' => 10000000 + $i,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name
            ]);

            // Assign groups randomly, or specific group for user 10
            if ($i == 10) {
                $user->groups()->attach(Group::find($group), ['area_id' => 1]);
            } else if ($i !== 11) {
                $randomGroup = $groups->random(rand(0, $groups->count()))->pluck('id')->toArray();
                $user->groups()->attach($randomGroup, ['area_id' => 1]);
            }
        }
    }
}
