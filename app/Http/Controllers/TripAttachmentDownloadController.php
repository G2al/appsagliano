<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Trip $trip, TripAttachment $attachment): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessTripsArea()) {
            abort(403);
        }

        if ($attachment->trip_id !== $trip->id) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        $extension = pathinfo($attachment->path, PATHINFO_EXTENSION);
        $filename = 'viaggio-' . $trip->id . '-bolla-' . $trip->delivery_note_number . '-' . $attachment->id . ($extension ? '.' . $extension : '');

        return $disk->download($attachment->path, $filename);
    }
}
