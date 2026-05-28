<?php

namespace App\Filament\Resources\ExtraCostResource\Pages;

use App\Filament\Resources\ExtraCostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExtraCost extends EditRecord
{
    protected static string $resource = ExtraCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
