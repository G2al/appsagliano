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
            ->join('filiali', 'filiali.id', '=', 'trips.filiale_id')
            ->join('goods_types', 'goods_types.id', '=', 'filiali.goods_type_id')
            ->selectRaw('
                MIN(trips.id) as id,
                goods_types.id as goods_type_id,
                goods_types.name as goods_type_name,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('goods_types.id', 'goods_types.name');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('goods_type_name')
                ->label('Tipologia')
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
        return "CONCAT('goods-type:', goods_types.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'goods-type:' . (string) $record->goods_type_id;
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
                        ->whereHas('filiale.goodsType', fn ($q) => $q->where('id', (int) $record->goods_type_id))
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        (string) $record->goods_type_name
                    ));
                }),
        ];
    }
}
