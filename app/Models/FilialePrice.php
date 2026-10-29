<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilialePrice extends Model
{
    protected $fillable = [
        'filiale_id',
        'trip_order',
        'price',
    ];

    protected $casts = [
        'trip_order' => 'integer',
        'price' => 'decimal:2',
    ];

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class);
    }
}
