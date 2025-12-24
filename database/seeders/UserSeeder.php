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
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'surname' => 'Sagliano',
                'phone' => '+390000000000',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_approved' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'worker@gmail.com'],
            [
                'name' => 'Mario',
                'surname' => 'Operaio',
                'phone' => '+391111111111',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'is_approved' => true,
            ]
        );
    }
}
