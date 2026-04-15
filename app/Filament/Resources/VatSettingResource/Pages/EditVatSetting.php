<?php

namespace App\Filament\Resources\VatSettingResource\Pages;

use App\Filament\Resources\VatSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditVatSetting extends EditRecord
{
    protected static string $resource = VatSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
