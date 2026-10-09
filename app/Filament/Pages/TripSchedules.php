<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPanelModules;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function getUsersProperty(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('trips')
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
