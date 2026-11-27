<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;

/**
 * Configurazione standard per il log di controllo (chi ha fatto cosa):
 * registra solo i campi che cambiano davvero, ignora i salvataggi senza
 * modifiche reali, e usa il nome del modello come "log name" per poterli
 * filtrare nella resource Audit Log.
 */
trait LogsAuditTrail
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename(static::class));
    }
}
