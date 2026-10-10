<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
        static::saving(function (self $platform): void {
            if ($platform->isDirty('address') && ! $platform->isDirty('latitude') && ! $platform->isDirty('longitude')) {
                $platform->latitude = null;
                $platform->longitude = null;
            }
        });
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
