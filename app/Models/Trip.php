<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Trip extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const GOODS_TYPE_DRY = 'secco';
    public const GOODS_TYPE_FRESH = 'freschi';

    protected $fillable = [
        'user_id',
        'platform_id',
        'vehicle_id',
        'date',
        'destinations',
        'goods_type',
        'delivery_note_number',
        'price',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'datetime',
        'destinations' => 'array',
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'attachment_url',
        'is_certified',
    ];

    /**
     * @return array<string, string>
     */
    public static function goodsTypeOptions(): array
    {
        return [
            self::GOODS_TYPE_DRY => 'Secco',
            self::GOODS_TYPE_FRESH => 'Freschi',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getIsCertifiedAttribute(): bool
    {
        return $this->price !== null;
    }

    public function getGoodsTypeLabelAttribute(): string
    {
        return self::goodsTypeOptions()[$this->goods_type] ?? (string) $this->goods_type;
    }

    public function getDestinationsLabelAttribute(): string
    {
        return collect($this->destinations ?? [])->filter()->implode(' → ');
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
