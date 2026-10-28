<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    public function index(): JsonResponse
    {
        $platforms = Platform::query()
            ->with('addresses:id,platform_id,address')
            ->orderBy('name')
            ->get(['id', 'name', 'company'])
            ->map(fn (Platform $platform) => [
                'id' => $platform->id,
                'name' => $platform->name,
                'company' => $platform->company,
                'addresses' => $platform->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'address' => $a->address,
                ])->values(),
            ]);

        return response()->json($platforms);
    }
}
