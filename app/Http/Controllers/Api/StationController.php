<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Station::select('id', 'name', 'address', 'credit_balance')->orderBy('name')->get()
        );
    }
}
