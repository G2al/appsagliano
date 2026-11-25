<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScadenzeResource\Pages;
use App\Models\DocumentExpiry;
use App\Models\User;
use App\Models\UserVerification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
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
            ->actions([
                Tables\Actions\Action::make('gestisci')
                    ->label('Gestisci')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (DocumentExpiry $record) => $record->document_label . ' — ' . $record->user_full_name)
                    ->modalSubmitActionLabel('Salva')
                    ->form(fn (DocumentExpiry $record) => [
                        ...self::documentPhotoPlaceholders($record),
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Nuova scadenza')
                            ->default($record->expiry_date)
                            ->required(),
                    ])
                    ->action(function (DocumentExpiry $record, array $data): void {
                        $verification = UserVerification::query()->find($record->user_verification_id);

                        if (! $verification) {
                            Notification::make()->danger()->title('Impossibile trovare il documento da aggiornare.')->send();

                            return;
                        }

                        $field = self::expiryFieldFor($record->document_type);
                        $verification->update([$field => $data['expiry_date']]);

                        Notification::make()->success()->title('Scadenza aggiornata')->send();
                    }),
            ])
            ->bulkActions([]);
    }

    private static function expiryFieldFor(string $documentType): string
    {
        return match ($documentType) {
            'identity' => 'identity_expiry_date',
            'license' => 'license_expiry_date',
            'cqc' => 'cqc_expiry_date',
            'tachograph' => 'tachograph_expiry_date',
            default => 'identity_expiry_date',
        };
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function documentPhotoPlaceholders(DocumentExpiry $record): array
    {
        $verification = UserVerification::query()->find($record->user_verification_id);

        if (! $verification) {
            return [];
        }

        $urls = match ($record->document_type) {
            'identity' => array_filter([$verification->identity_front_url, $verification->identity_back_url]),
            'license' => array_filter([$verification->license_url, $verification->license_back_url]),
            'cqc' => array_filter([$verification->cqc_url, $verification->cqc_back_url]),
            'tachograph' => array_filter([$verification->tachograph_front_url, $verification->tachograph_back_url]),
            default => [],
        };

        if (empty($urls)) {
            return [];
        }

        return [
            Forms\Components\View::make('filament.partials.attachment-preview')
                ->viewData([
                    'items' => collect($urls)->values()->map(fn ($url, $i) => [
                        'url' => $url,
                        'label' => 'documento ' . ($i + 1),
                    ])->all(),
                ])
                ->columnSpanFull(),
        ];
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
