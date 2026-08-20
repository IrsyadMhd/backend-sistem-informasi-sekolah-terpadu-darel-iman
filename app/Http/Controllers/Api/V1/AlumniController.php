<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RekapPrestasiSiswa;
use App\Models\Student;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    /**
     * Ambil daftar alumni, lulusan, dan siswa mutasi (masuk/keluar) real-time berdasar scope unit
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessScopeService = app(AccessScopeService::class);
        $accessibleUnitIds = $accessScopeService->accessibleEducationUnits($user)->pluck('id');

        $query = Student::with(['educationUnit', 'schoolClass'])
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('metadata->is_alumni', true)
                  ->orWhere('metadata->status_siswa', 'alumni')
                  ->orWhere('metadata->status_alumni', 'alumni')
                  ->orWhere('metadata->status_alumni', 'Tamat')
                  ->orWhere('metadata->status_alumni', 'Lulus')
                  ->orWhereNotNull('metadata->tahun_lulus')
                  ->orWhereNotNull('metadata->mutasi_type');
            });

        // Scope data real-time ke unit_id pengguna (kecuali Super Admin / Yayasan / Admin)
        if (! $accessScopeService->hasGlobalScope($user) && $accessibleUnitIds->isNotEmpty()) {
            $query->whereIn('unit_id', $accessibleUnitIds);
        }

        // Search Filter (Nama, NIS, NISN, Tujuan Kelulusan, PTN/PTS, Pekerjaan)
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('metadata->tujuan_kelulusan', 'like', "%{$search}%")
                  ->orWhere('metadata->perguruan_tinggi', 'like', "%{$search}%")
                  ->orWhere('metadata->status_lanjutan', 'like', "%{$search}%")
                  ->orWhere('metadata->pekerjaan', 'like', "%{$search}%");
            });
        }

        // Filter Tahun Lulus / Mutasi
        if ($request->filled('tahun_lulus') && !in_array($request->query('tahun_lulus'), ['semua', 'all', ''])) {
            $tahun = (string) $request->query('tahun_lulus');
            $query->where(function ($q) use ($tahun) {
                $q->where('metadata->tahun_lulus', $tahun)
                  ->orWhere('metadata->tahun_mutasi', $tahun)
                  ->orWhere('tahun_masuk', $tahun)
                  ->orWhereYear('updated_at', $tahun)
                  ->orWhereYear('created_at', $tahun);
            });
        }

        // Filter Jenis Status / Mutasi (lulusan / alumni, mutasi masuk, mutasi keluar)
        if ($request->filled('mutasi_type') && !in_array($request->query('mutasi_type'), ['semua', 'all', ''])) {
            $mutasiType = (string) $request->query('mutasi_type');
            if (in_array($mutasiType, ['alumni', 'lulusan', 'tamat'])) {
                $query->where(function ($q) {
                    $q->where('metadata->is_alumni', true)
                      ->orWhere('metadata->status_siswa', 'alumni')
                      ->orWhere('metadata->status_alumni', 'alumni')
                      ->orWhere('metadata->status_alumni', 'Tamat')
                      ->orWhere('metadata->status_alumni', 'Lulus')
                      ->orWhere(function ($sub) {
                          $sub->whereNull('metadata->mutasi_type')
                              ->where('is_active', false);
                      });
                });
            } elseif ($mutasiType === 'masuk') {
                $query->where('metadata->mutasi_type', 'masuk');
            } elseif ($mutasiType === 'keluar') {
                $query->where(function ($q) {
                    $q->where('metadata->mutasi_type', 'keluar')
                      ->orWhere('metadata->mutasi_type', 'berhenti');
                });
            }
        }

        // Filter Tujuan Kelulusan
        if ($request->filled('tujuan_kelulusan') && !in_array($request->query('tujuan_kelulusan'), ['semua', 'all', ''])) {
            $tujuan = (string) $request->query('tujuan_kelulusan');
            $query->where(function ($q) use ($tujuan) {
                $q->where('metadata->tujuan_kelulusan', 'like', "%{$tujuan}%")
                  ->orWhere('metadata->perguruan_tinggi', 'like', "%{$tujuan}%")
                  ->orWhere('metadata->status_lanjutan', 'like', "%{$tujuan}%")
                  ->orWhere('metadata->pekerjaan', 'like', "%{$tujuan}%");
            });
        }

        $perPage = (int) $request->query('per_page', 50);
        $alumni = $query->orderByDesc('updated_at')->paginate($perPage);

        return response()->json($alumni);
    }

    /**
     * Ambil ringkasan statistik alumni real-time berdasar scope unit
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessScopeService = app(AccessScopeService::class);
        $accessibleUnitIds = $accessScopeService->accessibleEducationUnits($user)->pluck('id');

        $studentQuery = Student::query();
        if (! $accessScopeService->hasGlobalScope($user) && $accessibleUnitIds->isNotEmpty()) {
            $studentQuery->whereIn('unit_id', $accessibleUnitIds);
        }

        $totalAlumni = (clone $studentQuery)->where(function ($q) {
            $q->where('metadata->is_alumni', true)
              ->orWhere('metadata->status_siswa', 'alumni')
              ->orWhere('metadata->status_alumni', 'alumni')
              ->orWhere('metadata->status_alumni', 'Tamat')
              ->orWhere('metadata->status_alumni', 'Lulus')
              ->orWhere(function ($sub) {
                  $sub->whereNull('metadata->mutasi_type')
                      ->where('is_active', false);
              });
        })->count();

        $pindahMasuk = (clone $studentQuery)->where('metadata->mutasi_type', 'masuk')->count();
        $pindahKeluar = (clone $studentQuery)->where(function ($q) {
            $q->where('metadata->mutasi_type', 'keluar')
              ->orWhere('metadata->mutasi_type', 'berhenti');
        })->count();

        $totalSiswa = (clone $studentQuery)->count();
        $totalPrestasi = RekapPrestasiSiswa::count();
        $siswaLulusTahunIni = (clone $studentQuery)->where('is_active', false)
            ->whereYear('updated_at', now()->year)
            ->count();

        $lanjutStudi = (clone $studentQuery)->where(function ($q) {
            $q->whereNotNull('metadata->tujuan_kelulusan')
              ->orWhereNotNull('metadata->perguruan_tinggi')
              ->orWhereNotNull('metadata->status_lanjutan');
        })->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_alumni' => $totalAlumni,
                'pindah_masuk' => $pindahMasuk,
                'pindah_keluar' => $pindahKeluar,
                'total_prestasi' => $totalPrestasi,
                'lulus_tahun_ini' => $siswaLulusTahunIni,
                'lanjut_studi' => $lanjutStudi,
                'persentase_kelulusan' => $totalSiswa > 0 ? round(($totalAlumni / $totalSiswa) * 100, 1) : 100,
                'rekap_prestasi' => RekapPrestasiSiswa::latest('tanggal_prestasi')->limit(10)->get(),
            ],
        ]);
    }
}
