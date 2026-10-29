<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiale extends Model
{
    protected $table = 'filiali';

    protected $fillable = [
        'platform_id',
        'goods_type_id',
        'name',
        'codice',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function goodsType(): BelongsTo
    {
        return $this->belongsTo(GoodsType::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FilialePrice::class)->orderBy('trip_order');
    }

    public function priceForTripOrder(int $tripOrder): ?float
    {
        $price = $this->prices->firstWhere('trip_order', $tripOrder);

        return $price?->price !== null ? (float) $price->price : null;
    }
}
