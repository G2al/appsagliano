<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPanelModules;
use App\Models\User;
use App\Support\DriverTripsZipExporter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class TripSchedules extends Page
{
    use ChecksPanelModules;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Scheda viaggi';
    protected static ?string $title = 'Scheda viaggi';
    protected static ?string $navigationGroup = 'Movimenti';
    protected static string $view = 'filament.pages.trip-schedules';
    protected string $paginationTheme = 'tailwind';

    #[Url]
    public string $search = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_TRIPS]);
    }

    public function markReviewed(int $userId): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $actor->isAdmin()) {
            abort(403);
        }

        User::query()->whereKey($userId)->update(['trip_schedule_reviewed_at' => now()]);

        Notification::make()
            ->title('Autista visualizzato')
            ->success()
            ->send();
    }

    public function exportDriver(int $userId)
    {
        $driver = User::query()->findOrFail($userId);

        $zip = DriverTripsZipExporter::buildForDriver($driver);

        return response()->streamDownload(function () use ($zip) {
            echo $zip;
        }, 'viaggi-' . str($driver->full_name ?: 'autista')->slug() . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function exportAll()
    {
        $zip = DriverTripsZipExporter::buildForAllDrivers();

        return response()->streamDownload(function () use ($zip) {
            echo $zip;
        }, 'viaggi-tutti-autisti.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function getUsersProperty(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('trips')
            ->withMax('trips as latest_trip_at', 'created_at')
            ->when($this->search !== '', function ($query) {
                $term = '%' . $this->search . '%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term));
            })
            ->orderByDesc('trips_count')
            ->orderBy('name')
            ->paginate($this->perPage);
    }
}
