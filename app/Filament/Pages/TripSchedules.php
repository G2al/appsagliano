<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPanelModules;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripSchedules extends Page implements HasTable
{
    use ChecksPanelModules;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Scheda viaggi';
    protected static ?string $title = 'Scheda viaggi';
    protected static ?string $navigationGroup = 'Movimenti';
    protected static string $view = 'filament.pages.trip-schedules';

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_TRIPS]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Autista')
                    ->formatStateUsing(fn (User $record) => $record->full_name)
                    ->searchable(['name', 'surname']),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('trips_count')
                    ->label('Viaggi totali')
                    ->counts('trips')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('schedule')
                    ->label('Vedi scheda viaggi')
                    ->icon('heroicon-o-calendar-days')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalHeading(fn (User $record) => 'Scheda viaggi · ' . $record->full_name)
                    ->modalContent(fn (User $record) => view('filament.pages.trip-schedule-modal', [
                        'userId' => $record->id,
                    ])),
            ]);
    }
}
