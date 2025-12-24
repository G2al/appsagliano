<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MaintenanceAttachmentPrintController extends Controller
{
    public function __invoke(Request $request, Maintenance $maintenance): View
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403);
        }

        if (! $maintenance->attachment_url) {
            abort(404);
        }

        $maintenance->load(['user', 'vehicle', 'supplier']);

        return view('receipts.maintenance-attachment', [
            'maintenance' => $maintenance,
        ]);
    }
}
