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
            ['phone' => '+390000000000'],
            [
                'email' => 'admin@appmegatrasporti.it',
                'name' => 'Admin',
                'surname' => 'Mega Trasporti',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_approved' => true,
            ]
        );

        User::updateOrCreate(
            ['phone' => '+391111111111'],
            [
                'email' => 'worker@appmegatrasporti.it',
                'name' => 'Mario',
                'surname' => 'Operaio',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'is_approved' => true,
            ]
        );
    }
}
