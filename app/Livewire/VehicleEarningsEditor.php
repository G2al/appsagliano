<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Support\BuildsMonthlyVehicleEarnings;
use Livewire\Component;

class VehicleEarningsEditor extends Component
{
    use BuildsMonthlyVehicleEarnings;

    public int $vehicleId;

    public string $month;

    public function mount(int $vehicleId): void
    {
        $this->vehicleId = $vehicleId;
        $this->month = now()->format('Y-m');
    }

    public function render()
    {
        $vehicle = Vehicle::findOrFail($this->vehicleId);
        $schedule = $this->buildMonthlyEarningsFor($vehicle, $this->month);

        return view('livewire.vehicle-earnings-editor', [
            'vehicle' => $vehicle,
            ...$schedule,
        ]);
    }
}
