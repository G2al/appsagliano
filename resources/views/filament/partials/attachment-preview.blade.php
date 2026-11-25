@php
    $isPdf = fn (string $url) => str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf');
@endphp

<div class="space-y-4">
    @foreach ($items as $item)
        <div class="space-y-2">
            @if ($isPdf($item['url']))
                <iframe src="{{ $item['url'] }}" class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 70vh;"></iframe>
            @else
                <div x-data="{ zoomed: false }" :style="zoomed ? 'overflow:auto;max-height:80vh;' : ''">
                    <img
                        src="{{ $item['url'] }}"
                        alt="{{ $item['label'] }}"
                        @click="zoomed = ! zoomed"
                        class="rounded-lg border border-gray-200 dark:border-gray-700"
                        :class="zoomed ? '' : 'w-full object-contain'"
                        :style="zoomed ? 'max-width:none;max-height:none;cursor:zoom-out;' : 'max-height:70vh;cursor:zoom-in;width:100%;'"
                    />
                </div>
            @endif

            <a
                href="{{ $item['url'] }}"
                download
                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline"
            >
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                Scarica {{ $item['label'] }}
            </a>
        </div>
    @endforeach
</div>
