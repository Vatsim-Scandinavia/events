<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Seeder;

class CalendarSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'seed@vatsca.org'],
            [
                'id'         => 100000000,
                'first_name' => 'Seed',
                'last_name'  => 'Admin',
                'last_login' => now(),
            ]
        );

        $calendars = [
            ['title' => 'VATSIM Scandinavia', 'description' => 'Network-wide events for the Scandinavian region.', 'visibility' => 'public'],
            ['title' => 'VACC Denmark',       'description' => 'Events organised by the Danish vACC.',              'visibility' => 'public'],
            ['title' => 'VACC Norway',         'description' => 'Events organised by the Norwegian vACC.',            'visibility' => 'public'],
            ['title' => 'VACC Sweden',         'description' => 'Events organised by the Swedish vACC.',              'visibility' => 'public'],
            ['title' => 'VACC Finland',        'description' => 'Events organised by the Finnish vACC.',              'visibility' => 'public'],
            ['title' => 'VACC Iceland',        'description' => 'Events organised by the Icelandic vACC.',            'visibility' => 'public'],
            ['title' => 'Staff Events',        'description' => 'Internal staff training and meetings.',              'visibility' => 'private'],
        ];

        foreach ($calendars as $data) {
            Calendar::firstOrCreate(
                ['title' => $data['title']],
                [...$data, 'created_by' => $admin->id]
            );
        }
    }
}
