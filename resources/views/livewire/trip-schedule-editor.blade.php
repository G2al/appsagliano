<div class="w-full min-w-0 space-y-4">
    <div class="flex items-center gap-2">
        <label for="trip-schedule-month-{{ $userId }}" class="text-sm font-medium text-gray-700 dark:text-gray-200">Mese</label>
        <input
            type="month"
            id="trip-schedule-month-{{ $userId }}"
            wire:model.live="month"
            style="font-size: 16px;"
            class="fi-input block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
        <span>{{ $totalTrips }} {{ $totalTrips === 1 ? 'viaggio' : 'viaggi' }} nel mese selezionato</span>
        <span class="font-semibold text-gray-900 dark:text-white">
            Totale mese: € {{ number_format(collect($days)->sum('total'), 2, ',', '.') }}
        </span>
    </div>

    <div
        class="w-full min-w-0 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 cursor-grab active:cursor-grabbing"
        style="contain: inline-size; user-select: none;"
        x-data="{
            isDragging: false,
            startX: 0,
            startScrollLeft: 0,
        }"
        x-on:mousedown="
            if ($event.target.tagName === 'INPUT') return;
            isDragging = true;
            startX = $event.pageX;
            startScrollLeft = $el.scrollLeft;
            $event.preventDefault();
        "
        x-on:mousemove.window="
            if (! isDragging) return;
            $event.preventDefault();
            $el.scrollLeft = startScrollLeft - ($event.pageX - startX);
        "
        x-on:mouseup.window="isDragging = false"
    >
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5);">Giorno</th>
                    @for ($i = 1; $i <= $maxTripsPerDay; $i++)
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5);" colspan="3">{{ $i }}° viaggio</th>
                    @endfor
                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Totale giorno</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($days as $row)
                    <tr class="{{ $row['trips']->isEmpty() ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-white' }}">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap align-top" style="border-right: 2px solid rgba(100,116,139,0.5);">
                            {{ $row['day'] }}
                            <span class="block text-xs font-normal text-gray-400 dark:text-gray-500">{{ $row['date']->translatedFormat('D') }}</span>
                        </td>

                        @for ($i = 0; $i < $maxTripsPerDay; $i++)
                            @php($trip = $row['trips']->get($i))
                            @if ($trip)
                                <td class="px-3 py-2 align-top whitespace-nowrap">
                                    <div class="font-medium whitespace-nowrap">{{ $trip->destinations_label ?: 'N/D' }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                        {{ $trip->vehicle?->plate ?? 'N/D' }}
                                        @if ($trip->platform)
                                            &middot; {{ $trip->platform->name }}
                                        @endif
                                        &middot; {{ $trip->goods_type_label }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top whitespace-nowrap">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Bolla</span>
                                    <div class="font-medium">{{ $trip->delivery_note_number }}</div>
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5);">
                                    @include('livewire.partials.trip-price-input', ['trip' => $trip])
                                </td>
                            @else
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2" style="border-right: 2px solid rgba(100,116,139,0.5);"></td>
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
