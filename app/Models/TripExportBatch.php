<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripExportBatch extends Model
{
    protected $fillable = [
        'driver_id',
        'exported_by',
        'sequence_number',
        'trip_ids',
    ];

    protected $casts = [
        'trip_ids' => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public static function nextSequenceFor(int $driverId): int
    {
        return (int) (static::query()->where('driver_id', $driverId)->max('sequence_number')) + 1;
    }
}
