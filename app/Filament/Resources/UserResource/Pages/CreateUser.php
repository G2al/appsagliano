<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            unset($data['role'], $data['panel_modules']);
            $data['role'] = 'worker';
            $data['panel_modules'] = [];
        }

        return $data;
    }
}
