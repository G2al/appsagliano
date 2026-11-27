<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'audit-log';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Log di controllo';
    protected static ?string $modelLabel = 'Movimento registrato';
    protected static ?string $pluralLabel = 'Log di controllo';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer_id')
                    ->label('Chi')
                    ->state(fn (Activity $record) => $record->causer?->full_name ?? 'Sistema'),
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Cosa')
                    ->badge(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Azione')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => 'Creato',
                        'updated' => 'Modificato',
                        'deleted' => 'Eliminato',
                        default => $state ?? 'N/D',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID record'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Modello')
                    ->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->toArray()),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Azione')
                    ->options([
                        'created' => 'Creato',
                        'updated' => 'Modificato',
                        'deleted' => 'Eliminato',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('dettagli')
                    ->label('Dettagli')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Dettaglio modifiche')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalContent(function (Activity $record) {
                        $old = collect($record->properties['old'] ?? [])->map(fn ($v, $k) => "{$k}: " . json_encode($v))->implode("\n");
                        $new = collect($record->properties['attributes'] ?? [])->map(fn ($v, $k) => "{$k}: " . json_encode($v))->implode("\n");

                        return new \Illuminate\Support\HtmlString(
                            '<div class="space-y-2 text-sm"><div><strong>Prima:</strong><pre class="whitespace-pre-wrap">' . e($old ?: '—') . '</pre></div>'
                            . '<div><strong>Dopo:</strong><pre class="whitespace-pre-wrap">' . e($new ?: '—') . '</pre></div></div>'
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
