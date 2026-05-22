<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportPeriod
{
    public function __construct(
        public readonly ?Carbon $start,
        public readonly ?Carbon $end,
    ) {
    }

    public static function fromFilters(array $filters): self
    {
        $preset = (string) ($filters['period_preset'] ?? 'current_month');
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if ($preset === 'total') {
            return new self(null, null);
        }

        if ($start || $end) {
            return self::custom($start, $end);
        }

        return $preset === 'last_month'
            ? self::lastMonth()
            : self::currentMonth();
    }

    public static function currentMonth(): self
    {
        return new self(
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
        );
    }

    public static function lastMonth(): self
    {
        $start = now()->subMonthNoOverflow()->startOfMonth();

        return new self(
            $start->copy()->startOfDay(),
            $start->copy()->endOfMonth()->endOfDay(),
        );
    }

    public static function custom(string|null $start, string|null $end): self
    {
        $resolvedStart = $start
            ? Carbon::parse($start)->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $resolvedEnd = $end
            ? Carbon::parse($end)->endOfDay()
            : now()->endOfDay();

        return new self($resolvedStart, $resolvedEnd);
    }

    public function applyToBuilder(Builder $query, string $column = 'date'): Builder
    {
        if ($this->start !== null) {
            $query->where($column, '>=', $this->start);
        }

        if ($this->end !== null) {
            $query->where($column, '<=', $this->end);
        }

        return $query;
    }
}
