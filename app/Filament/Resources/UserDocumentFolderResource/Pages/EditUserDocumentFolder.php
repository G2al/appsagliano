<?php

namespace App\Filament\Resources\UserDocumentFolderResource\Pages;

use App\Filament\Resources\UserDocumentFolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserDocumentFolder extends EditRecord
{
    protected static string $resource = UserDocumentFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
