<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Filament\Widgets\Concerns\RendersTripDetails;
use App\Models\Trip;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TripsByGoodsTypeTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Bordero per tipologia';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw('
                MIN(trips.id) as id,
                trips.goods_type as goods_type,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('trips.goods_type');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('goods_type')
                ->label('Tipologia')
                ->formatStateUsing(fn ($state) => Trip::goodsTypeOptions()[$state] ?? $state)
                ->badge()
                ->sortable(),
            Tables\Columns\TextColumn::make('trips_count')
                ->label('Record')
                ->sortable(),
            Tables\Columns\TextColumn::make('certified_count')
                ->label('Certificati')
                ->sortable(),
            Tables\Columns\TextColumn::make('pending_count')
                ->label('Da certificare')
                ->state(fn ($record) => (int) $record->trips_count - (int) $record->certified_count),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Totale certificato')
                ->money('EUR', true)
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            $this->getReportTableCheckedFilter(),
        ];
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('goods-type:', trips.goods_type)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'goods-type:' . (string) $record->goods_type;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi record')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Bordero per tipologia')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('goods_type', (string) $record->goods_type)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        Trip::goodsTypeOptions()[$record->goods_type] ?? (string) $record->goods_type
                    ));
                }),
        ];
    }
}
