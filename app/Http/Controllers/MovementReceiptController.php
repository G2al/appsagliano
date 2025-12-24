<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MovementReceiptController extends Controller
{
    public function __invoke(Request $request, Movement $movement): View
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403);
        }

        $movement->load(['user', 'station', 'vehicle', 'updatedBy']);

        return view('receipts.movement', [
            'movement' => $movement,
        ]);
    }
}
