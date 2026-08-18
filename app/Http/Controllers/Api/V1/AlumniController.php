<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RekapPrestasiSiswa;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    /**
     * Ambil daftar alumni (siswa lulus/non-aktif dengan status alumni)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit', 'schoolClass'])
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('metadata->is_alumni', true)
                  ->orWhere('metadata->status_siswa', 'alumni');
            });

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tahun_lulus')) {
            $tahun = (string) $request->query('tahun_lulus');
            $query->where('metadata->tahun_lulus', $tahun);
        }

        $perPage = (int) $request->query('per_page', 15);
        $alumni = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($alumni);
    }

    /**
     * Ambil ringkasan statistik alumni dan rekap prestasi
     */
    public function stats(): JsonResponse
    {
        $totalAlumni = Student::where(function ($q) {
            $q->where('is_active', false)
              ->orWhere('metadata->is_alumni', true);
        })->count();

        $totalSiswa = Student::count();
        $totalPrestasi = RekapPrestasiSiswa::count();
        $siswaLulusTahunIni = Student::where('is_active', false)
            ->whereYear('updated_at', now()->year)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_alumni' => $totalAlumni,
                'total_prestasi' => $totalPrestasi,
                'lulus_tahun_ini' => $siswaLulusTahunIni,
                'persentase_kelulusan' => $totalSiswa > 0 ? round(($totalAlumni / $totalSiswa) * 100, 1) : 100,
                'rekap_prestasi' => RekapPrestasiSiswa::latest('tanggal_prestasi')->limit(10)->get(),
            ],
        ]);
    }
}
