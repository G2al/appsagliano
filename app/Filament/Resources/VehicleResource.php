<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\RelationManagers;
use App\Models\Movement;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Veicoli';
    protected static ?string $navigationGroup = 'Impostazioni';
    protected static ?string $modelLabel = 'Veicolo';
    protected static ?string $pluralLabel = 'Veicoli';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome veicolo')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('plate')
                    ->label('Targa')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('color')
                    ->label('Colore')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('current_km')
                    ->label('Km attuali')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->nullable(),
                Forms\Components\TextInput::make('maintenance_km')
                    ->label('Km manutenzione')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $statsQuery = Movement::query()
                    ->whereNotNull('vehicle_id')
                    ->selectRaw('
                        vehicle_id,
                        (
                            SUM(
                                CASE
                                    WHEN km_end IS NOT NULL
                                        AND km_start IS NOT NULL
                                        AND km_end >= km_start
                                        AND liters IS NOT NULL
                                        AND liters > 0
                                    THEN km_end - km_start
                                    ELSE 0
                                END
                            ) / NULLIF(
                                SUM(
                                    CASE
                                        WHEN km_end IS NOT NULL
                                            AND km_start IS NOT NULL
                                            AND km_end >= km_start
                                            AND liters IS NOT NULL
                                            AND liters > 0
                                        THEN liters
                                        ELSE 0
                                    END
                                ),
                                0
                            )
                        ) as km_per_liter_avg
                    ')
                    ->groupBy('vehicle_id');

                return $query
                    ->select('vehicles.*')
                    ->leftJoinSub($statsQuery, 'refuel_stats', function ($join) {
                        $join->on('refuel_stats.vehicle_id', '=', 'vehicles.id');
                    })
                    ->addSelect([
                        'km_per_liter_avg' => 'refuel_stats.km_per_liter_avg',
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plate')
                    ->label('Targa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('color')
                    ->label('Colore')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_km')
                    ->label('Km attuali')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('maintenance_km')
                    ->label('Km manutenzione')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('km_per_liter_avg')
                    ->label('Media km/L')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2, ',', '.'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        (float) $state < 3 => 'danger',
                        (float) $state < 3.5 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
