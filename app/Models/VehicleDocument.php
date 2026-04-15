<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class VehicleDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            if (! $document->isDirty('file_path') || ! $document->file_path) {
                return;
            }

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            if (! $disk->exists($document->file_path)) {
                return;
            }

            $document->mime_type = $disk->mimeType($document->file_path) ?: null;
            $document->file_size = $disk->size($document->file_path);
        });

        static::updated(function (self $document): void {
            if (! $document->wasChanged('file_path')) {
                return;
            }

            $originalPath = (string) $document->getOriginal('file_path');

            if ($originalPath !== '' && $originalPath !== $document->file_path) {
                Storage::disk('public')->delete($originalPath);
            }
        });

        static::deleted(function (self $document): void {
            if (! $document->file_path) {
                return;
            }

            Storage::disk('public')->delete($document->file_path);
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->file_path);
    }
}
