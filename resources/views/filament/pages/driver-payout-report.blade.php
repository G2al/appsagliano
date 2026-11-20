<x-filament::page>
    <div class="space-y-4">
        <div class="max-w-xs">
            <x-filament::input.wrapper>
                <input type="month" wire:model.live="month" class="fi-input block w-full border-none bg-transparent p-0 text-sm text-gray-950 outline-none dark:text-white" />
            </x-filament::input.wrapper>
        </div>

        @php($rows = $this->rows)
        @php($grouped = collect($rows)->groupBy(fn ($row) => $row['company'] ?: 'Società non indicata'))

        <div class="space-y-3">
            @forelse ($grouped as $company => $companyRows)
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $company }}
                    </x-slot>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-1">Autista</th>
                                <th class="py-1">IBAN</th>
                                <th class="py-1 text-right">Trasferte (€)</th>
                                <th class="py-1 text-right">Stipendio (€)</th>
                                <th class="py-1 text-right">Acconti/TFR (€)</th>
                                <th class="py-1 text-right">Totale (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($companyRows as $row)
                                <tr>
                                    <td class="py-1">{{ $row['name'] }}</td>
                                    <td class="py-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['iban'] ?? 'non impostato' }}</td>
                                    <td class="py-1 text-right">{{ number_format($row['trips_total'], 2, ',', '.') }}</td>
                                    <td class="py-1 text-right">{{ number_format($row['salary'], 2, ',', '.') }}</td>
                                    <td class="py-1 text-right">{{ number_format($row['adjustments_total'], 2, ',', '.') }}</td>
                                    <td class="py-1 text-right font-medium">{{ number_format($row['total'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold">
                                <td class="py-1" colspan="5">Totale società</td>
                                <td class="py-1 text-right">{{ number_format($companyRows->sum('total'), 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        Nessun importo da pagare per questo mese.
                    </p>
                </x-filament::section>
            @endforelse
        </div>
    </div>
</x-filament::page>
