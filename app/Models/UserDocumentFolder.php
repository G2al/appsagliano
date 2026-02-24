<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDocumentFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_folder_template_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(UserDocumentFile::class)->latest('id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentFolderTemplate::class, 'document_folder_template_id');
    }
}
