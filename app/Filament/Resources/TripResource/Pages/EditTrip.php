<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Filiale;
use App\Models\Vehicle;
use App\Services\TripPriceCalculator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachmentPaths = [];

    /** @var array<int, int> */
    protected array $pendingExtraIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['attachments'] = $this->record->attachments->pluck('path')->toArray();

        $filiale = $this->record->filiale;
        $data['platform_id'] = $filiale?->platform_id;
        $data['goods_type_id'] = $filiale?->goods_type_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAttachmentPaths = $data['attachments'] ?? [];
        unset($data['attachments']);

        $calculator = app(TripPriceCalculator::class);
        $filiale = Filiale::query()->with(['platform', 'prices', 'goodsType.dischargeBonuses'])->findOrFail($data['filiale_id']);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $date = Carbon::parse($data['date']);
        $tripOrder = $calculator->resolveTripOrder((int) $data['user_id'], $date, excludeTripId: $this->record->id);
        $scarichi = (int) ($data['scarichi'] ?? 0);

        $extras = $calculator->resolveAutomaticExtras($filiale, $date);
        $this->pendingExtraIds = $extras->pluck('id')->map(fn ($id) => (int) $id)->all();
        $extrasTotal = $calculator->resolveExtrasAmount($extras);

        $vehicleDayPosition = $calculator->resolveVehicleDayPosition($vehicle->id, $date, excludeTripId: $this->record->id);
        $priorSequence = $calculator->resolveVehicleDaySequence($vehicle->id, $date, excludeTripId: $this->record->id);
        $ruleOverride = $calculator->resolveRuleOverride($vehicle, $filiale, $vehicleDayPosition, $priorSequence);

        $basePrice = $ruleOverride['price'] ?? $calculator->resolvePrice($filiale, $tripOrder);
        $ownerPrice = $ruleOverride['owner_price'] ?? $calculator->resolveOwnerPrice($filiale, $tripOrder, $scarichi);

        $data['trip_order'] = $tripOrder;
        $data['price'] = $basePrice;
        $data['owner_price'] = $ownerPrice !== null ? round($ownerPrice + $extrasTotal, 2) : null;

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

        $this->record->extras()->sync($this->pendingExtraIds);
    }
}
