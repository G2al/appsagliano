<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatSetting extends Model
{
    use HasFactory;

    public const DEFAULT_PERCENTAGE = 22.00;

    protected $fillable = [
        'percentage',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    public static function currentPercentage(): float
    {
        $percentage = static::query()->value('percentage');

        return $percentage !== null
            ? (float) $percentage
            : self::DEFAULT_PERCENTAGE;
    }
}
