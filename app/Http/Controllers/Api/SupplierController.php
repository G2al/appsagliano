<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $suppliers = Supplier::select('id', 'name', 'phone', 'email', 'supplier_type_id')
            ->with('type:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'type' => $supplier->type ? [
                    'id' => $supplier->type->id,
                    'name' => $supplier->type->name,
                ] : null,
            ]);

        return response()->json($suppliers);
    }
}
