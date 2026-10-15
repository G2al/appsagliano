<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpiryNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_verification_id',
        'document_type',
        'threshold_days',
        'expiry_date',
        'sent_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function userVerification(): BelongsTo
    {
        return $this->belongsTo(UserVerification::class);
    }
}
