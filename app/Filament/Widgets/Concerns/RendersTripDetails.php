<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;

trait RendersTripDetails
{
    protected function renderTripDetails(Builder $query, string $title): string
    {
        $rows = $query
            ->with(['user', 'vehicle', 'platform', 'attachments'])
            ->orderByDesc('date')
            ->limit(100)
            ->get()
            ->map(function (Trip $trip): string {
                $date = e($trip->date?->format('d/m/Y H:i') ?? 'N/D');
                $driver = e($trip->user?->full_name ?? 'N/D');
                $vehicle = e($trip->vehicle?->plate ?? $trip->vehicle?->name ?? 'N/D');
                $platform = e($trip->platform?->name ?? 'N/D');
                $destinations = e($trip->destinations_label ?: 'N/D');
                $note = e($trip->delivery_note_number);
                $price = $trip->price !== null
                    ? '€ ' . number_format((float) $trip->price, 2, ',', '.')
                    : 'Da certificare';
                $attachment = $trip->attachments->isNotEmpty()
                    ? '<a href="' . e(route('trips.attachment', $trip)) . '" target="_blank" class="text-primary-500">Apri (' . $trip->attachments->count() . ')</a>'
                    : 'N/D';

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$driver}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$vehicle}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$platform}</td>
                    <td class=\"px-2 py-1 text-sm\">{$destinations}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$note}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="px-2 py-3 text-sm text-center text-gray-500">Nessun viaggio</td></tr>';
        }

        $title = e($title);

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">{$title}</div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Autista</th>
                                <th class="px-2 py-2 font-medium">Veicolo</th>
                                <th class="px-2 py-2 font-medium">Piattaforma</th>
                                <th class="px-2 py-2 font-medium">Destinazioni</th>
                                <th class="px-2 py-2 font-medium">Bolla</th>
                                <th class="px-2 py-2 font-medium">Prezzo</th>
                                <th class="px-2 py-2 font-medium">Allegato</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows}
                        </tbody>
                    </table>
                </div>
            </div>
        HTML;
    }
}
