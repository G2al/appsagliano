<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\DocumentFoldersRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\TripsMonthlyRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class UserResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Utenti';
    protected static ?string $pluralLabel = 'Utenti';
    protected static ?string $modelLabel = 'Utente';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('surname')
                    ->label('Cognome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefono')
                    ->tel()
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('address')
                    ->label('Indirizzo di residenza')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label('Ruolo')
                    ->options([
                        'admin' => 'Admin',
                        'worker' => 'Operatore',
                    ])
                    ->required(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->default('worker')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->isAdmin() ?? false),
                Forms\Components\CheckboxList::make('panel_modules')
                    ->label('Abilitazioni pannello')
                    ->options(User::panelModuleOptions())
                    ->columns(1)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->helperText('Assegna uno o piu moduli: manutenzione, rifornimenti, documenti.'),
                Forms\Components\Placeholder::make('panel_modules_readonly')
                    ->label('Abilitazioni pannello')
                    ->visible(fn (?User $record): bool => ! (auth()->user()?->isAdmin() ?? false) && $record !== null)
                    ->content(function (?User $record): string {
                        if (! $record) {
                            return '-';
                        }

                        $labels = array_map(
                            fn (string $module): string => User::panelModuleOptions()[$module] ?? $module,
                            $record->getSanitizedPanelModules()
                        );

                        return empty($labels) ? 'Nessuna' : implode(', ', $labels);
                    }),
                Forms\Components\Toggle::make('is_approved')
                    ->label('Approvato')
                    ->default(false)
                    ->helperText('Passa a true in automatico quando l\'utente completa la verifica. Puoi comunque forzarlo manualmente.'),
                Forms\Components\Section::make('Verifica account')
                    ->visible(fn (?User $record): bool => $record !== null && $record->verification !== null)
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('verification_summary')
                            ->label('')
                            ->content(function (?User $record): HtmlString {
                                $v = $record?->verification;

                                if (! $v) {
                                    return new HtmlString('Nessun documento caricato.');
                                }

                                $row = function (string $label, ?string $url, ?string $expiry = null): string {
                                    if (! $url) {
                                        return "<div><span class=\"font-medium\">{$label}:</span> non caricato</div>";
                                    }

                                    $link = '<a href="' . e($url) . '" target="_blank" class="font-semibold text-primary-600 underline hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">Apri</a>';
                                    $expiryText = $expiry ? " &middot; scadenza {$expiry}" : '';

                                    return "<div><span class=\"font-medium\">{$label}:</span> {$link}{$expiryText}</div>";
                                };

                                $rows = [
                                    $row('Documento identita (fronte)', $v->identity_front_url, $v->identity_expiry_date?->format('d/m/Y')),
                                    $row('Documento identita (retro)', $v->identity_back_url),
                                    $row('Patente', $v->license_url, $v->license_expiry_date?->format('d/m/Y')),
                                    $row('CQC', $v->cqc_url, $v->cqc_expiry_date?->format('d/m/Y')),
                                    '<div class="mt-2 text-sm text-gray-500">Completata il: ' . ($v->completed_at?->format('d/m/Y H:i') ?? 'non completata') . '</div>',
                                ];

                                return new HtmlString('<div class="space-y-1">' . implode('', $rows) . '</div>');
                            }),
                    ]),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->hint('Lascia vuoto per non cambiare (in modifica).'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('surname')
                    ->label('Cognome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Ruolo')
                    ->colors([
                        'success' => 'admin',
                        'primary' => 'worker',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'admin' ? 'Admin' : 'Operatore'),
                Tables\Columns\TextColumn::make('panel_modules')
                    ->label('Abilitazioni')
                    ->badge()
                    ->formatStateUsing(function (User $record): string {
                        $labels = array_map(
                            fn (string $module): string => User::panelModuleOptions()[$module] ?? $module,
                            $record->getSanitizedPanelModules()
                        );

                        return empty($labels) ? 'Nessuna' : implode(', ', $labels);
                    }),
                Tables\Columns\BadgeColumn::make('is_approved')
                    ->label('Stato')
                    ->colors([
                        'success' => true,
                        'warning' => false,
                    ])
                    ->formatStateUsing(fn (bool $state) => $state ? 'Approvato' : 'In attesa'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Stato approvazione')
                    ->trueLabel('Approvati')
                    ->falseLabel('In attesa')
                    ->placeholder('Tutti'),
            ])
            ->poll('5s')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica utente'),
                Tables\Actions\Action::make('approve')
                    ->label('Approva')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => ! $record->is_approved)
                    ->action(function (User $record) {
                        $record->update(['is_approved' => true]);
                    })
                    ->color('success')
                    ->icon('heroicon-o-check-badge'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approva selezionati')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            DocumentFoldersRelationManager::class,
            TripsMonthlyRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanAccessModules(User::allowedPanelModules());
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
        return static::canViewAny();
    }
}
