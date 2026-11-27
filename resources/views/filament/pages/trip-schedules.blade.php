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
                        @if (auth()->user()?->isAdmin() && $user->assignedManagers->isNotEmpty())
                            &middot; Gestito da: {{ $user->assignedManagers->pluck('full_name')->implode(', ') }}
                        @endif
                    </x-slot>

                    <x-slot name="headerEnd">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge color="gray">
                                {{ $user->trips_count }} {{ $user->trips_count === 1 ? 'viaggio' : 'viaggi' }}
                            </x-filament::badge>
                            <x-filament::badge color="success">
                                {{ $user->reviewed_trips_count }} revisionati
                            </x-filament::badge>
                            <x-filament::badge color="warning">
                                {{ $user->unreviewed_trips_count }} da revisionare
                            </x-filament::badge>
                            @if (auth()->user()?->canManageBorderoPrice())
                                <x-filament::badge color="primary">
                                    € {{ number_format((float) ($user->certified_trips_total ?? 0), 2, ',', '.') }}
                                </x-filament::badge>
                            @endif
                        </div>
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
