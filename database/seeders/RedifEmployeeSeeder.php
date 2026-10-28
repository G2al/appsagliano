<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RedifEmployeeSeeder extends Seeder
{
    /**
     * Autisti reali di Redif. Password auto-generata (nome + "redif2026"),
     * come per gli account creati in blocco. Idempotente.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DRIVERS = [
        ['Francesco', 'Gioia'],
        ['Raffaele', 'Briglionr'],
        ['Aniello', 'Esposito'],
        ['Angelo', 'Fatigati'],
        ['Pasquale', 'Bracciale'],
        ['Armando', 'Parente'],
        ['Giuseppe', 'Castaldo'],
        ['Ferdinando', 'Cozzolino'],
        ['Bruno', 'Di Maio'],
        ['Palumbo', 'Palumbo'],
        ['Ludovico', 'Riccardi'],
        ['Domenico', 'Basilicata'],
        ['Luigi', 'Capasso'],
        ['Vincenzo', 'Giaquinto'],
        ['Claudio', 'Barone'],
    ];

    public function run(): void
    {
        $usedPhones = [];
        $imported = 0;

        foreach (self::DRIVERS as [$name, $surname]) {
            $slug = $this->slugFor($name) . $this->slugFor($surname);
            $email = "{$slug}@appredif.it";
            $password = strtolower($this->slugFor($name)) . 'redif2026';

            $phone = $this->uniqueRandomPhone($usedPhones);
            $usedPhones[] = $phone;

            User::updateOrCreate(
                ['name' => $name, 'surname' => $surname],
                [
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make($password),
                    'role' => 'worker',
                    'is_approved' => true,
                    'must_change_password' => true,
                ]
            );

            $imported++;
        }

        $this->command?->info("Importati {$imported} autisti Redif.");
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
