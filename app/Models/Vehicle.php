<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Movement;

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

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
