<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\PlatformResource\Pages;
use App\Models\Platform;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlatformResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = Platform::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Piattaforme';
    protected static ?string $modelLabel = 'Piattaforma';
    protected static ?string $pluralLabel = 'Piattaforme';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome piattaforma')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('address')
                    ->label('Indirizzo')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome piattaforma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Indirizzo')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('trips_count')
                    ->label('Viaggi')
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
            ->before(function (Tables\Actions\DeleteAction $action, Platform $record): void {
                if (! $record->trips()->withTrashed()->exists()) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title('Impossibile eliminare')
                    ->body('Questa piattaforma e usata da uno o piu viaggi.')
                    ->send();

                $action->cancel();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatforms::route('/'),
            'create' => Pages\CreatePlatform::route('/create'),
            'edit' => Pages\EditPlatform::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_TRIPS]);
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
