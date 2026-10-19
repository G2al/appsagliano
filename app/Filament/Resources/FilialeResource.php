<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\FilialeResource\Pages;
use App\Models\Filiale;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FilialeResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = Filiale::class;

    protected static ?string $slug = 'filiali';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Filiali';
    protected static ?string $modelLabel = 'Filiale';
    protected static ?string $pluralLabel = 'Filiali';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome filiale')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('codice')
                    ->label('Codice filiale')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome filiale')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('codice')
                    ->label('Codice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trips_count')
                    ->label('Record bordero')
                    ->counts('trips')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                static::deleteAction(),
            ])
            ->bulkActions([]);
    }

    public static function deleteAction(): Tables\Actions\DeleteAction
    {
        return Tables\Actions\DeleteAction::make()
            ->before(function (Tables\Actions\DeleteAction $action, Filiale $record): void {
                if (! $record->trips()->withTrashed()->exists()) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title('Impossibile eliminare')
                    ->body('Questa filiale e usata da uno o piu record bordero.')
                    ->send();

                $action->cancel();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiliales::route('/'),
            'create' => Pages\CreateFiliale::route('/create'),
            'edit' => Pages\EditFiliale::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_TRIPS, User::PANEL_MODULE_BORDERO]);
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
        return false;
    }
}
