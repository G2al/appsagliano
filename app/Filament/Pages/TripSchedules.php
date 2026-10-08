<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPanelModules;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class TripSchedules extends Page
{
    use ChecksPanelModules;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Scheda viaggi';
    protected static ?string $title = 'Scheda viaggi';
    protected static ?string $navigationGroup = 'Movimenti';
    protected static string $view = 'filament.pages.trip-schedules';

    public string $search = '';

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_TRIPS]);
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsersProperty(): Collection
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
            ->get();
    }
}
