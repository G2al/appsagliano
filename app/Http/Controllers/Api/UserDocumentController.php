<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDocumentFile;
use App\Models\UserDocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $folders = UserDocumentFolder::query()
            ->with([
                'files' => fn ($query) => $query
                    ->select([
                        'id',
                        'user_document_folder_id',
                        'title',
                        'mime_type',
                        'file_size',
                        'opened_at',
                        'created_at',
                    ])
                    ->orderByDesc('id'),
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $folders->map(fn (UserDocumentFolder $folder) => [
                'id' => $folder->id,
                'title' => $folder->title,
                'created_at' => $folder->created_at,
                'files' => $folder->files->map(fn (UserDocumentFile $file) => [
                    'id' => $file->id,
                    'title' => $file->title,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'opened_at' => $file->opened_at,
                    'created_at' => $file->created_at,
                ]),
            ])
        );
    }

    public function open(Request $request, UserDocumentFile $file): JsonResponse
    {
        $this->authorizeFileAccess($request, $file);
        if (! $file->opened_at) {
            $this->validateAccountPassword($request);
        }

        if (! $file->file_path || ! Storage::disk('local')->exists($file->file_path)) {
            abort(404);
        }

        $file->markAsOpened($request->ip(), $request->userAgent());

        return response()->json([
            'id' => $file->id,
            'opened_at' => $file->opened_at,
        ]);
    }

    public function download(Request $request, UserDocumentFile $file): StreamedResponse
    {
        $this->authorizeFileAccess($request, $file);
        if (! $file->opened_at) {
            $this->validateAccountPassword($request);
        }

        if (! $file->file_path || ! Storage::disk('local')->exists($file->file_path)) {
            abort(404);
        }

        $file->markAsOpened($request->ip(), $request->userAgent());

        $downloadName = $file->downloadName();

        return Storage::disk('local')->response(
            $file->file_path,
            $downloadName,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream']
        );
    }

    private function validateAccountPassword(Request $request): void
    {
        $password = (string) ($request->input('password') ?? $request->header('X-Document-Password', ''));
        $user = $request->user();

        if ($password === '' || ! $user || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password account non corretta.'],
            ]);
        }
    }

    private function authorizeFileAccess(Request $request, UserDocumentFile $file): void
    {
        $file->loadMissing('folder:id,user_id');
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->role === 'admin') {
            return;
        }

        if ((int) $file->folder?->user_id !== (int) $user->id) {
            abort(403);
        }
    }
}
