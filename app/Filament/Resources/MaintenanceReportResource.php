<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceReportResource\Pages;
use App\Models\Maintenance;
use App\Models\Supplier;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceReportResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report manutenzioni';
    protected static ?string $navigationGroup = 'Report';
    protected static ?string $slug = 'maintenance-reports';

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle', 'supplier']))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Veicolo')
                    ->formatStateUsing(fn ($state, $record) => trim(($state ? $state . ' - ' : '') . ($record->vehicle?->name ?? 'Veicolo')))
                    ->sortable()
                    ->searchable(['vehicles.plate', 'vehicles.name']),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('km_current')
                    ->label('Km manutenzione')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N° bolla')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('attachment_url')
                    ->label('Allegato')
                    ->state(fn ($record) => $record->attachment_url ? 'Stampa' : 'N/D')
                    ->url(fn ($record) => $record->attachment_url ? route('maintenances.attachment', $record) : null, true)
                    ->openUrlInNewTab()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Veicolo')
                    ->options(fn () => Vehicle::query()
                        ->orderBy('plate')
                        ->get()
                        ->mapWithKeys(fn ($v) => [$v->id => trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?? ''))])
                        ->toArray()),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('print_attachment')
                    ->label('Stampa allegato')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => $record->attachment_url ? route('maintenances.attachment', $record) : null, true)
                    ->visible(fn ($record) => filled($record->attachment_url))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceReports::route('/'),
        ];
    }
}
