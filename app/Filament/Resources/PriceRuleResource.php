<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceRuleResource\Pages;
use App\Models\GoodsType;
use App\Models\PriceRule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PriceRuleResource extends Resource
{
    protected static ?string $model = PriceRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Regole prezzo';
    protected static ?string $modelLabel = 'Regola prezzo';
    protected static ?string $pluralLabel = 'Regole prezzo';
    protected static ?string $navigationGroup = 'Impostazioni';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome regola')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('bancali')
                    ->label('Veicoli a cui si applica')
                    ->options([
                        7 => 'Solo veicoli con 7 bancali',
                        10 => 'Solo veicoli con 10 bancali',
                        15 => 'Solo veicoli con 15 bancali',
                        23 => 'Solo veicoli con 23 bancali',
                    ])
                    ->placeholder('Tutti i veicoli')
                    ->helperText('Lascia vuoto per applicarla a qualsiasi veicolo.')
                    ->nullable(),
                Forms\Components\Repeater::make('steps')
                    ->label('Sequenza diciture e prezzi fissi')
                    ->relationship()
                    ->orderColumn('position')
                    ->schema([
                        Forms\Components\Select::make('goods_type_id')
                            ->label('Dicitura')
                            ->options(fn () => GoodsType::query()->with('platform')->get()->mapWithKeys(
                                fn (GoodsType $gt) => [$gt->id => ($gt->platform?->name ?? 'N/D') . ' - ' . $gt->name]
                            ))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo autista (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('owner_price')
                            ->label('Prezzo guadagno (€)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->nullable(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Aggiungi passaggio')
                    ->reorderable()
                    ->minItems(1)
                    ->helperText('La posizione nell\'elenco corrisponde al numero di viaggio del veicolo in giornata: 1° passaggio = 1° viaggio del veicolo, ecc.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('steps')->with('steps.goodsType'))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bancali')
                    ->label('Veicoli')
                    ->formatStateUsing(fn (?int $state) => $state ? "{$state} bancali" : 'Tutti i veicoli')
                    ->badge(),
                Tables\Columns\TextColumn::make('steps')
                    ->label('Sequenza')
                    ->state(fn (PriceRule $record) => $record->steps->map(fn ($s) => $s->goodsType?->name ?? 'N/D')->implode(' → '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('steps_count')
                    ->label('Passaggi')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceRules::route('/'),
            'create' => Pages\CreatePriceRule::route('/create'),
            'edit' => Pages\EditPriceRule::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
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
