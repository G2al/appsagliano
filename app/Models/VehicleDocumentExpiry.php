<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello di sola lettura sulla vista SQL vehicle_document_expiries, per la
 * resource Filament "Scadenze Veicoli" (stesso meccanismo di DocumentExpiry
 * usato per le scadenze autisti).
 */
class VehicleDocumentExpiry extends Model
{
    protected $table = 'vehicle_document_expiries';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vehicleDocument(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class);
    }

    public function daysRemaining(): int
    {
        return (int) Carbon::today()->diffInDays($this->expiry_date, false);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function statusColor(): string
    {
        if ($this->isExpired()) {
            return 'danger';
        }

        $days = $this->daysRemaining();

        if ($days <= 15) {
            return 'warning';
        }

        if ($days <= 30) {
            return 'info';
        }

        return 'success';
    }

    public function save(array $options = [])
    {
        throw new \LogicException('vehicle_document_expiries e una vista di sola lettura.');
    }

    public function delete()
    {
        throw new \LogicException('vehicle_document_expiries e una vista di sola lettura.');
    }
}
