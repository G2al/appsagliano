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
        <span>{{ $totalTrips }} {{ $totalTrips === 1 ? 'record' : 'record' }} nel mese selezionato</span>
        @if (auth()->user()?->canManageBorderoPrice())
            <span class="font-semibold text-gray-900 dark:text-white">
                Totale mese: € {{ number_format(collect($days)->sum('total'), 2, ',', '.') }}
            </span>
        @endif
    </div>

    @if (auth()->user()?->canManageBorderoPrice() && $filialeTotals->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($filialeTotals as $row)
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs dark:border-gray-700 dark:bg-gray-800">
                    <span class="text-gray-500 dark:text-gray-400">{{ $row['filiale'] }}</span>
                    <span class="font-semibold text-gray-900 dark:text-white">€ {{ number_format($row['total'], 2, ',', '.') }}</span>
                </span>
            @endforeach
        </div>
    @endif

    @if (auth()->user()?->canManageBorderoPrice())
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Stipendio base del mese</x-slot>
                <div class="flex items-center gap-2">
                    <x-filament::input.wrapper class="max-w-[160px]">
                        <x-filament::input type="text" wire:model="salaryInput" placeholder="0.00" />
                    </x-filament::input.wrapper>
                    <x-filament::button wire:click="saveSalary" size="sm">Salva</x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Acconti / TFR</x-slot>
                <div class="space-y-2">
                    @forelse ($adjustments as $adjustment)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ $adjustment->label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-semibold {{ $adjustment->amount < 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ $adjustment->amount > 0 ? '+' : '' }}€ {{ number_format((float) $adjustment->amount, 2, ',', '.') }}
                                </span>
                                <button type="button" wire:click="deleteAdjustment({{ $adjustment->id }})" class="text-xs text-gray-400 hover:text-danger-600">✕</button>
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-500">Nessuna voce per questo mese.</p>
                    @endforelse

                    <div class="flex items-center gap-2 pt-2">
                        <x-filament::input.wrapper class="flex-1">
                            <x-filament::input type="text" wire:model="newAdjustmentLabel" placeholder="Es. Acconto, TFR" />
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper class="max-w-[120px]">
                            <x-filament::input type="text" wire:model="newAdjustmentAmount" placeholder="+/- importo" />
                        </x-filament::input.wrapper>
                        <x-filament::button wire:click="addAdjustment" size="sm">Aggiungi</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div class="text-right text-sm font-semibold text-gray-900 dark:text-white">
            Totale da pagare (trasferte + stipendio + acconti/TFR): € {{ number_format($payoutTotal, 2, ',', '.') }}
        </div>
    @endif

    @if (auth()->user()?->canReviewBordero())
        <div class="flex items-center justify-end">
            <x-filament::button wire:click="exportSelected" size="sm" color="gray" icon="heroicon-o-arrow-down-tray">
                Esporta selezionati in Excel
            </x-filament::button>
        </div>
    @endif

    <div
        class="w-full min-w-0 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700"
        style="contain: inline-size;"
        x-data
        x-on:wheel="
            if (Math.abs($event.deltaY) <= Math.abs($event.deltaX)) return;
            if ($el.scrollWidth <= $el.clientWidth) return;
            $el.scrollLeft += $event.deltaY;
            $event.preventDefault();
        "
    >
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5);">Giorno</th>
                    @for ($i = 1; $i <= $maxTripsPerDay; $i++)
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5); min-width: 260px;" colspan="2">{{ $i }}° record</th>
                    @endfor
                    @if (auth()->user()?->canManageBorderoPrice())
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Totale giorno</th>
                    @endif
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
                                @php($isLockedForMe = ! auth()->user()?->isAdmin() && $trip->isExportLockedFor(auth()->user()))
                                @php($isSelected = $selectedTrips[$trip->id] ?? false)
                                @php($canSelect = auth()->user()?->canReviewBordero() && ! $isLockedForMe)
                                <td
                                    class="px-3 py-2 align-top whitespace-nowrap {{ $isSelected ? 'bg-primary-50 dark:bg-white/10' : '' }}"
                                    style="{{ $canSelect ? 'cursor:pointer;' : '' }}"
                                    @if ($canSelect) wire:click="toggleTripSelection({{ $trip->id }})" title="Clicca per selezionare/deselezionare per l'esportazione" @endif
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">N. bordero {{ $trip->delivery_note_number }}</span>
                                        @if ($isSelected)
                                            <span class="text-xs font-semibold text-primary-600 dark:text-white">✓ selezionato</span>
                                        @endif
                                        @if ($isLockedForMe)
                                            <span class="text-xs text-gray-400" title="Esportato il {{ $trip->export_locked_at?->format('d/m/Y H:i') }}">🔒</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm">
                                        <span class="font-medium">{{ $trip->filiale ? "{$trip->filiale->codice} - {$trip->filiale->name}" : 'N/D' }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $trip->vehicle?->plate ?? 'N/D' }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $trip->filiale?->platform?->name ?? 'N/D' }} &middot; {{ $trip->goods_type_label }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">scarichi {{ $trip->scarichi }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap" style="border-right: 2px solid rgba(100,116,139,0.5);">
                                    <div class="flex items-center gap-1">
                                        <span
                                            style="display:inline-block;width:0.6rem;height:0.6rem;border-radius:9999px;vertical-align:middle;background-color:{{ $trip->is_reviewed ? '#22c55e' : '#f59e0b' }};"
                                            title="{{ $trip->is_reviewed ? 'Accettato' : 'Da revisionare' }}"
                                        ></span>
                                    </div>
                                    @if (auth()->user()?->canManageBorderoPrice())
                                        @include('livewire.partials.trip-price-input', ['trip' => $trip])
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-600">
                                            {{ $trip->is_reviewed ? 'Accettato' : 'Da revisionare' }}
                                        </span>
                                    @endif
                                    @if ($trip->is_reviewed && $trip->reviewer)
                                        <div class="text-xs text-gray-400 dark:text-gray-500">da {{ $trip->reviewer->full_name }}</div>
                                    @endif
                                    @if (auth()->user()?->canReviewBordero() && ! $isLockedForMe)
                                        <div class="mt-1 flex items-center gap-2">
                                            <a href="{{ $this->editUrl($trip->id) }}" target="_blank" class="text-xs text-primary-600 hover:underline">Modifica</a>
                                            @unless ($trip->is_reviewed)
                                                <button
                                                    type="button"
                                                    wire:click="openAcceptForm({{ $trip->id }})"
                                                    class="text-xs text-success-600 hover:underline"
                                                >Accetta</button>
                                            @endunless
                                        </div>
                                        @if ($acceptingTripId === $trip->id)
                                            <div class="mt-1 space-y-1 rounded border border-gray-200 p-1.5 dark:border-gray-700">
                                                <input type="file" wire:model="pendingScans.{{ $trip->id }}" class="text-xs" />
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="acceptTrip({{ $trip->id }})"
                                                        wire:confirm="Confermi l'accettazione di questo bordero?"
                                                        class="text-xs font-medium text-success-600 hover:underline"
                                                    >Conferma</button>
                                                    <button type="button" wire:click="cancelAcceptForm" class="text-xs text-gray-400 hover:underline">Annulla</button>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            @else
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2" style="border-right: 2px solid rgba(100,116,139,0.5);"></td>
                            @endif
                        @endfor

                        @if (auth()->user()?->canManageBorderoPrice())
                            <td class="px-3 py-2 align-top font-semibold whitespace-nowrap">
                                @if ($row['total'] > 0)
                                    € {{ number_format($row['total'], 2, ',', '.') }}
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">-</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
