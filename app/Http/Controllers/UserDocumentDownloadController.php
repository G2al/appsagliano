<?php

namespace App\Http\Controllers;

use App\Models\UserDocumentFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, UserDocumentFile $file): StreamedResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403);
        }

        if (! $file->file_path || ! Storage::disk('local')->exists($file->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->file_path, $file->downloadName());
    }
}
