<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPanelModules;
use App\Models\User;
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
    protected static ?string $navigationLabel = 'Scheda bordero';
    protected static ?string $title = 'Scheda bordero';
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
        $user = Auth::user();

        return $user instanceof User && ($user->isAdmin() || $user->canAccessBorderoArea());
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function getUsersProperty(): LengthAwarePaginator
    {
        /** @var User $current */
        $current = Auth::user();

        return User::query()
            ->withCount([
                'trips',
                'trips as reviewed_trips_count' => fn ($query) => $query->whereNotNull('reviewed_at'),
                'trips as unreviewed_trips_count' => fn ($query) => $query->whereNull('reviewed_at'),
            ])
            ->withSum(['trips as certified_trips_total' => fn ($query) => $query->whereNotNull('price')], 'price')
            ->with('assignedManagers')
            ->when(
                ! $current->isAdmin() && $current->hasAssignedDrivers(),
                fn ($query) => $query->whereIn('id', $current->assignedDrivers()->pluck('users.id'))
            )
            ->when($this->search !== '', function ($query) {
                $term = '%' . $this->search . '%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }
}
