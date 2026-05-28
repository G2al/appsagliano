@php
    $columns = $this->getColumns();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);
    $breakdownColumns = $this->getActiveBreakdownColumns();
    $breakdownRows = $this->getActiveBreakdownRows();
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview grid gap-y-4">
    @if ($hasHeading || $hasDescription)
        <div class="fi-wi-stats-overview-header grid gap-y-1">
            @if ($hasHeading)
                <h3 class="fi-wi-stats-overview-header-heading col-span-full text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ $heading }}
                </h3>
            @endif

            @if ($hasDescription)
                <p class="fi-wi-stats-overview-header-description overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>
    @endif

    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        @class([
            'fi-wi-stats-overview-stats-ctn grid gap-6',
            'md:grid-cols-1' => $columns === 1,
            'md:grid-cols-2' => $columns === 2,
            'md:grid-cols-3' => $columns === 3,
            'md:grid-cols-2 xl:grid-cols-4' => $columns === 4,
        ])
    >
        @foreach ($this->getCachedStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>

    <x-filament::modal
        :id="$this->getBreakdownModalId()"
        :heading="$this->getActiveBreakdownHeading()"
        width="7xl"
    >
        <div class="max-h-[70vh] overflow-auto">
            @if (count($breakdownColumns) && count($breakdownRows))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                @foreach ($breakdownColumns as $column)
                                    <th class="px-3 py-2 font-medium">
                                        {{ $column['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdownRows as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    @foreach ($breakdownColumns as $column)
                                        <td class="px-3 py-2 align-top">
                                            {{ $row[$column['key']] ?? '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    {{ $this->getActiveBreakdownEmptyMessage() }}
                </div>
            @endif
        </div>
    </x-filament::modal>
</x-filament-widgets::widget>
