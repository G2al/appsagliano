<?php

namespace App\Filament\Resources\StationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CardsRelationManager extends RelationManager
{
    protected static string $relationship = 'cards';

    protected static ?string $title = 'Carte di credito';

    protected static ?string $recordTitleAttribute = 'number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->label('Numero carta')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('label')
                    ->label('Etichetta')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Numero carta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etichetta')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aggiunta il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
