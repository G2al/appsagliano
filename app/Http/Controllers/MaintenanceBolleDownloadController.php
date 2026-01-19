<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class MaintenanceBolleDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $token = $request->query('token');

        if (!$token) {
            abort(400, 'Token mancante');
        }

        $ids = json_decode(base64_decode($token), true);

        if (!is_array($ids) || empty($ids)) {
            abort(400, 'Token non valido');
        }

        $records = Maintenance::whereIn('id', $ids)->get();

        if ($records->isEmpty()) {
            abort(404, 'Nessun record trovato');
        }

        $zipFileName = 'bolle_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $tempDir = storage_path('app/temp');
        $tempPath = $tempDir . '/' . $zipFileName;

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossibile creare il file ZIP');
        }

        $addedFiles = 0;

        foreach ($records as $record) {
            if (!$record->attachment_path) {
                continue;
            }

            $filePath = Storage::disk('public')->path($record->attachment_path);

            if (!file_exists($filePath)) {
                continue;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $invoiceNumber = $record->invoice_number ?: 'senza_bolla_' . $record->id;
            $invoiceNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoiceNumber);
            $fileName = 'bolla_' . $invoiceNumber . '.' . $extension;

            $zip->addFile($filePath, $fileName);
            $addedFiles++;
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($tempPath);
            abort(404, 'Nessun allegato trovato per i record selezionati');
        }

        return response()->download($tempPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
