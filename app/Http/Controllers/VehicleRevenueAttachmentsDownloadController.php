<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleRevenueAttachmentsDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }

        $token = $request->query('token');

        if (! $token) {
            abort(400, 'Token mancante');
        }

        $payload = json_decode(base64_decode($token), true);

        if (! is_array($payload)) {
            abort(400, 'Token non valido');
        }

        $vehicleIds = collect($payload['vehicle_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            abort(400, 'Nessun veicolo selezionato');
        }

        $month = $payload['month'] ?? null;

        if ($month !== null && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(400, 'Mese non valido');
        }

        $vehicles = Vehicle::query()
            ->whereKey($vehicleIds)
            ->get()
            ->keyBy('id');

        if ($vehicles->isEmpty()) {
            abort(404, 'Nessun veicolo trovato');
        }

        $revenuesQuery = VehicleRevenue::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('attachment_path')
            ->with('vehicle');

        if ($month !== null) {
            $monthDate = Carbon::createFromFormat('Y-m', $month);

            $revenuesQuery
                ->whereYear('date', $monthDate->year)
                ->whereMonth('date', $monthDate->month);
        }

        $revenues = $revenuesQuery
            ->get()
            ->sortBy(fn (VehicleRevenue $revenue): string => sprintf(
                '%s|%s|%010d',
                Str::upper((string) ($revenue->vehicle?->plate ?? '')),
                $revenue->date?->format('Y-m-d') ?? '',
                (int) $revenue->getKey()
            ))
            ->values();

        if ($revenues->isEmpty()) {
            abort(404, 'Nessun allegato trovato per i criteri selezionati');
        }

        $pdfFileName = $month !== null
            ? 'entrate-veicoli-' . $this->formatMonthSlug($month) . '_' . now()->format('Y-m-d_H-i-s') . '.pdf'
            : 'entrate-veicoli_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        $tempDir = storage_path('app/temp');
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $pdfFileName;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCompression(false);

        $temporaryFiles = [];
        $addedPages = 0;

        try {
            foreach ($revenues as $revenue) {
                $path = (string) $revenue->attachment_path;

                if (! Storage::disk('public')->exists($path)) {
                    continue;
                }

                $absolutePath = Storage::disk('public')->path($path);
                $mimeType = Storage::disk('public')->mimeType($path) ?: '';
                $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

                if ($mimeType === 'application/pdf' || $extension === 'pdf') {
                    $addedPages += $this->appendPdf($pdf, $absolutePath);

                    continue;
                }

                if (str_starts_with($mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $imagePath = $this->prepareImageForPdf($absolutePath, $extension, $temporaryFiles);

                    if ($imagePath !== null) {
                        $this->appendImage($pdf, $imagePath);
                        $addedPages++;
                    }
                }
            }

            if ($addedPages === 0) {
                @unlink($tempPath);
                abort(422, 'Nessun allegato compatibile trovato. Sono supportati PDF, JPG, PNG, GIF e WEBP.');
            }

            $pdf->Output('F', $tempPath);
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                @unlink($temporaryFile);
            }
        }

        return response()->download($tempPath, $pdfFileName, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    private function formatMonthSlug(string $yearMonth): string
    {
        return Str::of(
            Carbon::createFromFormat('Y-m', $yearMonth)
                ->locale('it')
                ->translatedFormat('F Y')
        )
            ->ascii()
            ->lower()
            ->replace(' ', '-')
            ->value();
    }

    private function appendPdf(Fpdi $pdf, string $path): int
    {
        $pageCount = $pdf->setSourceFile($path);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        return $pageCount;
    }

    private function appendImage(Fpdi $pdf, string $path): void
    {
        [$widthPx, $heightPx] = getimagesize($path);

        $orientation = $widthPx > $heightPx ? 'L' : 'P';
        $pdf->AddPage($orientation);

        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();
        $margin = 10.0;
        $availableWidth = $pageWidth - ($margin * 2);
        $availableHeight = $pageHeight - ($margin * 2);
        $scale = min($availableWidth / $widthPx, $availableHeight / $heightPx);
        $renderWidth = $widthPx * $scale;
        $renderHeight = $heightPx * $scale;
        $x = ($pageWidth - $renderWidth) / 2;
        $y = ($pageHeight - $renderHeight) / 2;

        $pdf->Image($path, $x, $y, $renderWidth, $renderHeight);
    }

    private function prepareImageForPdf(string $path, string $extension, array &$temporaryFiles): ?string
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return $path;
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return null;
        }

        $tempPath = storage_path('app/temp/' . Str::uuid() . '.png');

        imagepng($image, $tempPath);
        imagedestroy($image);

        $temporaryFiles[] = $tempPath;

        return $tempPath;
    }
}
