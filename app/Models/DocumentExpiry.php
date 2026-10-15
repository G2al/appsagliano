<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello di sola lettura sulla vista SQL document_expiries: espone ogni
 * documento di verifica (identita/patente/cqc/tachigrafica) con scadenza
 * compilata come riga singola, per la resource Filament "Scadenze".
 */
class DocumentExpiry extends Model
{
    protected $table = 'document_expiries';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'expiry_date' => 'date',
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'identity' => 'Documento di identita',
        'license' => 'Patente',
        'cqc' => 'CQC',
        'tachograph' => 'Carta tachigrafica',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userVerification(): BelongsTo
    {
        return $this->belongsTo(UserVerification::class, 'user_verification_id');
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
        throw new \LogicException('document_expiries e una vista di sola lettura.');
    }

    public function delete()
    {
        throw new \LogicException('document_expiries e una vista di sola lettura.');
    }
}
