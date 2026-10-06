<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MegaEmployeeSeeder extends Seeder
{
    /**
     * Importa i dipendenti reali di Mega Trasporti da
     * database/seeders/data/mega_employees.txt (un "COGNOME NOME" per riga).
     *
     * Regola di split: l'ULTIMA parola della riga e il nome, tutto il resto
     * (anche piu parole) e il cognome. Serve per non troncare cognomi composti
     * o nomi stranieri con piu parti (es. "DELLA CORTE ANTONIO" -> cognome
     * "Della Corte", nome "Antonio").
     *
     * Idempotente: rilanciabile senza creare doppioni, aggiorna l'utente
     * esistente in base alla coppia nome+cognome.
     */
    public function run(): void
    {
        $path = __DIR__ . '/data/mega_employees.txt';

        $lines = array_filter(array_map('trim', file($path)), fn ($line) => $line !== '');

        $usedPhones = [];
        $imported = 0;

        foreach ($lines as $line) {
            $words = preg_split('/\s+/', $line);
            $firstName = array_pop($words);
            $lastName = count($words) > 0 ? implode(' ', $words) : $firstName;

            $name = $this->titleCase($firstName);
            $surname = $this->titleCase($lastName);

            $slug = $this->slugFor($name) . $this->slugFor($surname);
            $email = $slug . '@appmegatrasporti.it';
            $password = strtolower($this->slugFor($name)) . 'mega2026';

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
                ]
            );

            $imported++;
        }

        $this->command?->info("Importati {$imported} dipendenti Mega Trasporti.");
    }

    private function titleCase(string $value): string
    {
        $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);

        return preg_replace_callback("/'(\\w)/u", fn ($m) => "'" . mb_strtoupper($m[1]), $value);
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
