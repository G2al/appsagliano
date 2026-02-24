<?php

namespace App\Filament\Resources\DocumentFolderTemplateResource\Pages;

use App\Filament\Resources\DocumentFolderTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentFolderTemplates extends ListRecords
{
    protected static string $resource = DocumentFolderTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
