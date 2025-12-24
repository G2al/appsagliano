@php
    $items = $getState() ?? collect();
    $columns = [
        'Data' => fn($item) => $item->date?->format('d/m/Y H:i') ?? 'N/D',
        'Veicolo' => fn($item) => trim(($item->vehicle?->plate ? $item->vehicle->plate . ' - ' : '') . ($item->vehicle?->name ?? 'N/D')),
        'Km manutenzione' => fn($item) => $item->km_current !== null ? number_format((float) $item->km_current, 0, ',', '.') : 'N/D',
        'Prezzo' => fn($item) => $item->price !== null ? '€ ' . number_format((float) $item->price, 2, ',', '.') : 'N/D',
        'N° bolla' => fn($item) => $item->invoice_number ?: 'N/D',
        'Note' => fn($item) => $item->notes ?: 'N/D',
        'Allegato' => fn($item) => $item->attachment_url ? '<a href="'.$item->attachment_url.'" target="_blank" style="color:#2563eb;">Vedi</a>' : 'N/D',
    ];
@endphp

<div class="space-y-4">
    @if($items->isEmpty())
        <p class="text-sm text-gray-500">Nessun ticket per questo fornitore nel periodo selezionato.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600">
                        @foreach(array_keys($columns) as $label)
                            <th class="px-2 py-1">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($items as $item)
                        <tr>
                            @foreach($columns as $getter)
                                @php $value = $getter($item); @endphp
                                <td class="px-2 py-1">{!! $value !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
