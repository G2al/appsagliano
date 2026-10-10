<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Services\TripDistanceCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachmentPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAttachmentPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingAttachmentPaths as $path) {
            $this->record->attachments()->create(['path' => $path]);
        }

        app(TripDistanceCalculator::class)->calculate($this->record);
    }
}
