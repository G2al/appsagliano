<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StationCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'number',
        'label',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
