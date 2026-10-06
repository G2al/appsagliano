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
            Station::select('id', 'name', 'address', 'credit_balance', 'uses_vouchers', 'uses_credit_cards')
                ->with(['cards' => fn ($query) => $query->select('id', 'station_id', 'number', 'label')->orderBy('number')])
                ->orderBy('name')
                ->get()
        );
    }
}
