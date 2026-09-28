<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\ReportTableCheck;
use Carbon\Carbon;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait InteractsWithReportTableChecks
{
    protected ?array $reportTableChecksCache = null;
    protected ?string $reportTableChecksCacheScope = null;

    abstract protected function getReportTableRowKey(Model $record): string;

    protected function getReportTableCheckColumn(): Tables\Columns\IconColumn
    {
        return Tables\Columns\IconColumn::make('report_table_check')
            ->label('Visto')
            ->boolean()
            ->state(fn (Model $record): bool => $this->isReportTableRowChecked($record))
            ->trueIcon('heroicon-s-check-circle')
            ->falseIcon('heroicon-o-minus-circle')
            ->trueColor('success')
            ->falseColor('gray')
            ->action('toggleReportTableRowCheck')
            ->tooltip(fn (Model $record): string => $this->isReportTableRowChecked($record) ? 'Rimuovi spunta' : 'Segna come visto');
    }

    abstract protected function getReportTableRowKeySql(): string;

    protected function getReportTableCheckedFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('report_table_checked')
            ->label('Visto')
            ->options([
                'checked' => 'Solo spuntati',
                'unchecked' => 'Solo non spuntati',
            ])
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;

                if (! in_array($value, ['checked', 'unchecked'], true)) {
                    return $query;
                }

                $keys = array_keys($this->getCurrentReportTableChecks());
                $expression = DB::raw($this->getReportTableRowKeySql());

                return $value === 'checked'
                    ? $query->whereIn($expression, $keys)
                    : $query->whereNotIn($expression, $keys);
            });
    }

    public function toggleReportTableRowCheck(Model $record): void
    {
        $userId = auth()->id();

        if (! $userId) {
            return;
        }

        $attributes = [
            'user_id' => $userId,
            'table_key' => static::class,
            'filter_key' => $this->getReportTableFilterKey(),
            'row_key' => $this->getReportTableRowKey($record),
        ];

        $existingCheck = ReportTableCheck::query()
            ->where($attributes)
            ->first();

        if ($existingCheck) {
            $existingCheck->delete();
            $this->forgetReportTableChecksCache();

            return;
        }

        ReportTableCheck::query()->create([
            ...$attributes,
            'checked_at' => now(),
        ]);

        $this->forgetReportTableChecksCache();
    }

    protected function isReportTableRowChecked(Model $record): bool
    {
        return array_key_exists(
            $this->getReportTableRowKey($record),
            $this->getCurrentReportTableChecks()
        );
    }

    protected function getCurrentReportTableChecks(): array
    {
        $cacheScope = $this->getReportTableChecksCacheScope();

        if (
            $this->reportTableChecksCache !== null &&
            $this->reportTableChecksCacheScope === $cacheScope
        ) {
            return $this->reportTableChecksCache;
        }

        $userId = auth()->id();

        if (! $userId) {
            $this->reportTableChecksCacheScope = $cacheScope;

            return $this->reportTableChecksCache = [];
        }

        $this->reportTableChecksCacheScope = $cacheScope;

        return $this->reportTableChecksCache = ReportTableCheck::query()
            ->where('user_id', $userId)
            ->where('table_key', static::class)
            ->where('filter_key', $this->getReportTableFilterKey())
            ->pluck('id', 'row_key')
            ->all();
    }

    protected function forgetReportTableChecksCache(): void
    {
        $this->reportTableChecksCache = null;
        $this->reportTableChecksCacheScope = null;
    }

    protected function getReportTableFilterKey(): string
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return $start->toDateString() . '|' . $end->toDateString();
    }

    protected function getReportTableCheckDateRange(): array
    {
        $filters = $this->filters ?? [];
        $startRaw = $filters['start_date'] ?? null;
        $endRaw = $filters['end_date'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [$start, $end];
    }

    protected function getReportTableChecksCacheScope(): string
    {
        return (string) auth()->id() . '|' . static::class . '|' . $this->getReportTableFilterKey();
    }
}
