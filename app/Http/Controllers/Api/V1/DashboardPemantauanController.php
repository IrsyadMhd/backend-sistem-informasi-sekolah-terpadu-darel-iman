<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PemantauanDashboard\DaftarPemantauanDashboardRequest;
use App\Http\Requests\PemantauanDashboard\SimpanIndikatorKinerjaUtamaRequest;
use App\Http\Requests\PemantauanDashboard\SimpanLaporanBulananPemantauanRequest;
use App\Http\Requests\PemantauanDashboard\SimpanPemantauanDivisiRequest;
use App\Http\Requests\PemantauanDashboard\SimpanPengumumanSekolahRequest;
use App\Http\Requests\PemantauanDashboard\SimpanRekapPrestasiSiswaRequest;
use App\Http\Resources\RingkasanDashboardPemantauanResource;
use App\Models\IndikatorKinerjaUtama;
use App\Models\LaporanBulanan;
use App\Models\PemantauanDivisi;
use App\Models\PengumumanSekolah;
use App\Models\RekapPrestasiSiswa;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardPemantauanController extends Controller
{
    private const ROLE_AKSES = [
        'Super Admin',
        'Kepala Sekolah',
        'Divisi Pendidikan',
        'Guru',
    ];

    private const PERMISSION_AKSES = 'dashboard.pemantauan.lihat';
    private const PERMISSION_KELOLA = 'dashboard.pemantauan.kelola';

    public function ringkasan(Request $request): RingkasanDashboardPemantauanResource
    {
        $this->pastikanHakAkses($request);

        $hariIni = now()->toDateString();
        $awalMinggu = now()->subDays(6)->toDateString();

        $cacheKey = "dashboard_pemantauan_ringkasan_{$hariIni}";

        $cachedData = Cache::remember($cacheKey, 120, function () use ($hariIni, $awalMinggu) {
            if (Schema::hasTable('attendances')) {
                $attendanceCounts = DB::table('attendances')
                    ->where('attendance_date', $hariIni)
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as terlambat,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as tidak_hadir
                    ")
                    ->first();

                $hadirHariIni = (int) ($attendanceCounts->total ?? 0);
                $terlambatHariIni = (int) ($attendanceCounts->terlambat ?? 0);
                $tidakHadirHariIni = (int) ($attendanceCounts->tidak_hadir ?? 0);

                $lineChartMingguan = DB::table('attendances')
                    ->selectRaw('attendance_date as label, count(*) as total')
                    ->whereBetween('attendance_date', [$awalMinggu, $hariIni])
                    ->groupBy('attendance_date')
                    ->orderBy('attendance_date')
                    ->get();
            } else {
                $hadirHariIni = 0;
                $terlambatHariIni = 0;
                $tidakHadirHariIni = 0;
                $lineChartMingguan = collect();
            }

            $donutChart = [
                ['label' => 'Hadir', 'nilai' => max($hadirHariIni - $terlambatHariIni - $tidakHadirHariIni, 0)],
                ['label' => 'Terlambat', 'nilai' => $terlambatHariIni],
                ['label' => 'Tidak Hadir', 'nilai' => $tidakHadirHariIni],
            ];

            if (Schema::hasTable('tahfizh_records') && Schema::hasTable('classes')) {
                $barTahfizh = DB::table('tahfizh_records')
                    ->join('classes', 'classes.id', '=', 'tahfizh_records.class_id')
                    ->selectRaw('classes.name as kelas, COALESCE(SUM(tahfizh_records.line_count), 0) as total_baris')
                    ->whereBetween('record_date', [$awalMinggu, $hariIni])
                    ->groupBy('classes.name')
                    ->orderByDesc('total_baris')
                    ->limit(7)
                    ->get();

                $rataBarisTahfizh = (float) DB::table('tahfizh_records')
                    ->whereBetween('record_date', [now()->subDays(30)->toDateString(), $hariIni])
                    ->avg('line_count');
            } else {
                $barTahfizh = collect();
                $rataBarisTahfizh = 0;
            }

            if (Schema::hasTable('mutabaah_records')) {
                $ibadahRingkas = DB::table('mutabaah_records')
                    ->selectRaw('COALESCE(AVG(tilawah_lines), 0) as rata_tilawah, COALESCE(AVG(CASE WHEN sunnah_fasting THEN 100 ELSE 0 END), 0) as persen_puasa_sunnah')
                    ->whereBetween('record_date', [$awalMinggu, $hariIni])
                    ->first();
            } else {
                $ibadahRingkas = (object) [
                    'rata_tilawah' => 0,
                    'persen_puasa_sunnah' => 0,
                ];
            }

            $targetBaris = 10;
            $persenTargetTahfizh = $targetBaris > 0 ? min(round(($rataBarisTahfizh / $targetBaris) * 100, 2), 100) : 0;

            return [
                'total_siswa' => Student::query()->count(),
                'total_guru' => Teacher::query()->count(),
                'kehadiran_hari_ini' => $hadirHariIni,
                'statistik_keterlambatan' => $terlambatHariIni,
                'statistik_ketidakhadiran' => $tidakHadirHariIni,
                'donut_chart' => $donutChart,
                'line_chart_kehadiran_mingguan' => $lineChartMingguan,
                'bar_chart_tahfizh' => $barTahfizh,
                'progress_target_tahfizh' => [
                    'target_baris_per_hari' => $targetBaris,
                    'realisasi_rata_baris' => round($rataBarisTahfizh, 2),
                    'persentase' => $persenTargetTahfizh,
                ],
                'progress_ibadah_siswa' => [
                    'rata_tilawah_baris' => round((float) ($ibadahRingkas->rata_tilawah ?? 0), 2),
                    'persen_puasa_sunnah' => round((float) ($ibadahRingkas->persen_puasa_sunnah ?? 0), 2),
                ],
                'data_tabel_rekap_prestasi' => RekapPrestasiSiswa::query()->latest('tanggal_prestasi')->limit(10)->get(),
                'pengumuman_sekolah' => PengumumanSekolah::query()
                    ->where('status_aktif', true)
                    ->where(function ($query) {
                        $query->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now());
                    })
                    ->where('mulai_tampil', '<=', now())
                    ->orderByDesc('prioritas')
                    ->orderByDesc('mulai_tampil')
                    ->limit(10)
                    ->get(),
                'indikator_kinerja_utama' => $this->hitungIndikatorPerluPerhatian(),
            ];
        });

        return new RingkasanDashboardPemantauanResource($cachedData);
    }

    public function daftarPemantauanDivisi(DaftarPemantauanDashboardRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request);

        $data = PemantauanDivisi::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->validated('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('nama_divisi', 'ilike', "%{$search}%")
                        ->orWhere('aspek_pemantauan', 'ilike', "%{$search}%")
                        ->orWhere('status_pemantauan', 'ilike', "%{$search}%");
                });
            })
            ->latest('tanggal_pemantauan')
            ->paginate((int) $request->validated('per_page', 15));

        return response()->json($data);
    }

    public function simpanPemantauanDivisi(SimpanPemantauanDivisiRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = DB::transaction(function () use ($request) {
            return PemantauanDivisi::query()->create([
                ...$request->validated(),
                'id_penginput' => $request->user()->id,
            ]);
        });

        return response()->json(['message' => 'Data pemantauan divisi berhasil disimpan.', 'data' => $model], 201);
    }

    public function detailPemantauanDivisi(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request);

        return response()->json(PemantauanDivisi::query()->findOrFail($id));
    }

    public function ubahPemantauanDivisi(SimpanPemantauanDivisiRequest $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = DB::transaction(function () use ($request, $id) {
            $model = PemantauanDivisi::query()->findOrFail($id);
            $model->update($request->validated());

            return $model->fresh();
        });

        return response()->json(['message' => 'Data pemantauan divisi berhasil diperbarui.', 'data' => $model]);
    }

    public function hapusPemantauanDivisi(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);
        PemantauanDivisi::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Data pemantauan divisi berhasil dihapus.']);
    }

    public function daftarLaporanBulanan(DaftarPemantauanDashboardRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request);

        $data = LaporanBulanan::query()
            ->when($request->filled('bulan'), fn ($query) => $query->where('bulan', (int) $request->validated('bulan')))
            ->when($request->filled('tahun'), fn ($query) => $query->where('tahun', (int) $request->validated('tahun')))
            ->latest('tahun')
            ->latest('bulan')
            ->paginate((int) $request->validated('per_page', 15));

        return response()->json($data);
    }

    public function simpanLaporanBulanan(SimpanLaporanBulananPemantauanRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = LaporanBulanan::query()->create([
            ...$request->validated(),
            'id_penginput' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Laporan bulanan berhasil disimpan.', 'data' => $model], 201);
    }

    public function detailLaporanBulanan(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request);

        return response()->json(LaporanBulanan::query()->findOrFail($id));
    }

    public function ubahLaporanBulanan(SimpanLaporanBulananPemantauanRequest $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = LaporanBulanan::query()->findOrFail($id);
        $model->update($request->validated());

        return response()->json(['message' => 'Laporan bulanan berhasil diperbarui.', 'data' => $model->fresh()]);
    }

    public function hapusLaporanBulanan(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);
        LaporanBulanan::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Laporan bulanan berhasil dihapus.']);
    }

    public function daftarRekapPrestasiSiswa(DaftarPemantauanDashboardRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request);

        $data = RekapPrestasiSiswa::query()
            ->with('siswa:id,full_name,nis')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->validated('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('nama_prestasi', 'ilike', "%{$search}%")
                        ->orWhere('jenis_prestasi', 'ilike', "%{$search}%")
                        ->orWhere('tingkat_prestasi', 'ilike', "%{$search}%");
                });
            })
            ->latest('tanggal_prestasi')
            ->paginate((int) $request->validated('per_page', 15));

        return response()->json($data);
    }

    public function simpanRekapPrestasiSiswa(SimpanRekapPrestasiSiswaRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = RekapPrestasiSiswa::query()->create([
            ...$request->validated(),
            'id_penginput' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Rekap prestasi siswa berhasil disimpan.', 'data' => $model], 201);
    }

    public function detailRekapPrestasiSiswa(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request);

        return response()->json(RekapPrestasiSiswa::query()->with('siswa:id,full_name,nis')->findOrFail($id));
    }

    public function ubahRekapPrestasiSiswa(SimpanRekapPrestasiSiswaRequest $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = RekapPrestasiSiswa::query()->findOrFail($id);
        $model->update($request->validated());

        return response()->json(['message' => 'Rekap prestasi siswa berhasil diperbarui.', 'data' => $model->fresh()]);
    }

    public function hapusRekapPrestasiSiswa(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);
        RekapPrestasiSiswa::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Rekap prestasi siswa berhasil dihapus.']);
    }

    public function daftarPengumumanSekolah(DaftarPemantauanDashboardRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request);

        $data = PengumumanSekolah::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->validated('search');
                $query->whereRaw("to_tsvector('simple', coalesce(judul_pengumuman,'') || ' ' || coalesce(isi_pengumuman,'')) @@ plainto_tsquery('simple', ?)", [$search]);
            })
            ->latest('mulai_tampil')
            ->paginate((int) $request->validated('per_page', 15));

        return response()->json($data);
    }

    public function simpanPengumumanSekolah(SimpanPengumumanSekolahRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = PengumumanSekolah::query()->create([
            ...$request->validated(),
            'prioritas' => (int) $request->validated('prioritas', 1),
            'id_penerbit' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Pengumuman sekolah berhasil dipublikasikan.', 'data' => $model], 201);
    }

    public function detailPengumumanSekolah(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request);

        return response()->json(PengumumanSekolah::query()->findOrFail($id));
    }

    public function ubahPengumumanSekolah(SimpanPengumumanSekolahRequest $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = PengumumanSekolah::query()->findOrFail($id);
        $model->update($request->validated());

        return response()->json(['message' => 'Pengumuman sekolah berhasil diperbarui.', 'data' => $model->fresh()]);
    }

    public function hapusPengumumanSekolah(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);
        PengumumanSekolah::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Pengumuman sekolah berhasil dihapus.']);
    }

    public function daftarIndikatorKinerjaUtama(DaftarPemantauanDashboardRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request);

        $data = IndikatorKinerjaUtama::query()
            ->when($request->filled('bulan'), fn ($query) => $query->where('bulan_periode', (int) $request->validated('bulan')))
            ->when($request->filled('tahun'), fn ($query) => $query->where('tahun_periode', (int) $request->validated('tahun')))
            ->orderBy('urutan_tampil')
            ->paginate((int) $request->validated('per_page', 15));

        return response()->json($data);
    }

    public function simpanIndikatorKinerjaUtama(SimpanIndikatorKinerjaUtamaRequest $request): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = IndikatorKinerjaUtama::query()->updateOrCreate(
            ['kode_indikator' => (string) $request->validated('kode_indikator')],
            [
                ...$request->validated(),
                'urutan_tampil' => (int) $request->validated('urutan_tampil', 0),
                'id_penginput' => $request->user()->id,
            ]
        );

        return response()->json(['message' => 'Indikator kinerja utama berhasil disimpan.', 'data' => $model], 201);
    }

    public function detailIndikatorKinerjaUtama(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request);

        return response()->json(IndikatorKinerjaUtama::query()->findOrFail($id));
    }

    public function ubahIndikatorKinerjaUtama(SimpanIndikatorKinerjaUtamaRequest $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);

        $model = IndikatorKinerjaUtama::query()->findOrFail($id);
        $model->update($request->validated());

        return response()->json(['message' => 'Indikator kinerja utama berhasil diperbarui.', 'data' => $model->fresh()]);
    }

    public function hapusIndikatorKinerjaUtama(Request $request, string $id): JsonResponse
    {
        $this->pastikanHakAkses($request, true);
        IndikatorKinerjaUtama::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Indikator kinerja utama berhasil dihapus.']);
    }

    private function pastikanHakAkses(Request $request, bool $butuhKelola = false): void
    {
        $user = $request->user();
        $izin = $butuhKelola ? self::PERMISSION_KELOLA : self::PERMISSION_AKSES;

        if (! $user || ! $user->can($izin)) {
            abort(403, 'Anda tidak memiliki izin modul dashboard pemantauan.');
        }
    }

    /**
     * Menghitung indikator kinerja operasional yang perlu perhatian secara dinamis dari database riil
     * (tanpa hardcode), mencakup tabel IKU, pemantauan divisi, presensi kesiswaan, dan jadwal pengajaran.
     *
     * @return array<int, array<string, mixed>>
     */
    private function hitungIndikatorPerluPerhatian(): array
    {
        $alerts = collect();

        // 1. Data IKU tersimpan di database jika ada yang belum mencapai target (nilai < target_nilai)
        if (Schema::hasTable('indikator_kinerja_utamas')) {
            $ikuData = IndikatorKinerjaUtama::query()
                ->where(function ($query) {
                    $query->whereNotNull('target_nilai')
                        ->whereColumn('nilai', '<', 'target_nilai');
                })
                ->orWhere('kategori_indikator', 'kritis')
                ->orderBy('urutan_tampil')
                ->limit(5)
                ->get();

            foreach ($ikuData as $iku) {
                $alerts->push([
                    'id' => (string) $iku->id,
                    'nama_indikator' => "IKU [{$iku->kategori_indikator}]: {$iku->nama_indikator} (Capaian: {$iku->nilai}/{$iku->target_nilai} {$iku->satuan})",
                    'status' => 'perlu_perhatian',
                ]);
            }
        }

        // 2. Pemantauan Divisi: Aspek dengan persentase capaian terendah (< 75%) atau status belum tercapai
        if (Schema::hasTable('pemantauan_divisis')) {
            $divisiPerluPerhatian = DB::table('pemantauan_divisis')
                ->where(function ($q) {
                    $q->where('persentase_capaian', '<', 75)
                        ->orWhereIn('status_pemantauan', ['belum_tercapai', 'tertunda', 'proses']);
                })
                ->orderBy('persentase_capaian', 'asc')
                ->limit(3)
                ->get(['id', 'nama_divisi', 'aspek_pemantauan', 'persentase_capaian', 'status_pemantauan']);

            foreach ($divisiPerluPerhatian as $div) {
                $capaian = round((float) $div->persentase_capaian);
                $alerts->push([
                    'id' => (string) $div->id,
                    'nama_indikator' => "Evaluasi Divisi {$div->nama_divisi}: {$div->aspek_pemantauan} (Realisasi: {$capaian}%)",
                    'status' => 'perlu_perhatian',
                ]);
            }
        }

        // 3. Presensi Kesiswaan: Deteksi ketidakhadiran atau keterlambatan siswa dari sesi presensi terbaru
        if (Schema::hasTable('attendances')) {
            $latestDate = DB::table('attendances')->max('attendance_date');
            if ($latestDate) {
                $stats = DB::table('attendances')
                    ->where('attendance_date', $latestDate)
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN UPPER(status) = 'TERLAMBAT' OR status = 'late' THEN 1 ELSE 0 END) as terlambat,
                        SUM(CASE WHEN UPPER(status) IN ('SAKIT', 'IZIN', 'ALPHA', 'ALPA', 'absent') THEN 1 ELSE 0 END) as tidak_hadir
                    ")
                    ->first();

                if ($stats && (int) $stats->tidak_hadir > 0) {
                    $alerts->push([
                        'id' => 'alert-attendance-absence-' . $latestDate,
                        'nama_indikator' => "Presensi Siswa ({$latestDate}): {$stats->tidak_hadir} siswa tidak hadir dari {$stats->total} siswa terdata",
                        'status' => 'perlu_perhatian',
                    ]);
                }

                if ($stats && (int) $stats->terlambat > 0) {
                    $alerts->push([
                        'id' => 'alert-attendance-late-' . $latestDate,
                        'nama_indikator' => "Ketepatan Waktu Siswa ({$latestDate}): {$stats->terlambat} siswa tercatat datang terlambat",
                        'status' => 'perlu_perhatian',
                    ]);
                }
            }
        }

        return $alerts->take(5)->values()->all();
    }
}
