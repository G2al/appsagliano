<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleRevenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'date',
        'amount_ex_vat',
        'vat_percentage',
        'amount_inc_vat',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_ex_vat' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'amount_inc_vat' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $revenue): void {
            if ($revenue->vat_percentage === null) {
                $revenue->vat_percentage = VatSetting::currentPercentage();
            }
            $amountExVat = (float) ($revenue->amount_ex_vat ?? 0);
            $vatPercentage = (float) ($revenue->vat_percentage ?? 0);

            $revenue->amount_inc_vat = round(
                $amountExVat * (1 + ($vatPercentage / 100)),
                2
            );
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
