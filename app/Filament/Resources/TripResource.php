<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Veicolo')
                            ->relationship('vehicle', 'plate')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? '')))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('platform_id')
                            ->label('Piattaforma')
                            ->options(fn () => Platform::query()->orderBy('name')->pluck('name', 'id'))
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Forms\Set $set) {
                                $set('goods_type_id', null);
                                $set('filiale_id', null);
                            })
                            ->required(),
                        Forms\Components\Select::make('goods_type_id')
                            ->label('Dicitura')
                            ->options(fn (Forms\Get $get) => $get('platform_id')
                                ? GoodsType::query()->where('platform_id', $get('platform_id'))->orderBy('name')->pluck('name', 'id')
                                : [])
                            ->live()
                            ->dehydrated(false)
                            ->disabled(fn (Forms\Get $get) => ! $get('platform_id'))
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('filiale_id', null))
                            ->required(),
                        Forms\Components\Select::make('filiale_id')
                            ->label('Filiale')
                            ->options(fn (Forms\Get $get) => $get('goods_type_id')
                                ? Filiale::query()
                                    ->where('goods_type_id', $get('goods_type_id'))
                                    ->get()
                                    ->mapWithKeys(fn (Filiale $f) => [$f->id => "{$f->codice} - {$f->name}"])
                                : [])
                            ->disabled(fn (Forms\Get $get) => ! $get('goods_type_id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('scarichi')
                            ->label('Scarichi')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Allegati')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->directory('trips')
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canManagePrice = static::currentUserCanManageBorderoPrice();
        $canReview = static::currentUserCanReviewBordero();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'filiale.platform', 'filiale.goodsType', 'vehicle', 'attachments']))
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
                Tables\Columns\TextColumn::make('filiale.platform.name')
                    ->label('Piattaforma')
                    ->searchable(),
                Tables\Columns\TextColumn::make('filiale.name')
                    ->label('Filiale')
                    ->formatStateUsing(fn ($state, Trip $record) => $record->filiale ? "{$record->filiale->codice} - {$record->filiale->name}" : 'N/D')
                    ->searchable(),
                Tables\Columns\TextColumn::make('filiale.goodsType.name')
                    ->label('Dicitura')
                    ->badge(),
                Tables\Columns\TextColumn::make('trip_order')
                    ->label('N° viaggio')
                    ->formatStateUsing(fn (?int $state) => $state ? "{$state}°" : '—')
                    ->sortable(),
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
                    ->color(fn (string $state) => $state === 'Certificato' ? 'success' : 'warning')
                    ->visible($canManagePrice),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Revisione')
                    ->state(fn (Trip $record) => $record->is_reviewed ? 'Accettato' : 'Da revisionare')
                    ->badge()
                    ->color(fn (Trip $record) => $record->is_reviewed ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()->label('Cestino'),
                Tables\Filters\TernaryFilter::make('reviewed')
                    ->label('Revisione')
                    ->placeholder('Tutti')
                    ->trueLabel('Accettati')
                    ->falseLabel('Da revisionare')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('reviewed_at'),
                        false: fn (Builder $query) => $query->whereNull('reviewed_at'),
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
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label(fn (Trip $record) => $record->is_reviewed ? 'Revisionato' : 'Accetta')
                    ->icon('heroicon-o-check-badge')
                    ->color(fn (Trip $record) => $record->is_reviewed ? 'gray' : 'success')
                    ->visible(fn (Trip $record): bool => ! $record->trashed() && $canReview)
                    ->form([
                        Forms\Components\Placeholder::make('note')
                            ->label('')
                            ->content('Controlla il bordero e, se serve, allega la scansione del documento.'),
                        Forms\Components\FileUpload::make('scan')
                            ->label('Scansione documento')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->directory('trips')
                            ->disk('public')
                            ->visibility('public')
                            ->nullable(),
                    ])
                    ->fillForm(fn (): array => ['scan' => null])
                    ->action(function (Trip $record, array $data): void {
                        if (! empty($data['scan'])) {
                            $record->attachments()->create(['path' => $data['scan']]);
                        }

                        $record->update(['reviewed_at' => now()]);

                        Notification::make()
                            ->title('Bordero accettato')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('set_price')
                    ->label(fn (Trip $record) => $record->is_certified ? 'Modifica prezzo' : 'Imposta prezzo')
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

    public static function currentUserCanReviewBordero(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canReviewBordero();
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
