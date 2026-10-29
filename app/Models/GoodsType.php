<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsType extends Model
{
    protected $fillable = [
        'platform_id',
        'name',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function filiali(): HasMany
    {
        return $this->hasMany(Filiale::class);
    }
}
