<?php

namespace App\Models;

use App\Services\UserDocumentFolderTemplateSyncService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolderTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    protected static function booted(): void
    {
        static::created(function (self $template): void {
            app(UserDocumentFolderTemplateSyncService::class)->syncTemplateForAllUsers($template);
        });

        static::updated(function (self $template): void {
            if (! $template->wasChanged('title')) {
                return;
            }

            app(UserDocumentFolderTemplateSyncService::class)->syncTemplateTitle($template);
        });
    }

    public function userFolders(): HasMany
    {
        return $this->hasMany(UserDocumentFolder::class);
    }
}
