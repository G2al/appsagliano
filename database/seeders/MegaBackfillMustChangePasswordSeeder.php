<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class MegaBackfillMustChangePasswordSeeder extends Seeder
{
    /**
     * Correzione una tantum: i dipendenti importati da MegaEmployeeSeeder
     * prima che esistesse la colonna must_change_password sono rimasti con
     * il valore di default (false). Qui lo riportiamo a true SOLO per chi
     * non ha mai toccato il proprio account dopo la creazione (created_at
     * uguale a updated_at), cosi non tocchiamo nessuno che abbia gia
     * cambiato la password legittimamente (es. da app).
     *
     * Non tocca password, telefono o email: solo il flag.
     */
    public function run(): void
    {
        $path = __DIR__ . '/data/mega_employees.txt';

        $lines = array_filter(array_map('trim', file($path)), fn ($line) => $line !== '');

        $updated = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $words = preg_split('/\s+/', $line);
            $firstName = array_pop($words);
            $lastName = count($words) > 0 ? implode(' ', $words) : $firstName;

            $name = $this->titleCase($firstName);
            $surname = $this->titleCase($lastName);

            $user = User::where('name', $name)->where('surname', $surname)->first();

            if (! $user) {
                continue;
            }

            $untouchedSinceCreation = $user->created_at !== null
                && $user->updated_at !== null
                && $user->created_at->equalTo($user->updated_at);

            if (! $untouchedSinceCreation) {
                $skipped++;
                continue;
            }

            $user->timestamps = false;
            $user->must_change_password = true;
            $user->save();

            $updated++;
        }

        $this->command?->info("Corretti {$updated} dipendenti (must_change_password=true). Saltati {$skipped} perche gia modificati.");
    }

    private function titleCase(string $value): string
    {
        $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);

        return preg_replace_callback("/'(\\w)/u", fn ($m) => "'" . mb_strtoupper($m[1]), $value);
    }
}
