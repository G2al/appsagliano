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
        'company',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PlatformAddress::class)->orderBy('id');
    }

    public function hasSingleAddress(): bool
    {
        return $this->addresses->count() === 1;
    }
}
