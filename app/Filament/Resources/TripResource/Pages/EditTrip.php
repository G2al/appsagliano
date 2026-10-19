<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachmentPaths = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['attachments'] = $this->record->attachments->pluck('path')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAttachmentPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        return $data;
    }

    protected function afterSave(): void
    {
        $existing = $this->record->attachments()->get();
        $keptPaths = $this->pendingAttachmentPaths;

        foreach ($existing as $attachment) {
            if (! in_array($attachment->path, $keptPaths, true)) {
                Storage::disk('public')->delete($attachment->path);
                $attachment->delete();
            }
        }

        $existingPaths = $existing->pluck('path')->all();

        foreach ($keptPaths as $path) {
            if (! in_array($path, $existingPaths, true)) {
                $this->record->attachments()->create(['path' => $path]);
            }
        }
    }
}
