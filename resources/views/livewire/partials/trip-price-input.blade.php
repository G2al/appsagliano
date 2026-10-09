@php($tripId = $trip->id)
<div
    x-data="{ saved: false }"
    x-on:price-saved.window="
        if ($event.detail.tripId === {{ $tripId }}) {
            saved = true;
            setTimeout(() => saved = false, 1200);
        }
    "
    class="flex items-center gap-1"
>
    <span class="text-xs text-gray-500 dark:text-gray-400">&euro;</span>
    <input
        type="text"
        inputmode="decimal"
        wire:model="prices.{{ $tripId }}"
        wire:change="updatePrice({{ $tripId }})"
        x-on:keydown.enter.prevent="$event.target.blur()"
        placeholder="0,00"
        style="font-size: 16px;"
        x-bind:class="saved ? 'ring-2 ring-green-500 border-green-500' : 'border-gray-300 dark:border-gray-600'"
        class="fi-input w-16 rounded-lg px-2 py-1 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:text-white transition-colors"
    />
</div>
