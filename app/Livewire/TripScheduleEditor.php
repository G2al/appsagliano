<?php

namespace App\Livewire;

use App\Filament\Resources\TripResource;
use App\Models\DriverMonthlyAdjustment;
use App\Models\DriverMonthlySalary;
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

    public string $salaryInput = '';

    public string $newAdjustmentLabel = '';

    public string $newAdjustmentAmount = '';

    public function mount(int $userId): void
    {
        $this->userId = $userId;
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->salaryInput = '';
    }

    public function saveSalary(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canManageBorderoPrice()) {
            abort(403);
        }

        $raw = str_replace(',', '.', trim($this->salaryInput));

        if ($raw === '') {
            DriverMonthlySalary::query()
                ->where('user_id', $this->userId)
                ->where('month', $this->month)
                ->delete();
        } else {
            DriverMonthlySalary::query()->updateOrCreate(
                ['user_id' => $this->userId, 'month' => $this->month],
                ['amount' => round((float) $raw, 2)]
            );
        }

        Notification::make()->title('Stipendio aggiornato')->success()->send();
    }

    public function addAdjustment(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canManageBorderoPrice()) {
            abort(403);
        }

        $label = trim($this->newAdjustmentLabel);
        $raw = str_replace(',', '.', trim($this->newAdjustmentAmount));

        if ($label === '' || $raw === '' || ! is_numeric($raw)) {
            return;
        }

        DriverMonthlyAdjustment::query()->create([
            'user_id' => $this->userId,
            'month' => $this->month,
            'label' => $label,
            'amount' => round((float) $raw, 2),
        ]);

        $this->newAdjustmentLabel = '';
        $this->newAdjustmentAmount = '';

        Notification::make()->title('Voce aggiunta')->success()->send();
    }

    public function deleteAdjustment(int $adjustmentId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canManageBorderoPrice()) {
            abort(403);
        }

        DriverMonthlyAdjustment::query()
            ->where('user_id', $this->userId)
            ->where('id', $adjustmentId)
            ->delete();
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

    public function acceptTrip(int $tripId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canReviewBordero()) {
            abort(403);
        }

        $trip = Trip::query()
            ->where('user_id', $this->userId)
            ->findOrFail($tripId);

        $trip->update(['reviewed_at' => now()]);

        Notification::make()
            ->title('Bordero accettato')
            ->success()
            ->send();
    }

    public function editUrl(int $tripId): string
    {
        return TripResource::getUrl('edit', ['record' => $tripId]);
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

        $salary = DriverMonthlySalary::query()
            ->where('user_id', $this->userId)
            ->where('month', $this->month)
            ->first();

        if ($this->salaryInput === '') {
            $amount = $salary?->amount ?? $owner->default_salary;
            $this->salaryInput = $amount !== null ? number_format((float) $amount, 2, '.', '') : '';
        }

        $adjustments = DriverMonthlyAdjustment::query()
            ->where('user_id', $this->userId)
            ->where('month', $this->month)
            ->orderBy('id')
            ->get();

        $salaryAmount = $this->salaryInput !== '' ? (float) str_replace(',', '.', $this->salaryInput) : 0.0;
        $adjustmentsTotal = (float) $adjustments->sum(fn (DriverMonthlyAdjustment $a) => (float) $a->amount);
        $payoutTotal = collect($schedule['days'])->sum('total') + $salaryAmount + $adjustmentsTotal;

        return view('livewire.trip-schedule-editor', [
            'owner' => $owner,
            'adjustments' => $adjustments,
            'adjustmentsTotal' => $adjustmentsTotal,
            'salaryAmount' => $salaryAmount,
            'payoutTotal' => $payoutTotal,
            ...$schedule,
        ]);
    }
}
