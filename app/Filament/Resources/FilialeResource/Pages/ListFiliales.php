<?php

namespace App\Filament\Resources\FilialeResource\Pages;

use App\Filament\Resources\FilialeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiliales extends ListRecords
{
    protected static string $resource = FilialeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
