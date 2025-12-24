<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StationResource\Pages;
use App\Filament\Resources\StationResource\RelationManagers;
use App\Models\Station;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StationResource extends Resource
{
    protected static ?string $model = Station::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Stazioni di servizio';
    protected static ?string $navigationGroup = 'Impostazioni';
    protected static ?string $modelLabel = 'Stazione di servizio';
    protected static ?string $pluralLabel = 'Stazioni di servizio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome stazione')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('address')
                    ->label('Indirizzo')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('credit_balance')
                    ->label('Credito carta')
                    ->prefix('€')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Indirizzo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credit_balance')
                    ->label('Credito carta')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '€ ' . number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
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
            'index' => Pages\ListStations::route('/'),
            'create' => Pages\CreateStation::route('/create'),
            'edit' => Pages\EditStation::route('/{record}/edit'),
        ];
    }
}
