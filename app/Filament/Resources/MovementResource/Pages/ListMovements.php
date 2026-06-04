<?php

namespace App\Filament\Resources\MovementResource\Pages;

use App\Filament\Resources\MovementResource;
use App\Filament\Resources\MovementResource\Widgets\MovementStatsWidget;
use App\Models\Movement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMovements extends ListRecords
{
    protected static string $resource = MovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('recalculateTicketAverages')
                ->label('Ricalcola medie')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->size('sm')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->requiresConfirmation()
                ->modalHeading('Ricalcolare km iniziali e medie ticket?')
                ->modalDescription('Attenzione: questa azione riallinea tutti i rifornimenti in ordine cronologico per veicolo e ricalcola km iniziali e media ticket. Non modifica prezzi, litri, credito stazione, ricevute o notifiche.')
                ->action(function (): void {
                    $result = Movement::realignAllVehicleSequences();

                    Notification::make()
                        ->title('Ricalcolo completato')
                        ->body("Veicoli: {$result['vehicles']} · Rifornimenti analizzati: {$result['movements']} · Record aggiornati: {$result['updated']}")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MovementStatsWidget::class,
        ];
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        $search = $this->getTableSearch();

        if (blank($search)) {
            return $query;
        }

        foreach ($this->extractTableSearchWords($search) as $searchWord) {
            $like = '%' . $searchWord . '%';

            $query->where(function (Builder $query) use ($like) {
                $query
                    ->whereHas('vehicle', function (Builder $query) use ($like) {
                        $query->where('plate', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    })
                    ->orWhereHas('station', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('user', function (Builder $query) use ($like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('surname', 'like', $like);
                    });
            });
        }

        return $query;
    }
}
