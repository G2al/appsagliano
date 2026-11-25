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

                @if (auth()->user()?->isAdmin())
                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-archive-box-arrow-down"
                        wire:click="exportAll"
                    >
                        Esporta tutti
                    </x-filament::button>
                @endif
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($this->users as $user)
                @php
                    $latestTripAt = $user->latest_trip_at ? \Illuminate\Support\Carbon::parse($user->latest_trip_at) : null;
                    $isReviewed = $user->trips_count === 0
                        || ($user->trip_schedule_reviewed_at && (! $latestTripAt || $user->trip_schedule_reviewed_at->greaterThan($latestTripAt)));
                @endphp
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">
                        <span
                            style="display:inline-block;width:0.6rem;height:0.6rem;border-radius:9999px;vertical-align:middle;margin-right:0.5rem;background-color:{{ $isReviewed ? '#22c55e' : '#f59e0b' }};"
                            title="{{ $isReviewed ? 'Visualizzato' : 'Da visualizzare' }}"
                        ></span>
                        {{ $user->full_name }}
                    </x-slot>

                    <x-slot name="description">
                        {{ $user->phone ?: 'Telefono non indicato' }}
                    </x-slot>

                    <x-slot name="headerEnd">
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="gray">
                                {{ $user->trips_count }} {{ $user->trips_count === 1 ? 'viaggio' : 'viaggi' }}
                            </x-filament::badge>
                            @if (auth()->user()?->isAdmin() && ! $isReviewed)
                                <x-filament::button
                                    size="xs"
                                    color="warning"
                                    wire:click="markReviewed({{ $user->id }})"
                                >
                                    Segna visualizzato
                                </x-filament::button>
                            @endif
                            @if (auth()->user()?->isAdmin())
                                <x-filament::icon-button
                                    icon="heroicon-o-arrow-down-tray"
                                    label="Esporta"
                                    wire:click="exportDriver({{ $user->id }})"
                                    tooltip="Esporta Excel per mese"
                                />
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
