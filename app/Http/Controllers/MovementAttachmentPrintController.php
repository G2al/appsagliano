<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MovementAttachmentPrintController extends Controller
{
    public function __invoke(Request $request, Movement $movement): View
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessRefuelsArea()) {
            abort(403);
        }

        $movement->load(['user', 'vehicle', 'station', 'attachments']);

        if ($movement->attachments->isEmpty()) {
            abort(404);
        }

        return view('receipts.photo', [
            'movement' => $movement,
        ]);
    }
}
