<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VatSettingResource\Pages;
use App\Models\User;
use App\Models\VatSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VatSettingResource extends Resource
{
    protected static ?string $model = VatSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Iva';
    protected static ?string $modelLabel = 'Iva';
    protected static ?string $pluralLabel = 'Iva';
    protected static ?string $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 99;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configurazione IVA')
                    ->schema([
                        Forms\Components\TextInput::make('percentage')
                            ->label('IVA')
                            ->numeric()
                            ->required()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Usata come IVA di default per le nuove manutenzioni. Le manutenzioni gia salvate mantengono il valore storico.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('percentage')
                    ->label('IVA')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.') . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aggiornata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVatSettings::route('/'),
            'create' => Pages\CreateVatSetting::route('/create'),
            'edit' => Pages\EditVatSetting::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && ! static::$model::query()->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
