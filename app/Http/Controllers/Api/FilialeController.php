<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filiale;
use Illuminate\Http\JsonResponse;

class FilialeController extends Controller
{
    public function index(): JsonResponse
    {
        $filiali = Filiale::query()
            ->orderBy('name')
            ->get(['id', 'name', 'codice']);

        return response()->json($filiali);
    }
}
