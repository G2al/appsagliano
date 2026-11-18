<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsTypeDischargeBonus extends Model
{
    protected $fillable = [
        'goods_type_id',
        'min_scarichi',
        'bonus',
    ];

    protected $casts = [
        'min_scarichi' => 'integer',
        'bonus' => 'decimal:2',
    ];

    public function goodsType(): BelongsTo
    {
        return $this->belongsTo(GoodsType::class);
    }
}
