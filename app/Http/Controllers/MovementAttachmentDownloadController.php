<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovementAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Movement $movement): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessRefuelsArea()) {
            abort(403);
        }

        if (! $movement->photo_path || ! Storage::disk('public')->exists($movement->photo_path)) {
            abort(404);
        }

        $extension = pathinfo($movement->photo_path, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = 'ricevuta_rifornimento_' . $movement->id . '.' . $extension;

        return Storage::disk('public')->download($movement->photo_path, $fileName);
    }
}
