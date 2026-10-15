<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Nomi/cognomi fittizi per i driver demo di Vialo (nessun dato reale).
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DRIVERS = [
        ['Marco', 'Bianchi'], ['Luca', 'Ferrari'], ['Giulia', 'Romano'],
        ['Andrea', 'Colombo'], ['Francesco', 'Ricci'], ['Sara', 'Marino'],
        ['Matteo', 'Greco'], ['Chiara', 'Bruno'], ['Davide', 'Gallo'],
        ['Elena', 'Conti'], ['Simone', 'De Luca'], ['Valentina', 'Costa'],
        ['Alessandro', 'Giordano'], ['Martina', 'Mancini'], ['Riccardo', 'Rizzo'],
        ['Federica', 'Lombardi'], ['Gabriele', 'Moretti'], ['Ilaria', 'Barbieri'],
        ['Tommaso', 'Fontana'], ['Beatrice', 'Santoro'], ['Lorenzo', 'Mariani'],
        ['Alice', 'Rinaldi'], ['Michele', 'Caruso'], ['Giorgia', 'Ferraro'],
        ['Nicola', 'Pellegrini'],
    ];

    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '+390000000000'],
            [
                'email' => 'admin@appvialo.it',
                'name' => 'Admin',
                'surname' => 'Vialo',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_approved' => true,
                'documents_verified' => true,
            ]
        );

        User::updateOrCreate(
            ['phone' => '+391111111111'],
            [
                'email' => 'worker@appvialo.it',
                'name' => 'Mario',
                'surname' => 'Operaio',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'is_approved' => true,
                'documents_verified' => true,
                'panel_modules' => ['maintenance', 'documents', 'trips'],
            ]
        );

        $usedPhones = ['+390000000000', '+391111111111'];

        foreach (self::DRIVERS as $index => [$name, $surname]) {
            $slug = $this->slugFor($name) . $this->slugFor($surname);
            $phone = $this->uniqueRandomPhone($usedPhones);
            $usedPhones[] = $phone;

            // La maggior parte dei driver demo e' gia operativa (approvata,
            // verificata); una minoranza resta in stati intermedi per poter
            // mostrare onboarding/verifica/scadenze anche nella demo.
            $isFullyOperational = $index % 6 !== 0;

            $panelModules = match ($index % 4) {
                0 => ['maintenance'],
                1 => ['documents'],
                2 => ['trips'],
                default => ['maintenance', 'documents'],
            };

            $user = User::updateOrCreate(
                ['name' => $name, 'surname' => $surname],
                [
                    'email' => "{$slug}@appvialo.it",
                    'phone' => $phone,
                    'password' => Hash::make(strtolower($this->slugFor($name)) . 'vialo2026'),
                    'role' => 'worker',
                    'is_approved' => true,
                    'must_change_password' => ! $isFullyOperational,
                    'documents_verified' => $isFullyOperational,
                    'panel_modules' => $panelModules,
                ]
            );

            $this->seedVerificationFor($user, $index);
        }

        $this->command?->info('Utenti demo Vialo creati: 1 admin + ' . count(self::DRIVERS) . ' autisti.');
    }

    /**
     * Crea una UserVerification con scadenze variegate: alcune gia scadute,
     * altre entro 7/15/30 giorni, altre tranquille, cosi la resource
     * Scadenze e le notifiche push hanno sempre qualcosa da mostrare in demo.
     */
    private function seedVerificationFor(User $user, int $index): void
    {
        $offsets = [-3, 5, 12, 20, 45, 120, 200];
        $offset = $offsets[$index % count($offsets)];

        UserVerification::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'identity_front_path' => 'demo/placeholder.jpg',
                'identity_back_path' => 'demo/placeholder.jpg',
                'identity_expiry_date' => now()->addDays($offset),
                'license_path' => 'demo/placeholder.jpg',
                'license_back_path' => 'demo/placeholder.jpg',
                'license_expiry_date' => now()->addDays($offset + 10),
                'cqc_path' => 'demo/placeholder.jpg',
                'cqc_back_path' => 'demo/placeholder.jpg',
                'cqc_expiry_date' => now()->addDays($offset + 25),
                'tachograph_front_path' => 'demo/placeholder.jpg',
                'tachograph_back_path' => 'demo/placeholder.jpg',
                'tachograph_expiry_date' => now()->addDays($offset + 40),
                'completed_at' => now()->subDays(random_int(1, 90)),
            ]
        );
    }

    private function slugFor(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($value)));
    }

    /**
     * @param  string[]  $usedPhones
     */
    private function uniqueRandomPhone(array $usedPhones): string
    {
        do {
            $phone = '+39' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        } while (in_array($phone, $usedPhones, true) || User::where('phone', $phone)->exists());

        return $phone;
    }
}
