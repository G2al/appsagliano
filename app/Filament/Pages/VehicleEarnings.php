<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Vehicle;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class VehicleEarnings extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Guadagno veicoli';
    protected static ?string $title = 'Guadagno veicoli';
    protected static ?string $navigationGroup = 'Movimenti';
    protected static string $view = 'filament.pages.vehicle-earnings';
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
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function getVehiclesProperty(): LengthAwarePaginator
    {
        return Vehicle::query()
            ->withCount('trips')
            ->when($this->search !== '', function ($query) {
                $term = '%' . $this->search . '%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('plate', 'like', $term)
                    ->orWhere('company', 'like', $term));
            })
            ->orderBy('plate')
            ->paginate($this->perPage);
    }
}
