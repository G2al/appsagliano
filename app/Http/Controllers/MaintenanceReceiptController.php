<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MaintenanceReceiptController extends Controller
{
    public function __invoke(Request $request, Maintenance $maintenance): View
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403);
        }

        $maintenance->load(['user', 'vehicle', 'supplier']);

        return view('receipts.maintenance', [
            'maintenance' => $maintenance,
        ]);
    }
}
