<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Filiale;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TripResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Bordero';
    protected static ?string $modelLabel = 'Record bordero';
    protected static ?string $pluralLabel = 'Bordero';
    protected static ?string $navigationGroup = 'Movimenti';

    public static function form(Form $form): Form
    {
        $canManagePrice = static::currentUserCanManageBorderoPrice();

        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli bordero')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Autista')
                            ->relationship('user', 'name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                            ->searchable(['name', 'surname'])
                            ->preload()
                            ->default(fn () => Auth::id())
                            ->required(),
                        Forms\Components\DateTimePicker::make('date')
                            ->label('Data e ora')
                            ->seconds(false)
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('delivery_note_number')
                            ->label('Numero bordero')
                            ->regex('/^[0-9-]+$/')
                            ->validationMessages(['regex' => 'Il numero bordero puo contenere solo numeri e trattini.'])
                            ->maxLength(30)
                            ->required(),
                        Forms\Components\Radio::make('goods_type')
                            ->label('Tipologia')
                            ->options(Trip::goodsTypeOptions())
                            ->inline()
                            ->required(),
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Veicolo')
                            ->relationship('vehicle', 'plate')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? '')))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('filiale_id')
                            ->label('Filiale')
                            ->relationship('filiale', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Filiale $record) => "{$record->codice} - {$record->name}")
                            ->searchable(['name', 'codice'])
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('scarichi')
                            ->label('Scarichi')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->nullable()
                            ->visible($canManagePrice)
                            ->helperText('Inserendo il prezzo il record risulta certificato.'),
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Allegati')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->directory('trips')
                            ->disk('public')
                            ->visibility('public')
                            ->minFiles(1)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canManagePrice = static::currentUserCanManageBorderoPrice();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'filiale', 'vehicle', 'attachments']))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autista')
                    ->formatStateUsing(fn ($state, Trip $record) => $record->user?->full_name ?? 'N/D')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Veicolo')
                    ->formatStateUsing(fn ($state, Trip $record) => trim(($record->vehicle?->plate ? $record->vehicle->plate . ' - ' : '') . ($record->vehicle?->name ?? '')))
                    ->searchable(),
                Tables\Columns\TextColumn::make('filiale.name')
                    ->label('Filiale')
                    ->formatStateUsing(fn ($state, Trip $record) => $record->filiale ? "{$record->filiale->codice} - {$record->filiale->name}" : 'N/D')
                    ->searchable(),
                Tables\Columns\TextColumn::make('goods_type')
                    ->label('Tipologia')
                    ->formatStateUsing(fn ($state) => Trip::goodsTypeOptions()[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('delivery_note_number')
                    ->label('Numero bordero')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scarichi')
                    ->label('Scarichi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', true)
                    ->placeholder('—')
                    ->sortable()
                    ->visible($canManagePrice),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->state(fn (Trip $record) => $record->is_certified ? 'Certificato' : 'Da certificare')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Certificato' ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()->label('Cestino'),
                Tables\Filters\TernaryFilter::make('certified')
                    ->label('Stato')
                    ->placeholder('Tutti')
                    ->trueLabel('Certificati')
                    ->falseLabel('Da certificare')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('price'),
                        false: fn (Builder $query) => $query->whereNull('price'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('filiale_id')
                    ->label('Filiale')
                    ->options(fn () => Filiale::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Veicolo')
                    ->options(fn () => Vehicle::query()
                        ->orderBy('plate')
                        ->get()
                        ->mapWithKeys(fn ($v) => [$v->id => trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?? ''))])
                        ->toArray()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Autista')
                    ->options(fn () => User::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])
                        ->toArray()),
                Tables\Filters\SelectFilter::make('goods_type')
                    ->label('Tipologia')
                    ->options(Trip::goodsTypeOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('set_price')
                    ->label(fn (Trip $record) => $record->is_certified ? 'Modifica prezzo' : 'Economa')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->visible(fn (Trip $record): bool => ! $record->trashed() && $canManagePrice)
                    ->form([
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                    ])
                    ->fillForm(fn (Trip $record): array => ['price' => $record->price])
                    ->action(fn (Trip $record, array $data) => $record->update(['price' => $data['price']])),
                Tables\Actions\Action::make('attachment')
                    ->label('Allegati')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (Trip $record): bool => ! $record->trashed() && $record->attachments->isNotEmpty())
                    ->url(fn (Trip $record) => route('trips.attachment', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Trip $record): bool => ! $record->trashed()),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }

    public static function currentUserCanManageBorderoPrice(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageBorderoPrice();
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
        return static::canViewAny() && (! method_exists($record, 'trashed') || ! $record->trashed());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && (! method_exists($record, 'trashed') || ! $record->trashed());
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canRestore(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canRestoreAny(): bool
    {
        return static::canViewAny();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canViewAny();
    }
}
