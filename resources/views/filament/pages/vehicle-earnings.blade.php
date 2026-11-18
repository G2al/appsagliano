<x-filament::page>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="max-w-sm flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cerca veicolo (targa, nome, società)..."
                    />
                </x-filament::input.wrapper>
            </div>

            <div class="flex items-center gap-2">
                <label for="vehicle-earnings-per-page" class="text-sm text-gray-500 dark:text-gray-400">Per pagina</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="vehicle-earnings-per-page" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($this->vehicles as $vehicle)
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">
                        {{ trim(($vehicle->plate ? $vehicle->plate . ' - ' : '') . ($vehicle->name ?? '')) }}
                    </x-slot>

                    <x-slot name="description">
                        {{ $vehicle->company ?: 'Società non indicata' }}
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::badge color="gray">
                            {{ $vehicle->trips_count }} {{ $vehicle->trips_count === 1 ? 'viaggio' : 'viaggi' }}
                        </x-filament::badge>
                    </x-slot>

                    @livewire('vehicle-earnings-editor', ['vehicleId' => $vehicle->id], key('vehicle-earnings-editor-' . $vehicle->id))
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        Nessun veicolo trovato.
                    </p>
                </x-filament::section>
            @endforelse
        </div>

        {{ $this->vehicles->links() }}
    </div>
</x-filament::page>
