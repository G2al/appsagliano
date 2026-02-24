<?php

namespace App\Filament\Resources\DocumentFolderTemplateResource\Pages;

use App\Filament\Resources\DocumentFolderTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentFolderTemplate extends EditRecord
{
    protected static string $resource = DocumentFolderTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
