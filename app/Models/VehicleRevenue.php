<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class VehicleRevenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'date',
        'name',
        'amount_ex_vat',
        'vat_percentage',
        'amount_inc_vat',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_ex_vat' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'amount_inc_vat' => 'decimal:2',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $revenue): void {
            if ($revenue->vat_percentage === null) {
                $revenue->vat_percentage = VatSetting::currentPercentage();
            }
            $amountExVat = (float) ($revenue->amount_ex_vat ?? 0);
            $vatPercentage = (float) ($revenue->vat_percentage ?? 0);

            $revenue->amount_inc_vat = round(
                $amountExVat * (1 + ($vatPercentage / 100)),
                2
            );
        });

        static::updated(function (self $revenue): void {
            if (! $revenue->wasChanged('attachment_path')) {
                return;
            }

            $originalPath = $revenue->getOriginal('attachment_path');

            if ($originalPath) {
                Storage::disk('public')->delete($originalPath);
            }
        });

        static::deleting(function (self $revenue): void {
            if ($revenue->attachment_path) {
                Storage::disk('public')->delete($revenue->attachment_path);
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->attachment_path);
    }
}
