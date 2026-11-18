<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    /**
     * Struttura annidata piattaforma -> dicitura -> filiali, per permettere
     * al frontend di far scegliere in cascata: piattaforma, poi dicitura
     * (solo quelle di quella piattaforma), poi filiale (solo quelle di
     * piattaforma+dicitura scelte). Usata per i Rifornimenti (solo
     * piattaforma/indirizzi) e per il Bordero (tutta la cascata).
     */
    public function index(): JsonResponse
    {
        $platforms = Platform::query()
            ->with([
                'addresses:id,platform_id,address',
                'goodsTypes:id,platform_id,name',
                'goodsTypes.filiali:id,goods_type_id,name,codice',
                'extras:id,platform_id,name,amount',
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Platform $platform) => [
                'id' => $platform->id,
                'name' => $platform->name,
                'addresses' => $platform->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'address' => $a->address,
                ])->values(),
                'goods_types' => $platform->goodsTypes->map(fn ($goodsType) => [
                    'id' => $goodsType->id,
                    'name' => $goodsType->name,
                    'filiali' => $goodsType->filiali->map(fn ($filiale) => [
                        'id' => $filiale->id,
                        'name' => $filiale->name,
                        'codice' => $filiale->codice,
                    ])->values(),
                ])->values(),
                'extras' => $platform->extras->map(fn ($extra) => [
                    'id' => $extra->id,
                    'name' => $extra->name,
                    'amount' => (float) $extra->amount,
                ])->values(),
            ]);

        return response()->json($platforms);
    }
}
