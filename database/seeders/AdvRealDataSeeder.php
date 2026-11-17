<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdvRealDataSeeder extends Seeder
{
    /**
     * Importa i dati reali di ADV Logistica (veicoli, autisti, stazione
     * carburante, piattaforma di carico) forniti dal cliente.
     *
     * Idempotente: rilanciabile senza creare doppioni (veicoli per targa,
     * autisti per nome+cognome, stazione/piattaforma per nome).
     */
    private const VEHICLES = [
        ['name' => 'Man TGS 480', 'plate' => 'EW841WL'],
        ['name' => 'Iveco Stralis 500', 'plate' => 'CZ419GZ'],
        ['name' => 'Renault T 480', 'plate' => 'FR940MY'],
        ['name' => 'Iveco Stralis 420', 'plate' => 'ED475VN'],
        ['name' => 'Volvo FH 420', 'plate' => 'ET146HR'],
        ['name' => 'Man TGX 460', 'plate' => 'FL913FV'],
    ];

    /**
     * @var array<int, array{0: string, 1: string, 2: string}> [cognome, nome, telefono]
     */
    private const DRIVERS = [
        ['Marotta', 'Pietro', '+393393117799'],
        ['Marotta', 'Emanuele', '+393466828491'],
        ['Marano', 'Federico', '+393382428090'],
        ['D\'Angelo', 'Antonio', '+393891632887'],
        ['Virgilio', 'Antonio', '+393491731145'],
    ];

    public function run(): void
    {
        foreach (self::VEHICLES as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['plate' => $vehicle['plate']],
                [
                    'name' => $vehicle['name'],
                    'current_km' => 0,
                    'maintenance_km' => 0,
                ]
            );
        }

        foreach (self::DRIVERS as [$surname, $name, $phone]) {
            $slug = $this->slugFor($name) . $this->slugFor($surname);

            User::query()->updateOrCreate(
                ['name' => $name, 'surname' => $surname],
                [
                    'email' => $slug . '@app-adv.it',
                    'phone' => $phone,
                    'password' => Hash::make(strtolower($this->slugFor($name)) . 'adv2026'),
                    'role' => 'worker',
                    'is_approved' => true,
                    'must_change_password' => true,
                ]
            );
        }

        $station = Station::query()->updateOrCreate(
            ['name' => 'IPER FUEL'],
            [
                'address' => 'Via Circumvallazione 83, Casaluce (CE)',
                'credit_balance' => null,
                'uses_vouchers' => true,
                'uses_credit_cards' => false,
            ]
        );

        $platform = Platform::query()->updateOrCreate(
            ['name' => 'CE.DI. Sigma Campania S.p.A.']
        );

        $platform->addresses()->firstOrCreate(['address' => 'Carinaro (CE)']);

        $this->command?->info('Importati: ' . count(self::VEHICLES) . ' veicoli, ' . count(self::DRIVERS) . ' autisti, stazione ' . $station->name . ', piattaforma ' . $platform->name . '.');
    }

    private function slugFor(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($value)));
    }
}
