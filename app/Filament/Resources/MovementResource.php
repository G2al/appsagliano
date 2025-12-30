<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovementResource\Pages;
use App\Models\Movement;
use App\Models\Station;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MovementResource extends Resource
{
    protected static ?string $model = Movement::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationLabel = 'Rifornimenti';
    protected static ?string $pluralLabel = 'Rifornimenti';
    protected static ?string $modelLabel = 'Rifornimento';
    protected static ?string $navigationGroup = 'Movimenti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli movimento')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Autore')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id())
                            ->required(),
                        Forms\Components\DateTimePicker::make('date')
                            ->label('Data movimento')
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Select::make('station_id')
                            ->label('Stazione')
                            ->options(fn () => Station::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => ($s->name ?: 'Senza nome')])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Veicolo')
                            ->options(fn () => \App\Models\Vehicle::query()
                                ->orderBy('plate')
                                ->get()
                                ->mapWithKeys(function ($v) {
                                    $label = trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?: 'Senza nome'));
                                    return [$v->id => $label];
                                })
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('km_start')
                            ->label('Km iniziali')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('km_end')
                            ->label('Km finali')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('liters')
                            ->label('Litri')
                            ->numeric()
                            ->step('0.01')
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo')
                            ->numeric()
                            ->step('0.01')
                            ->required(),
                        Forms\Components\TextInput::make('adblue')
                            ->label('AdBlue')
                            ->numeric()
                            ->step('0.01')
                            ->nullable(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull()
                            ->nullable(),
                        Forms\Components\FileUpload::make('photo_path')
                            ->label('Ricevuta')
                            ->image()
                            ->directory('receipts')
                            ->disk('public')
                            ->visibility('public')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autore')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('station.name')
                    ->label('Stazione')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Veicolo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('liters')
                    ->label('Litri')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('km_per_liter')
                    ->label('Media ticket km/L')
                    ->formatStateUsing(fn ($state) => $state === null ? 'N/D' : number_format((float) $state, 2, ',', '.'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        (float) $state < 3 => 'danger',
                        (float) $state < 3.5 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', true)
                    ->sortable(),
                Tables\Columns\ImageColumn::make('photo_url')
                    ->label('Ricevuta')
                    ->square()
                    ->height(40),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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
                    ->visible(fn (Movement $record) => filled($record->photo_url))
                    ->url(fn (Movement $record) => route('movements.attachment', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            DB::transaction(function () use ($records) {
                                $records->each(function (Movement $record): void {
                                    $stationId = $record->station_id;
                                    $charge = (float) ($record->station_charge ?? 0);

                                    if ($stationId && $charge > 0) {
                                        Station::whereKey($stationId)->increment('credit_balance', $charge);
                                    }

                                    $record->delete();
                                });
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovements::route('/'),
            'create' => Pages\CreateMovement::route('/create'),
            'edit' => Pages\EditMovement::route('/{record}/edit'),
        ];
    }
}
