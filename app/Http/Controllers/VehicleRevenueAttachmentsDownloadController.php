<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
            ->orderBy('vehicle_id')
            ->orderBy('date')
            ->orderBy('id');

        if ($month !== null) {
            $monthDate = Carbon::createFromFormat('Y-m', $month);

            $revenuesQuery
                ->whereYear('date', $monthDate->year)
                ->whereMonth('date', $monthDate->month);
        }

        $revenues = $revenuesQuery->get();

        if ($revenues->isEmpty()) {
            abort(404, 'Nessun allegato trovato per i criteri selezionati');
        }

        $rootFolder = $month !== null
            ? 'entrate-veicoli-' . $this->formatMonthFolder($month)
            : 'entrate-veicoli';

        $zipFileName = $rootFolder . '_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $tempDir = storage_path('app/temp');
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossibile creare il file ZIP');
        }

        $addedFiles = 0;

        foreach ($revenues as $revenue) {
            $path = (string) $revenue->attachment_path;

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            $vehicle = $vehicles->get($revenue->vehicle_id);
            $vehicleFolder = $this->vehicleFolderName($vehicle?->plate, $vehicle?->id);
            $monthFolder = $this->formatMonthFolder($revenue->date?->format('Y-m'));
            $relativePath = $month !== null
                ? $rootFolder . '/' . $vehicleFolder . '/' . $this->buildFileName($revenue)
                : $rootFolder . '/' . $vehicleFolder . '/' . $monthFolder . '/' . $this->buildFileName($revenue);

            $zip->addFile(Storage::disk('public')->path($path), $relativePath);
            $addedFiles++;
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($tempPath);
            abort(404, 'Nessun allegato disponibile nei file selezionati');
        }

        return response()->download($tempPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function vehicleFolderName(?string $plate, ?int $vehicleId): string
    {
        $base = filled($plate) ? $plate : 'veicolo-' . (int) $vehicleId;

        return Str::of($base)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->value();
    }

    private function formatMonthFolder(?string $yearMonth): string
    {
        if (! $yearMonth) {
            return 'senza-mese';
        }

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

    private function buildFileName(VehicleRevenue $revenue): string
    {
        $extension = pathinfo((string) $revenue->attachment_path, PATHINFO_EXTENSION);
        $date = $revenue->date?->format('Y-m-d') ?? 'senza-data';
        $name = Str::of((string) ($revenue->name ?: 'entrata'))
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower()
            ->value();

        $suffix = $extension !== '' ? '.' . strtolower($extension) : '';

        return $date . '_' . $name . '_' . $revenue->getKey() . $suffix;
    }
}
