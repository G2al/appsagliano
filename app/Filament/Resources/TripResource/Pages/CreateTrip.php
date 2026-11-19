<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Filiale;
use App\Models\Vehicle;
use App\Services\TripPriceCalculator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachmentPaths = [];

    /** @var array<int, int> */
    protected array $pendingExtraIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAttachmentPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        $calculator = app(TripPriceCalculator::class);
        $filiale = Filiale::query()->with(['platform', 'prices', 'goodsType.dischargeBonuses'])->findOrFail($data['filiale_id']);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $date = Carbon::parse($data['date']);
        $tripOrder = $calculator->resolveTripOrder((int) $data['user_id'], $date);
        $scarichi = (int) ($data['scarichi'] ?? 0);

        $extras = $calculator->resolveAutomaticExtras($filiale, $date);
        $this->pendingExtraIds = $extras->pluck('id')->map(fn ($id) => (int) $id)->all();
        $extrasTotal = $calculator->resolveExtrasAmount($extras);

        $vehicleDayPosition = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $priorSequence = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $ruleOverride = $calculator->resolveRuleOverride($vehicle, $filiale, $vehicleDayPosition, $priorSequence);

        $basePrice = $ruleOverride['price'] ?? $calculator->resolvePrice($filiale, $tripOrder);
        $ownerPrice = $ruleOverride['owner_price'] ?? $calculator->resolveOwnerPrice($filiale, $tripOrder, $scarichi);

        $data['trip_order'] = $tripOrder;
        $data['price'] = $basePrice;
        $data['owner_price'] = $ownerPrice !== null ? round($ownerPrice + $extrasTotal, 2) : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingAttachmentPaths as $path) {
            $this->record->attachments()->create(['path' => $path]);
        }

        if (! empty($this->pendingExtraIds)) {
            $this->record->extras()->sync($this->pendingExtraIds);
        }
    }
}
