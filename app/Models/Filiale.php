<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiale extends Model
{
    protected $table = 'filiali';

    protected $fillable = [
        'name',
        'codice',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
