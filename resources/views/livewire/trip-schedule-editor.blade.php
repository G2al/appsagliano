<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <x-filament::icon-button
            icon="heroicon-m-chevron-left"
            wire:click="previousMonth"
            label="Mese precedente"
        />

        <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $this->monthLabel }}
        </span>

        <x-filament::icon-button
            icon="heroicon-m-chevron-right"
            wire:click="nextMonth"
            label="Mese successivo"
        />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
        <span>{{ $totalTrips }} {{ $totalTrips === 1 ? 'viaggio' : 'viaggi' }} nel mese selezionato</span>
        <span class="font-semibold text-gray-900 dark:text-white">
            Totale mese: € {{ number_format(collect($days)->sum('total'), 2, ',', '.') }}
        </span>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Giorno</th>
                    @for ($i = 1; $i <= $maxTripsPerDay; $i++)
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" colspan="3">{{ $i }}° viaggio</th>
                    @endfor
                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Totale giorno</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($days as $row)
                    <tr class="{{ $row['trips']->isEmpty() ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-white' }}">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap align-top">
                            {{ $row['day'] }}
                            <span class="block text-xs font-normal text-gray-400 dark:text-gray-500">{{ $row['date']->translatedFormat('D') }}</span>
                        </td>

                        @for ($i = 0; $i < $maxTripsPerDay; $i++)
                            @php($trip = $row['trips']->get($i))
                            @if ($trip)
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ $trip->destinations_label ?: 'N/D' }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $trip->vehicle?->plate ?? 'N/D' }}
                                        @if ($trip->platform)
                                            &middot; {{ $trip->platform->name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top whitespace-nowrap">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Bolla</span>
                                    <div class="font-medium">{{ $trip->delivery_note_number }}</div>
                                </td>
                                <td class="px-3 py-2 align-top whitespace-nowrap">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Importo &euro;</span>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        wire:model="prices.{{ $trip->id }}"
                                        wire:change="updatePrice({{ $trip->id }})"
                                        placeholder="0,00"
                                        class="fi-input w-24 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </td>
                            @else
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                            @endif
                        @endfor

                        <td class="px-3 py-2 align-top font-semibold whitespace-nowrap">
                            @if ($row['total'] > 0)
                                € {{ number_format($row['total'], 2, ',', '.') }}
                            @else
                                <span class="text-gray-300 dark:text-gray-600">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
