<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtraCostResource\Pages;
use App\Models\ExtraCost;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExtraCostResource extends Resource
{
    protected static ?string $model = ExtraCost::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Costi extra';
    protected static ?string $modelLabel = 'Costo extra';
    protected static ?string $pluralLabel = 'Costi extra';
    protected static ?string $navigationGroup = 'Finanza';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Data')
                    ->default(now()->toDateString())
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->label('Dicitura')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->prefix('EUR')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Dicitura')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->formatStateUsing(
                        fn ($state): string => 'EUR ' . number_format((float) $state, 2, ',', '.')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExtraCosts::route('/'),
            'create' => Pages\CreateExtraCost::route('/create'),
            'edit' => Pages\EditExtraCost::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }
}
