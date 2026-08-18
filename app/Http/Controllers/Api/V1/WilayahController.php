<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IndonesiaRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WilayahController extends Controller
{
    public function provinsi(): JsonResponse
    {
        $data = Cache::remember('wilayah_provinsi_list', 86400, function () {
            return IndonesiaRegion::query()
                ->distinct()
                ->orderBy('provinsi')
                ->pluck('provinsi');
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function kota(Request $request): JsonResponse
    {
        $provinsi = (string) $request->query('provinsi', '');
        $cacheKey = 'wilayah_kota_' . md5($provinsi);

        $data = Cache::remember($cacheKey, 86400, function () use ($provinsi) {
            $query = IndonesiaRegion::query();

            if ($provinsi !== '') {
                $query->where('provinsi', $provinsi);
            }

            return $query->distinct()
                ->orderBy('kota_kabupaten')
                ->pluck('kota_kabupaten');
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function kecamatan(Request $request): JsonResponse
    {
        $provinsi = (string) $request->query('provinsi', '');
        $kota = (string) $request->query('kota', $request->query('kota_kabupaten', ''));
        $cacheKey = 'wilayah_kec_' . md5($provinsi . '_' . $kota);

        $data = Cache::remember($cacheKey, 86400, function () use ($provinsi, $kota) {
            $query = IndonesiaRegion::query();

            if ($provinsi !== '') {
                $query->where('provinsi', $provinsi);
            }

            if ($kota !== '') {
                $query->where('kota_kabupaten', $kota);
            }

            return $query->distinct()
                ->orderBy('kecamatan')
                ->pluck('kecamatan');
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function kelurahan(Request $request): JsonResponse
    {
        $provinsi = (string) $request->query('provinsi', '');
        $kota = (string) $request->query('kota', $request->query('kota_kabupaten', ''));
        $kecamatan = (string) $request->query('kecamatan', '');
        $cacheKey = 'wilayah_kel_' . md5($provinsi . '_' . $kota . '_' . $kecamatan);

        $data = Cache::remember($cacheKey, 86400, function () use ($provinsi, $kota, $kecamatan) {
            $query = IndonesiaRegion::query();

            if ($provinsi !== '') {
                $query->where('provinsi', $provinsi);
            }

            if ($kota !== '') {
                $query->where('kota_kabupaten', $kota);
            }

            if ($kecamatan !== '') {
                $query->where('kecamatan', $kecamatan);
            }

            return $query->distinct()
                ->orderBy('kelurahan')
                ->pluck('kelurahan');
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
