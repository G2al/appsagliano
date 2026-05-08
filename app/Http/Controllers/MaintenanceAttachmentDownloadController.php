<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Maintenance $maintenance): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessMaintenanceArea()) {
            abort(403);
        }

        if (! $maintenance->attachment_path || ! Storage::disk('public')->exists($maintenance->attachment_path)) {
            abort(404);
        }

        $extension = pathinfo($maintenance->attachment_path, PATHINFO_EXTENSION) ?: 'jpg';
        $invoiceNumber = $maintenance->invoice_number ?: 'manutenzione_' . $maintenance->id;
        $invoiceNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoiceNumber);
        $fileName = 'allegato_manutenzione_' . $invoiceNumber . '.' . $extension;

        return Storage::disk('public')->download($maintenance->attachment_path, $fileName);
    }
}
