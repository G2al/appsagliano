<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceResource\Pages;
use App\Models\Maintenance;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class MaintenanceResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Manutenzioni';
    protected static ?string $modelLabel = 'Manutenzione';
    protected static ?string $pluralLabel = 'Manutenzioni';
    protected static ?string $navigationGroup = 'Movimenti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli manutenzione')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Autore')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => Auth::id())
                            ->required(),
                        Forms\Components\DateTimePicker::make('date')
                            ->label('Data')
                            ->seconds(false)
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornitore')
                            ->relationship('supplier', 'name')
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
                        Forms\Components\TextInput::make('km_current')
                            ->label('Km manutenzione')
                            ->numeric()
                            ->required()
                            ->helperText('Valore unico usato per le manutenzioni.'),
                        Forms\Components\TextInput::make('km_after')
                            ->label('Prossima manutenzione (km)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(function (Get $get): string {
                                $vehicleId = $get('vehicle_id');
                                if (! $vehicleId) {
                                    return '0 = nessun avviso. Ultimi km manutenzione: N/D';
                                }

                                $lastKm = Maintenance::query()
                                    ->where('vehicle_id', $vehicleId)
                                    ->orderByDesc('date')
                                    ->orderByDesc('id')
                                    ->value('km_current');

                                if ($lastKm === null) {
                                    return '0 = nessun avviso. Ultimi km manutenzione: N/D';
                                }

                                return '0 = nessun avviso. Ultimi km manutenzione: ' . number_format((float) $lastKm, 0, ',', '.');
                            }),
                        Forms\Components\DatePicker::make('next_maintenance_date')
                            ->label('Prossima manutenzione (data)')
                            ->helperText('Lascia vuoto per nessun avviso a data.'),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo')
                            ->numeric()
                            ->step('0.01')
                            ->required(),
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Numero bolla')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                            ->label('Dettagli intervento')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('attachment_path')
                            ->label('Allegato')
                            ->image()
                            ->directory('maintenances')
                            ->disk('public')
                            ->visibility('public')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autore')
                    ->formatStateUsing(fn ($record) => $record->user ? trim($record->user->name . ' ' . $record->user->surname) : 'N/D')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.plate')
                    ->label('Veicolo')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->vehicle?->plate ? $record->vehicle->plate . ' - ' : '') . ($record->vehicle?->name ?? '')))
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numero bolla')
                    ->searchable(),
                Tables\Columns\TextColumn::make('km_current')
                    ->label('Km manutenzione')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', true)
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')
                    ->label('Stampa')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Maintenance $record) => route('maintenances.receipt', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('attachment')
                    ->label('Ricevuta')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (Maintenance $record) => filled($record->attachment_url))
                    ->url(fn (Maintenance $record) => route('maintenances.attachment', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                Tables\Actions\BulkAction::make('download_bolle')
                    ->label('Scarica bolle')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->toArray();

                        if (empty($ids)) {
                            return;
                        }

                        $token = base64_encode(json_encode($ids));

                        return redirect()->route('maintenances.download-bolle', ['token' => $token]);
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenances::route('/'),
            'create' => Pages\CreateMaintenance::route('/create'),
            'edit' => Pages\EditMaintenance::route('/{record}/edit'),
        ];
    }
}
