<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MegaSupplierSeeder extends Seeder
{
    /**
     * Importa i fornitori reali di Mega Trasporti da
     * database/seeders/data/mega_suppliers.txt (una ragione sociale per riga).
     *
     * Nel file ci sono solo i nomi: telefono/email/indirizzo sono dati
     * segnaposto generati qui (non reali), con "mega" nel dominio email e
     * nell'indirizzo per restare coerenti col resto dei dati aziendali.
     *
     * Idempotente: rilanciabile senza creare doppioni, aggiorna il fornitore
     * esistente in base al nome esatto.
     */
    public function run(): void
    {
        $path = __DIR__ . '/data/mega_suppliers.txt';

        $lines = array_filter(array_map('trim', file($path)), fn ($line) => $line !== '');

        $usedPhones = [];
        $usedEmails = [];
        $imported = 0;

        foreach ($lines as $name) {
            $email = $this->uniqueEmailFor($name, $usedEmails);
            $usedEmails[] = $email;

            $phone = $this->uniqueRandomPhone($usedPhones);
            $usedPhones[] = $phone;

            Supplier::query()->updateOrCreate(
                ['name' => $name],
                [
                    'phone' => $phone,
                    'email' => $email,
                    'address' => 'Via Mega Trasporti 1, 00100 Roma (RM)',
                ]
            );

            $imported++;
        }

        $this->command?->info("Importati {$imported} fornitori Mega Trasporti.");
    }

    private function slugFor(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($value)));
    }

    /**
     * @param  string[]  $usedEmails
     */
    private function uniqueEmailFor(string $name, array $usedEmails): string
    {
        $slug = Str::limit($this->slugFor($name), 40, '');
        $slug = $slug !== '' ? $slug : 'fornitore';

        $email = "{$slug}@fornitori.appmegatrasporti.it";
        $suffix = 2;

        while (
            in_array($email, $usedEmails, true)
            || Supplier::where('email', $email)->exists()
        ) {
            $email = "{$slug}{$suffix}@fornitori.appmegatrasporti.it";
            $suffix++;
        }

        return $email;
    }

    /**
     * @param  string[]  $usedPhones
     */
    private function uniqueRandomPhone(array $usedPhones): string
    {
        do {
            $phone = '+3906' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        } while (in_array($phone, $usedPhones, true) || Supplier::where('phone', $phone)->exists());

        return $phone;
    }
}
