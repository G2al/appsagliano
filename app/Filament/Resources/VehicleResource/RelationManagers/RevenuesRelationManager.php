<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use App\Models\User;
use App\Models\VatSetting;
use App\Models\VehicleRevenue;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RevenuesRelationManager extends RelationManager
{
    protected static string $relationship = 'revenues';

    protected static ?string $title = 'Entrate veicolo';

    protected static ?string $recordTitleAttribute = 'date';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Data')
                    ->default(now()->toDateString())
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nome entrata')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount_ex_vat')
                    ->label('Entrata senza IVA')
                    ->prefix('EUR')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        $set('amount_inc_vat', self::calculateAmountIncVat(
                            $get('amount_ex_vat'),
                            $get('vat_percentage')
                        ));
                    }),
                Forms\Components\TextInput::make('vat_percentage')
                    ->label('IVA applicata')
                    ->suffix('%')
                    ->numeric()
                    ->default(fn (?VehicleRevenue $record): float => (float) ($record?->vat_percentage ?? VatSetting::currentPercentage()))
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('amount_inc_vat')
                    ->label('Entrata con IVA')
                    ->prefix('EUR')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Calcolata automaticamente in base all IVA configurata nel sistema.'),
                Forms\Components\FileUpload::make('attachment_path')
                    ->label('Allegato')
                    ->disk('public')
                    ->directory(fn (): string => 'vehicle-revenues/' . $this->ownerRecord->getKey())
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                    ])
                    ->visibility('public')
                    ->helperText('Formati supportati per lo scarico PDF unico: PDF, JPG, PNG, GIF, WEBP.')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_ex_vat')
                    ->label('Entrata senza IVA')
                    ->formatStateUsing(
                        fn ($state): string => 'EUR ' . number_format((float) $state, 2, ',', '.')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_percentage')
                    ->label('IVA')
                    ->formatStateUsing(
                        fn ($state): string => number_format((float) $state, 2, ',', '.') . '%'
                    ),
                Tables\Columns\TextColumn::make('amount_inc_vat')
                    ->label('Entrata con IVA')
                    ->formatStateUsing(
                        fn ($state): string => 'EUR ' . number_format((float) $state, 2, ',', '.')
                    )
                    ->sortable(),
                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Allegato')
                    ->boolean()
                    ->state(fn (VehicleRevenue $record): bool => filled($record->attachment_path)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuova entrata'),
            ])
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->label('Periodo')
                    ->form([
                        Forms\Components\Select::make('period_preset')
                            ->label('Periodo')
                            ->options([
                                'current_month' => 'Questo mese',
                                'last_month' => 'Mese scorso',
                                'current_year' => 'Anno corrente',
                                'last_30_days' => 'Ultimi 30 giorni',
                                'total' => 'Totale',
                            ])
                            ->default('current_month')
                            ->live()
                            ->afterStateHydrated(function (Set $set, ?string $state): void {
                                $this->applyPeriodPreset($set, $state);
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $this->applyPeriodPreset($set, $state);
                            }),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Dal')
                            ->default(now()->startOfMonth()->toDateString())
                            ->disabled(fn (Get $get): bool => $get('period_preset') === 'total')
                            ->live(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Al')
                            ->default(now()->toDateString())
                            ->disabled(fn (Get $get): bool => $get('period_preset') === 'total')
                            ->live(),
                    ])
                    ->columns(3)
                    ->default([
                        'period_preset' => 'current_month',
                        'start_date' => now()->startOfMonth()->toDateString(),
                        'end_date' => now()->toDateString(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['start_date'] ?? null) {
                            $query->whereDate('date', '>=', $data['start_date']);
                        }

                        if ($data['end_date'] ?? null) {
                            $query->whereDate('date', '<=', $data['end_date']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('openAttachment')
                    ->label('Apri allegato')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (VehicleRevenue $record): ?string => $record->attachment_url)
                    ->openUrlInNewTab()
                    ->visible(fn (VehicleRevenue $record): bool => filled($record->attachment_url)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    private static function calculateAmountIncVat(mixed $amountExVat, mixed $vatPercentage): float
    {
        $amount = (float) ($amountExVat ?? 0);
        $vat = (float) ($vatPercentage ?? 0);

        return round($amount * (1 + ($vat / 100)), 2);
    }

    private function applyPeriodPreset(Set $set, ?string $preset): void
    {
        if ($preset === 'total') {
            $set('start_date', null);
            $set('end_date', null);

            return;
        }

        if ($preset === 'last_month') {
            $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();

            $set('start_date', $start->toDateString());
            $set('end_date', $start->copy()->endOfMonth()->toDateString());

            return;
        }

        if ($preset === 'current_year') {
            $start = Carbon::now()->startOfYear();

            $set('start_date', $start->toDateString());
            $set('end_date', Carbon::now()->toDateString());

            return;
        }

        if ($preset === 'last_30_days') {
            $set('start_date', Carbon::now()->subDays(29)->toDateString());
            $set('end_date', Carbon::now()->toDateString());

            return;
        }

        $set('start_date', Carbon::now()->startOfMonth()->toDateString());
        $set('end_date', Carbon::now()->toDateString());
    }
}
