<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Trip $trip): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessTripsArea()) {
            abort(403);
        }

        $disk = Storage::disk('public');

        if (! $trip->attachment_path || ! $disk->exists($trip->attachment_path)) {
            abort(404);
        }

        $extension = pathinfo($trip->attachment_path, PATHINFO_EXTENSION);
        $filename = 'viaggio-' . $trip->id . '-bolla-' . $trip->delivery_note_number . ($extension ? '.' . $extension : '');

        return $disk->download($trip->attachment_path, $filename);
    }
}
