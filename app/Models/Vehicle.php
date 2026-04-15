<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plate',
        'color',
        'current_km',
        'maintenance_km',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $vehicle): void {
            $vehicle->documents()->get()->each->delete();
        });
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class)->latest('id');
    }
}
