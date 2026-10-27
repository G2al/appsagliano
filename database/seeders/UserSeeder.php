<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Unico utente iniziale di Redif: l'admin. Tutto il resto
     * (dipendenti, veicoli, fornitori, ecc.) lo inserisce il cliente
     * direttamente dal pannello.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '+390000000000'],
            [
                'email' => 'admin@appredif.it',
                'name' => 'Admin',
                'surname' => 'Redif',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_approved' => true,
            ]
        );
    }
}
