<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TripAttachmentPrintController extends Controller
{
    public function __invoke(Request $request, Trip $trip): View
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessTripsArea()) {
            abort(403);
        }

        $trip->load(['user', 'vehicle', 'platform', 'attachments']);

        if ($trip->attachments->isEmpty()) {
            abort(404);
        }

        return view('receipts.trip-attachment', [
            'trip' => $trip,
        ]);
    }
}
