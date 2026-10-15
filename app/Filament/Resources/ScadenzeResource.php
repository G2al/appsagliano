<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScadenzeResource\Pages;
use App\Models\DocumentExpiry;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ScadenzeResource extends Resource
{
    protected static ?string $model = DocumentExpiry::class;

    protected static ?string $slug = 'scadenze';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Scadenze';
    protected static ?string $pluralLabel = 'Scadenze';
    protected static ?string $modelLabel = 'Scadenza';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_full_name')
                    ->label('Dipendente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_label')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(function (DocumentExpiry $record): string {
                        if ($record->isExpired()) {
                            return 'Scaduto';
                        }

                        $days = $record->daysRemaining();

                        return "Tra {$days} " . ($days === 1 ? 'giorno' : 'giorni');
                    })
                    ->color(fn (DocumentExpiry $record): string => $record->statusColor()),
            ])
            ->defaultSort('expiry_date', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo documento')
                    ->options(DocumentExpiry::LABELS),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageScadenzes::route('/'),
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
        $count = DocumentExpiry::query()->where('expiry_date', '<', now()->toDateString())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
