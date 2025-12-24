<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Movement;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'credit_balance',
    ];

    protected $casts = [
        'credit_balance' => 'decimal:2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
