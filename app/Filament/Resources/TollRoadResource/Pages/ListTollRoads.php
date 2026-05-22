<?php

namespace App\Filament\Resources\TollRoadResource\Pages;

use App\Filament\Resources\TollRoadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTollRoads extends ListRecords
{
    protected static string $resource = TollRoadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
