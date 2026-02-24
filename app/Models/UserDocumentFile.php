<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDocumentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_document_folder_id',
        'uploaded_by',
        'title',
        'file_path',
        'mime_type',
        'file_size',
        'opened_at',
        'opened_ip',
        'opened_user_agent',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $file): void {
            if (! $file->uploaded_by && Auth::check()) {
                $file->uploaded_by = Auth::id();
            }
        });

        static::saving(function (self $file): void {
            if (! $file->isDirty('file_path') || ! $file->file_path) {
                return;
            }

            $disk = Storage::disk('local');

            if (! $disk->exists($file->file_path)) {
                return;
            }

            $file->mime_type = $disk->mimeType($file->file_path) ?: null;
            $file->file_size = $disk->size($file->file_path);

            if (! $file->title) {
                $file->title = pathinfo($file->file_path, PATHINFO_FILENAME);
            }
        });

        static::updated(function (self $file): void {
            if (! $file->wasChanged('file_path')) {
                return;
            }

            $originalPath = (string) $file->getOriginal('file_path');

            if ($originalPath !== '' && $originalPath !== $file->file_path) {
                Storage::disk('local')->delete($originalPath);
            }
        });

        static::deleted(function (self $file): void {
            if (! $file->file_path) {
                return;
            }

            Storage::disk('local')->delete($file->file_path);
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(UserDocumentFolder::class, 'user_document_folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function markAsOpened(?string $ip, ?string $userAgent): void
    {
        if ($this->opened_at) {
            return;
        }

        $this->forceFill([
            'opened_at' => now(),
            'opened_ip' => $ip,
            'opened_user_agent' => $userAgent,
        ])->saveQuietly();
    }

    public function downloadName(): string
    {
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);
        $safeTitle = trim((string) $this->title) !== '' ? trim((string) $this->title) : 'documento';
        $safeTitle = Str::of($safeTitle)->ascii()->replaceMatches('/[^A-Za-z0-9 _.-]/', '')->trim()->value();

        if ($safeTitle === '') {
            $safeTitle = 'documento';
        }

        return $extension ? "{$safeTitle}.{$extension}" : $safeTitle;
    }
}
