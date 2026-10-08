<x-filament::page>
    <div class="space-y-4">
        <div class="max-w-sm">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cerca autista..."
                />
            </x-filament::input.wrapper>
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
    </div>
</x-filament::page>
