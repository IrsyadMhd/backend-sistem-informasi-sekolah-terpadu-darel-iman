<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TransportationMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModaTransportasiController extends Controller
{
    /**
     * Dapatkan daftar opsi dropdown moda transportasi.
     */
    public function dropdown(Request $request): JsonResponse
    {
        $modes = TransportationMode::active()
            ->select('id', 'name', 'code')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name,
                    'label' => $item->name,
                    'code' => $item->code,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Data moda transportasi berhasil dimuat.',
            'data' => $modes,
        ]);
    }
}
