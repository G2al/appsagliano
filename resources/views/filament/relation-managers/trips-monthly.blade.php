<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label for="trips-month" class="text-sm font-medium text-gray-700 dark:text-gray-200">Mese</label>
            <input
                type="month"
                id="trips-month"
                wire:model.live="month"
                class="fi-input block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ $totalTrips }} {{ $totalTrips === 1 ? 'viaggio' : 'viaggi' }} nel mese selezionato
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium whitespace-nowrap">Giorno</th>
                    @for ($i = 1; $i <= $maxTripsPerDay; $i++)
                        <th class="px-3 py-2 font-medium whitespace-nowrap" colspan="2">{{ $i }}° viaggio</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $row)
                    <tr class="border-t border-gray-100 dark:border-gray-800 {{ $row['trips']->isEmpty() ? 'opacity-50' : '' }}">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap align-top">
                            {{ $row['day'] }}
                            <span class="block text-xs font-normal text-gray-400">{{ $row['date']->translatedFormat('D') }}</span>
                        </td>

                        @for ($i = 0; $i < $maxTripsPerDay; $i++)
                            @php($trip = $row['trips']->get($i))
                            @if ($trip)
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ $trip->destinations_label ?: 'N/D' }}</div>
                                    <div class="text-xs text-gray-400">
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
                            @else
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                            @endif
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
