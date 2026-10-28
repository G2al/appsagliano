<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocumentExpiryNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_document_id',
        'threshold_days',
        'expiry_date',
        'sent_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function vehicleDocument(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class);
    }
}
