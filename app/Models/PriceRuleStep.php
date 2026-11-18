<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRuleStep extends Model
{
    protected $fillable = [
        'price_rule_id',
        'position',
        'goods_type_id',
        'price',
        'owner_price',
    ];

    protected $casts = [
        'position' => 'integer',
        'price' => 'decimal:2',
        'owner_price' => 'decimal:2',
    ];

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }

    public function goodsType(): BelongsTo
    {
        return $this->belongsTo(GoodsType::class);
    }
}
