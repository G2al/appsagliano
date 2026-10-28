<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleDocumentExpiryResource\Pages;
use App\Models\User;
use App\Models\VehicleDocumentExpiry;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VehicleDocumentExpiryResource extends Resource
{
    protected static ?string $model = VehicleDocumentExpiry::class;

    protected static ?string $slug = 'scadenze-veicoli';

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Scadenze Veicoli';
    protected static ?string $pluralLabel = 'Scadenze Veicoli';
    protected static ?string $modelLabel = 'Scadenza Veicolo';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle_label')
                    ->label('Veicolo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipologia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(function (VehicleDocumentExpiry $record): string {
                        if ($record->isExpired()) {
                            return 'Scaduto';
                        }

                        $days = $record->daysRemaining();

                        return "Tra {$days} " . ($days === 1 ? 'giorno' : 'giorni');
                    })
                    ->color(fn (VehicleDocumentExpiry $record): string => $record->statusColor()),
            ])
            ->defaultSort('expiry_date', 'asc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVehicleDocumentExpiries::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canReceiveExpiryNotifications();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = VehicleDocumentExpiry::query()->where('expiry_date', '<', now()->toDateString())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
