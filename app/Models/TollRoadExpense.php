<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TollRoadExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'toll_road_id',
        'date',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function tollRoad(): BelongsTo
    {
        return $this->belongsTo(TollRoad::class);
    }
}
