<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Platform;
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
    protected static ?string $navigationLabel = 'Viaggi';
    protected static ?string $modelLabel = 'Viaggio';
    protected static ?string $pluralLabel = 'Viaggi';
    protected static ?string $navigationGroup = 'Movimenti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli viaggio')
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
                        Forms\Components\Select::make('platform_id')
                            ->label('Piattaforma')
                            ->relationship('platform', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Veicolo')
                            ->relationship('vehicle', 'plate')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? '')))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Repeater::make('destinations')
                            ->label('Destinazioni')
                            ->simple(
                                Forms\Components\TextInput::make('destination')
                                    ->label('Destinazione')
                                    ->required()
                                    ->maxLength(255)
                            )
                            ->addActionLabel('Aggiungi destinazione')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Radio::make('goods_type')
                            ->label('Dicitura')
                            ->options(Trip::goodsTypeOptions())
                            ->inline()
                            ->required(),
                        Forms\Components\TextInput::make('delivery_note_number')
                            ->label('Bolla')
                            ->inputMode('numeric')
                            ->regex('/^\d+$/')
                            ->maxLength(30)
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo viaggio (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->nullable()
                            ->helperText('Inserendo il prezzo il viaggio risulta certificato.'),
                        Forms\Components\FileUpload::make('attachment_path')
                            ->label('Allegato')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->directory('trips')
                            ->disk('public')
                            ->visibility('public')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'platform', 'vehicle']))
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
                Tables\Columns\TextColumn::make('platform.name')
                    ->label('Piattaforma')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Veicolo')
                    ->formatStateUsing(fn ($state, Trip $record) => trim(($record->vehicle?->plate ? $record->vehicle->plate . ' - ' : '') . ($record->vehicle?->name ?? '')))
                    ->searchable(),
                Tables\Columns\TextColumn::make('destinations')
                    ->label('Destinazioni')
                    ->state(fn (Trip $record) => $record->destinations_label)
                    ->wrap()
                    ->limit(60),
                Tables\Columns\TextColumn::make('goods_type')
                    ->label('Dicitura')
                    ->formatStateUsing(fn ($state) => Trip::goodsTypeOptions()[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('delivery_note_number')
                    ->label('Bolla')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', true)
                    ->placeholder('—')
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('platform_id')
                    ->label('Piattaforma')
                    ->options(fn () => Platform::query()->orderBy('name')->pluck('name', 'id')->toArray()),
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
                    ->label('Dicitura')
                    ->options(Trip::goodsTypeOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('set_price')
                    ->label(fn (Trip $record) => $record->is_certified ? 'Modifica prezzo' : 'Certifica')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->visible(fn (Trip $record): bool => ! $record->trashed())
                    ->form([
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo viaggio (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                    ])
                    ->fillForm(fn (Trip $record): array => ['price' => $record->price])
                    ->action(fn (Trip $record, array $data) => $record->update(['price' => $data['price']])),
                Tables\Actions\Action::make('attachment')
                    ->label('Allegato')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (Trip $record): bool => ! $record->trashed() && filled($record->attachment_path))
                    ->url(fn (Trip $record) => route('trips.attachment', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download_attachment')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Trip $record): bool => ! $record->trashed() && filled($record->attachment_path))
                    ->url(fn (Trip $record) => route('trips.attachment.download', $record)),
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
