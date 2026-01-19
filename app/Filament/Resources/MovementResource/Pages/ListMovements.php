<?php

namespace App\Filament\Resources\MovementResource\Pages;

use App\Filament\Resources\MovementResource;
use App\Filament\Resources\MovementResource\Widgets\MovementStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMovements extends ListRecords
{
    protected static string $resource = MovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
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
