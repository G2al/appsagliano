<?php

namespace App\Livewire;

use App\Models\Trip;
use App\Models\User;
use App\Support\BuildsMonthlyTripSchedule;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TripScheduleEditor extends Component
{
    use BuildsMonthlyTripSchedule;

    public int $userId;

    public string $month;

    /** @var array<int, string> */
    public array $prices = [];

    public function mount(int $userId): void
    {
        $this->userId = $userId;
        $this->month = now()->format('Y-m');
    }

    public function updatePrice(int $tripId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canManageBorderoPrice()) {
            abort(403);
        }

        $trip = Trip::query()
            ->where('user_id', $this->userId)
            ->findOrFail($tripId);

        $raw = str_replace(',', '.', trim((string) ($this->prices[$tripId] ?? '')));

        $trip->price = $raw === '' ? null : round((float) $raw, 2);
        $trip->save();

        $this->prices[$tripId] = $trip->price !== null
            ? number_format((float) $trip->price, 2, '.', '')
            : '';

        Notification::make()
            ->title('Importo aggiornato')
            ->success()
            ->send();

        $this->dispatch('price-saved', tripId: $tripId);
    }

    public function render()
    {
        $owner = User::findOrFail($this->userId);
        $schedule = $this->buildMonthlyScheduleFor($owner, $this->month);

        foreach ($schedule['days'] as $day) {
            foreach ($day['trips'] as $trip) {
                if (! array_key_exists($trip->id, $this->prices)) {
                    $this->prices[$trip->id] = $trip->price !== null
                        ? number_format((float) $trip->price, 2, '.', '')
                        : '';
                }
            }
        }

        return view('livewire.trip-schedule-editor', [
            'owner' => $owner,
            ...$schedule,
        ]);
    }
}
