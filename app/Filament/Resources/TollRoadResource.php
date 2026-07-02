<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TollRoadResource\Pages;
use App\Filament\Resources\TollRoadResource\RelationManagers\ExpensesRelationManager;
use App\Models\TollRoad;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TollRoadResource extends Resource
{
    protected static ?string $model = TollRoad::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Autostrade';
    protected static ?string $modelLabel = 'Autostrada';
    protected static ?string $pluralLabel = 'Autostrade';
    protected static ?string $navigationGroup = 'Finanza';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome autostrada')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->withSum('expenses as expenses_total', 'amount')
                    ->withMax('expenses as last_expense_date', 'date');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expenses_total')
                    ->label('Totale costi')
                    ->formatStateUsing(
                        fn ($state): string => 'EUR ' . number_format((float) ($state ?? 0), 2, ',', '.')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_expense_date')
                    ->label('Ultimo costo')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
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
        return [
            ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTollRoads::route('/'),
            'create' => Pages\CreateTollRoad::route('/create'),
            'edit' => Pages\EditTollRoad::route('/{record}/edit'),
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
