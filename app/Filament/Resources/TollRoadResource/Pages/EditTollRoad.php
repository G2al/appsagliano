<?php

namespace App\Filament\Resources\TollRoadResource\Pages;

use App\Filament\Resources\TollRoadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTollRoad extends EditRecord
{
    protected static string $resource = TollRoadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
