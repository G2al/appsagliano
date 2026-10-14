<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAddress extends Model
{
    protected $fillable = [
        'platform_id',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $platformAddress): void {
            if ($platformAddress->isDirty('address') && ! $platformAddress->isDirty('latitude') && ! $platformAddress->isDirty('longitude')) {
                $platformAddress->latitude = null;
                $platformAddress->longitude = null;
            }
        });
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
