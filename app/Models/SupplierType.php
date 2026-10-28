<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
