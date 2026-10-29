<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\FilialeResource\Pages;
use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                Forms\Components\Select::make('platform_id')
                    ->label('Piattaforma')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('goods_type_id', null))
                    ->required(),
                Forms\Components\Select::make('goods_type_id')
                    ->label('Dicitura')
                    ->options(fn (Forms\Get $get) => $get('platform_id')
                        ? GoodsType::query()->where('platform_id', $get('platform_id'))->pluck('name', 'id')
                        : [])
                    ->disabled(fn (Forms\Get $get) => ! $get('platform_id'))
                    ->helperText(fn (Forms\Get $get) => $get('platform_id') ? null : 'Scegli prima la piattaforma.')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nome filiale')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('codice')
                    ->label('Codice filiale')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Forms\Components\Repeater::make('prices')
                    ->label('Listino prezzi (per numero di viaggio nella giornata)')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('trip_order')
                            ->label('Numero viaggio')
                            ->options([
                                1 => '1° viaggio',
                                2 => '2° viaggio',
                                3 => '3° viaggio',
                                4 => '4° viaggio',
                                5 => '5° viaggio',
                                6 => '6° viaggio',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Aggiungi prezzo')
                    ->maxItems(6)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['platform', 'goodsType']))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('platform.name')
                    ->label('Piattaforma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goodsType.name')
                    ->label('Dicitura')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome filiale')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('codice')
                    ->label('Codice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prices_count')
                    ->label('Prezzi impostati')
                    ->counts('prices')
                    ->sortable(),
                Tables\Columns\TextColumn::make('trips_count')
                    ->label('Record bordero')
                    ->counts('trips')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform_id')
                    ->label('Piattaforma')
                    ->options(fn () => Platform::query()->orderBy('name')->pluck('name', 'id')->toArray()),
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
