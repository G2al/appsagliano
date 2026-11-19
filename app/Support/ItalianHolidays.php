<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ItalianHolidays
{
    /**
     * Vero se la data e' domenica o una festivita' nazionale italiana
     * (Pasquetta compresa).
     */
    public static function isHoliday(Carbon $date): bool
    {
        if ($date->isSunday()) {
            return true;
        }

        $fixed = [
            '01-01', // Capodanno
            '01-06', // Epifania
            '04-25', // Liberazione
            '05-01', // Festa dei lavoratori
            '06-02', // Festa della Repubblica
            '08-15', // Ferragosto
            '11-01', // Tutti i santi
            '12-08', // Immacolata
            '12-25', // Natale
            '12-26', // Santo Stefano
        ];

        if (in_array($date->format('m-d'), $fixed, true)) {
            return true;
        }

        return $date->isSameDay(static::easterMonday($date->year));
    }

    private static function easterMonday(int $year): Carbon
    {
        $easterDays = easter_days($year);

        return Carbon::create($year, 3, 21)->addDays($easterDays)->addDay();
    }
}
