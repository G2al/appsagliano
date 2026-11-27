<?php

namespace App\Livewire;

use App\Filament\Resources\TripResource;
use App\Models\DriverMonthlyAdjustment;
use App\Models\DriverMonthlySalary;
use App\Models\Trip;
use App\Models\TripExportBatch;
use App\Models\User;
use App\Support\BuildsMonthlyTripSchedule;
use App\Support\DriverTripListExcelExporter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class TripScheduleEditor extends Component
{
    use BuildsMonthlyTripSchedule;
    use WithFileUploads;

    public int $userId;

    public string $month;

    /** @var array<int, string> */
    public array $prices = [];

    public string $salaryInput = '';

    public string $newAdjustmentLabel = '';

    public string $newAdjustmentAmount = '';

    public ?int $acceptingTripId = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null> */
    public array $pendingScans = [];

    /** @var array<int, bool> */
    public array $selectedTrips = [];

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

    public function openAcceptForm(int $tripId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canReviewBordero()) {
            abort(403);
        }

        $this->acceptingTripId = $tripId;
    }

    public function cancelAcceptForm(): void
    {
        $this->acceptingTripId = null;
    }

    public function acceptTrip(int $tripId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canReviewBordero()) {
            abort(403);
        }

        $scan = $this->pendingScans[$tripId] ?? null;

        if (! $scan) {
            Notification::make()
                ->title('Allega la scansione del documento per accettare')
                ->danger()
                ->send();

            return;
        }

        $trip = Trip::query()
            ->where('user_id', $this->userId)
            ->findOrFail($tripId);

        if (! $user->isAdmin() && $trip->isExportLockedFor($user)) {
            abort(403, 'Questo record e\' stato esportato e non puo\' piu\' essere modificato.');
        }

        $path = $scan->store('trips', 'public');
        $trip->attachments()->create(['path' => $path]);

        $trip->update([
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
        ]);

        unset($this->pendingScans[$tripId]);
        $this->acceptingTripId = null;

        Notification::make()
            ->title('Bordero accettato')
            ->success()
            ->send();
    }

    public function toggleTripSelection(int $tripId): void
    {
        $this->selectedTrips[$tripId] = ! ($this->selectedTrips[$tripId] ?? false);
    }

    /**
     * Esporta in Excel i viaggi selezionati dell'autista e li "blocca" per
     * questo utente (non admin): non potra' piu' modificarli ne'
     * revisionarli, restano pero' modificabili per l'admin.
     */
    public function exportSelected()
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->canReviewBordero()) {
            abort(403);
        }

        $tripIds = collect($this->selectedTrips)->filter()->keys()->map(fn ($id) => (int) $id)->values();

        if ($tripIds->isEmpty()) {
            Notification::make()->title('Seleziona almeno un viaggio')->danger()->send();

            return;
        }

        $trips = Trip::query()
            ->where('user_id', $this->userId)
            ->whereIn('id', $tripIds)
            ->with(['filiale', 'vehicle', 'extras'])
            ->orderBy('date')
            ->get();

        $driver = User::findOrFail($this->userId);
        $listNumber = TripExportBatch::nextSequenceFor($driver->id);

        TripExportBatch::create([
            'driver_id' => $driver->id,
            'exported_by' => $user->id,
            'sequence_number' => $listNumber,
            'trip_ids' => $trips->pluck('id')->all(),
        ]);

        if (! $user->isAdmin()) {
            Trip::query()->whereIn('id', $trips->pluck('id'))->update([
                'export_locked_by' => $user->id,
                'export_locked_at' => now(),
            ]);
        }

        $this->selectedTrips = [];

        $spreadsheet = DriverTripListExcelExporter::build($driver, $listNumber, $trips);

        return DriverTripListExcelExporter::stream(
            $spreadsheet,
            'lista-' . $listNumber . '-' . str($driver->full_name)->slug() . '.xlsx'
        );
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
