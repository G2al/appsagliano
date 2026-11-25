@php
    $isPdf = fn (string $url) => str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf');
@endphp

<div class="space-y-4">
    @foreach ($items as $item)
        <div class="space-y-2">
            @if ($isPdf($item['url']))
                <iframe src="{{ $item['url'] }}" class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 70vh;"></iframe>
            @else
                <img src="{{ $item['url'] }}" alt="{{ $item['label'] }}" class="w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" style="max-height: 70vh;" />
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
