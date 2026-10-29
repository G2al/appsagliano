<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Filiale;
use App\Services\TripPriceCalculator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachmentPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAttachmentPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        $calculator = app(TripPriceCalculator::class);
        $filiale = Filiale::query()->with('prices')->findOrFail($data['filiale_id']);
        $tripOrder = $calculator->resolveTripOrder((int) $data['user_id'], Carbon::parse($data['date']));

        $data['trip_order'] = $tripOrder;
        $data['price'] = $calculator->resolvePrice($filiale, $tripOrder);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingAttachmentPaths as $path) {
            $this->record->attachments()->create(['path' => $path]);
        }
    }
}
