<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '+390000000002'],
            [
                'email' => 'admin@app-adv.it',
                'name' => 'Admin',
                'surname' => 'ADV Logistica',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_approved' => true,
            ]
        );

        User::updateOrCreate(
            ['phone' => '+391111111112'],
            [
                'email' => 'worker@app-adv.it',
                'name' => 'Mario',
                'surname' => 'Operaio ADV',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'is_approved' => true,
            ]
        );
    }
}
