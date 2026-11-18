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

    public function dischargeBonuses(): HasMany
    {
        return $this->hasMany(GoodsTypeDischargeBonus::class)->orderBy('min_scarichi');
    }

    /**
     * Supplemento guadagno proprietario in base al numero di scarichi del
     * viaggio: vince la soglia piu' alta raggiunta (es. 0-1 scarichi = 0,
     * 2+ scarichi = 10). Nullo se non c'e' nessuno scaglione impostato.
     */
    public function dischargeBonusForScarichi(int $scarichi): ?float
    {
        $bonus = $this->dischargeBonuses
            ->filter(fn (GoodsTypeDischargeBonus $b) => $b->min_scarichi <= $scarichi)
            ->sortByDesc('min_scarichi')
            ->first();

        return $bonus?->bonus !== null ? (float) $bonus->bonus : null;
    }
}
