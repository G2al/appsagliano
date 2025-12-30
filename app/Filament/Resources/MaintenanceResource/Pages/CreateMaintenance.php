<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenance extends CreateRecord
{
    protected static string $resource = MaintenanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['km_after'] = $data['km_current'] ?? null;

        return $data;
    }
}
