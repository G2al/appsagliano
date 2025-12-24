<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MovementAttachmentPrintController extends Controller
{
    public function __invoke(Request $request, Movement $movement): View
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403);
        }

        if (! $movement->photo_url) {
            abort(404);
        }

        $movement->load(['user', 'vehicle', 'station']);

        return view('receipts.photo', [
            'movement' => $movement,
        ]);
    }
}
