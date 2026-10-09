<x-filament::page>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="max-w-sm flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cerca autista..."
                    />
                </x-filament::input.wrapper>
            </div>

            <div class="flex items-center gap-2">
                <label for="trip-schedules-per-page" class="text-sm text-gray-500 dark:text-gray-400">Per pagina</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="trip-schedules-per-page" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($this->users as $user)
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">
                        {{ $user->full_name }}
                    </x-slot>

                    <x-slot name="description">
                        {{ $user->phone ?: 'Telefono non indicato' }}
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::badge color="gray">
                            {{ $user->trips_count }} {{ $user->trips_count === 1 ? 'viaggio' : 'viaggi' }}
                        </x-filament::badge>
                    </x-slot>

                    @livewire('trip-schedule-editor', ['userId' => $user->id], key('trip-schedule-editor-' . $user->id))
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        Nessun autista trovato.
                    </p>
                </x-filament::section>
            @endforelse
        </div>

        {{ $this->users->links() }}
    </div>
</x-filament::page>
