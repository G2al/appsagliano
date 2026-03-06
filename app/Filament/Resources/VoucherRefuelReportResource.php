<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\VoucherRefuelReportResource\Pages;
use App\Models\Movement;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VoucherRefuelReportResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = Movement::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Buoni rifornimenti';
    protected static ?string $navigationGroup = 'Buoni';
    protected static ?string $modelLabel = 'Rifornimento con buono';
    protected static ?string $pluralLabel = 'Rifornimenti con buono';
    protected static ?string $slug = 'voucher-refuels';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('is_voucher', true)
                ->with(['user', 'station', 'vehicle'])
            )
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autore')
                    ->formatStateUsing(fn (Movement $record) => $record->user ? trim(($record->user->name ?? '') . ' ' . ($record->user->surname ?? '')) : 'N/D')
                    ->searchable(['users.name', 'users.surname'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('station.name')
                    ->label('Stazione')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Veicolo')
                    ->formatStateUsing(fn ($state, Movement $record) => trim(($state ? $state . ' - ' : '') . ($record->vehicle?->name ?? 'Veicolo')))
                    ->searchable(['vehicles.plate', 'vehicles.name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('liters')
                    ->label('Litri')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo buono')
                    ->money('EUR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('km_per_liter')
                    ->label('Media km/L')
                    ->formatStateUsing(fn ($state) => $state === null ? 'N/D' : number_format((float) $state, 2, ',', '.'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        (float) $state < 3 => 'danger',
                        (float) $state < 3.5 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('start')
                            ->label('Dal')
                            ->default(fn () => Carbon::now()->startOfMonth()),
                        DatePicker::make('end')
                            ->label('Al')
                            ->default(fn () => Carbon::now()),
                    ])
                    ->default([
                        'start' => Carbon::now()->startOfMonth()->toDateString(),
                        'end' => Carbon::now()->toDateString(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['start'] ?? null) {
                            $query->whereDate('date', '>=', $data['start']);
                        }

                        if ($data['end'] ?? null) {
                            $query->whereDate('date', '<=', $data['end']);
                        }
                    }),
                Tables\Filters\SelectFilter::make('station_id')
                    ->label('Stazione')
                    ->options(fn () => Station::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Veicolo')
                    ->options(fn () => Vehicle::query()
                        ->orderBy('plate')
                        ->get()
                        ->mapWithKeys(fn ($v) => [$v->id => trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?? ''))])
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')
                    ->label('Stampa')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Movement $record) => route('movements.receipt', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('attachment')
                    ->label('Ricevuta')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (Movement $record): bool => filled($record->photo_url))
                    ->url(fn (Movement $record) => route('movements.attachment', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoucherRefuelReports::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_REFUELS]);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

