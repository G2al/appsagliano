<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait RendersTripDetails
{
    /** @var array<string, int> */
    public array $detailsPage = [];

    private const DETAILS_PER_PAGE = 25;

    public function goToDetailsPage(string $rowKey, int $page): void
    {
        $this->detailsPage[$rowKey] = max(1, $page);
    }

    protected function renderTripDetails(Builder $query, string $title, string $rowKey): string
    {
        $canSeePrice = (bool) Auth::user()?->canManageBorderoPrice();
        $page = $this->detailsPage[$rowKey] ?? 1;

        $paginator = $query
            ->with(['user', 'vehicle', 'filiale', 'attachments'])
            ->orderByDesc('date')
            ->paginate(self::DETAILS_PER_PAGE, ['*'], 'page', $page);

        $priceHeader = $canSeePrice ? '<th class="px-2 py-2 font-medium">Prezzo</th>' : '';

        $rows = collect($paginator->items())
            ->map(function (Trip $trip) use ($canSeePrice): string {
                $date = e($trip->date?->format('d/m/Y H:i') ?? 'N/D');
                $driver = e($trip->user?->full_name ?? 'N/D');
                $vehicle = e($trip->vehicle?->plate ?? $trip->vehicle?->name ?? 'N/D');
                $filiale = e($trip->filiale ? "{$trip->filiale->codice} - {$trip->filiale->name}" : 'N/D');
                $note = e($trip->delivery_note_number);
                $scarichi = e((string) $trip->scarichi);
                $attachment = $trip->attachments->isNotEmpty()
                    ? '<a href="' . e(route('trips.attachment', $trip)) . '" target="_blank" class="text-primary-500">Apri (' . $trip->attachments->count() . ')</a>'
                    : 'N/D';

                $priceCell = '';
                if ($canSeePrice) {
                    $price = $trip->price !== null
                        ? '€ ' . number_format((float) $trip->price, 2, ',', '.')
                        : 'Da certificare';
                    $priceCell = "<td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>";
                }

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$driver}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$vehicle}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$filiale}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$note}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$scarichi}</td>
                    {$priceCell}
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })
            ->implode('');

        $colspan = $canSeePrice ? 8 : 7;

        if ($rows === '') {
            $rows = "<tr><td colspan=\"{$colspan}\" class=\"px-2 py-3 text-sm text-center text-gray-500\">Nessun record</td></tr>";
        }

        $title = e($title);
        $rowKeyJs = e($rowKey);

        $pagination = '';
        if ($paginator->lastPage() > 1) {
            $currentPage = $paginator->currentPage();
            $prevAttr = $currentPage <= 1
                ? 'disabled style="opacity:0.4;cursor:not-allowed;"'
                : 'wire:click="goToDetailsPage(\'' . $rowKeyJs . '\', ' . ($currentPage - 1) . ')"';
            $nextAttr = $currentPage >= $paginator->lastPage()
                ? 'disabled style="opacity:0.4;cursor:not-allowed;"'
                : 'wire:click="goToDetailsPage(\'' . $rowKeyJs . '\', ' . ($currentPage + 1) . ')"';

            $pagination = <<<HTML
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Pagina {$currentPage} di {$paginator->lastPage()} ({$paginator->total()} record)</span>
                    <div class="flex gap-2">
                        <button type="button" {$prevAttr} class="px-2 py-1 rounded border border-gray-200 dark:border-gray-700">&laquo; Precedente</button>
                        <button type="button" {$nextAttr} class="px-2 py-1 rounded border border-gray-200 dark:border-gray-700">Successiva &raquo;</button>
                    </div>
                </div>
            HTML;
        }

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
                                <th class="px-2 py-2 font-medium">Filiale</th>
                                <th class="px-2 py-2 font-medium">Numero bordero</th>
                                <th class="px-2 py-2 font-medium">Scarichi</th>
                                {$priceHeader}
                                <th class="px-2 py-2 font-medium">Allegato</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows}
                        </tbody>
                    </table>
                </div>
                {$pagination}
            </div>
        HTML;
    }
}
