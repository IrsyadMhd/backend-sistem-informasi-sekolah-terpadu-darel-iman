<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Division;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\PemantauanDivisi;
use App\Models\Semester;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PemantauanDivisiSeeder extends Seeder
{
    /**
     * Seed database pemantauan divisi secara dinamis dan terstruktur dari data unit riil.
     */
    public function run(): void
    {
        if (! Schema::hasTable('pemantauan_divisis')) {
            $this->command?->warn('Tabel pemantauan_divisis belum ada. Jalankan migration terlebih dahulu.');

            return;
        }

        // 1. Tarik user penginput secara dinamis dari database (Superadmin/Pimpinan/User pertama)
        $penginput = User::where('email', 'superadmin@dareliman.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        if (! $penginput) {
            $this->command?->warn('PemantauanDivisiSeeder: Tidak ditemukan user penginput di database.');

            return;
        }

        // 2. Tarik Tahun Ajaran & Semester aktif secara dinamis dari database
        $tahunAjaran = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::latest()->first();

        $semester = $tahunAjaran
            ? Semester::query()
                ->where('academic_year_id', $tahunAjaran->id)
                ->orderByDesc('is_active')
                ->orderBy('sequence')
                ->first()
            : null;

        // 3. Tarik master unit pendidikan aktif
        $units = EducationUnit::where('is_active', true)->orderBy('name')->get();
        if ($units->isEmpty()) {
            $units = EducationUnit::all();
        }

        // 4. Pastikan divisi-divisi operasional utama terdaftar di master divisions
        $coreDivisions = [
            'Divisi Pendidikan' => 'Akademik & Pembelajaran',
            'Divisi Kurikulum' => 'Akademik & Pembelajaran',
            'Divisi Kesiswaan' => 'Kesiswaan & Kedisiplinan',
            'Divisi Tahfizh' => "Al-Qur'an & Tahfizh",
            'Divisi Bahasa' => 'Akademik & Pembelajaran',
            'Tata Usaha (TU)' => 'SDM & Tata Kelola Operasional',
            'Sarana & Prasarana' => 'Sarpras & Lingkungan Sekolah',
        ];

        $divisionModels = [];
        foreach ($coreDivisions as $divName => $kategori) {
            $code = 'DIV-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $divName), 0, 8));
            $div = Division::firstOrCreate(
                ['name' => $divName],
                [
                    'code' => $code,
                    'description' => "Divisi Operasional {$divName}",
                    'is_active' => true,
                    'created_by' => $penginput->id,
                ]
            );
            $divisionModels[] = [
                'model' => $div,
                'kategori' => $kategori,
            ];
        }

        // 5. Daftar petugas pengawas supervisi yayasan/divisi
        $supervisors = [
            'Ust. Hendri Fauzan, M.Pd (Supervisi Pendidikan)',
            'Ust. Ahmad Fauzi, S.Pd.I (Pengawas Kurikulum)',
            'Ust. Ridwan Effendi, Lc (Koordinator Tahfizh)',
            'Dr. H. Zulkifli, M.A (Tim Monev Yayasan)',
            'Ustzh. Nurhasanah, S.Pd (Pengawas Kesiswaan)',
            'Ust. Irwan Syahputra, S.T (Supervisi Sarpras & Aset)',
        ];

        // 6. Tanggal referensi semester ganjil tahun ajaran berjalan (2026)
        $startDate = $semester?->start_date
            ? Carbon::parse($semester->start_date)
            : Carbon::create(2026, 7, 1);

        $perfService = app(\App\Services\PemantauanDivisiPerformanceService::class);

        DB::transaction(function () use ($units, $divisionModels, $supervisors, $tahunAjaran, $semester, $penginput, $startDate, $perfService) {
            // Bersihkan data lama yang belum terhubung dengan unit_id
            PemantauanDivisi::whereNull('unit_id')->delete();

            $counter = 0;
            foreach ($units as $unitIndex => $unit) {
                // Pilih subset divisi yang dimonitor per unit
                foreach ($divisionModels as $divIndex => $divData) {
                    $div = $divData['model'];
                    $kategori = $divData['kategori'];

                    $supervisor = $supervisors[($unitIndex + $divIndex) % count($supervisors)];
                    $livePerf = $perfService->getLivePerformanceForDivision($div->name, $unit->name);

                    // Distribusi persentase capaian yang bervariasi dan realistis per unit
                    $baseCapaian = (float) $livePerf['persentase_capaian'];
                    $variation = (($unitIndex * 7 + $divIndex * 13) % 25) - 10; // -10 s/d +14
                    $capaian = min(100.0, max(35.0, round($baseCapaian + $variation, 1)));

                    $status = 'proses';
                    if ($capaian >= 80.0) {
                        $status = 'tercapai';
                    } elseif ($capaian < 50.0) {
                        $status = 'belum_tercapai';
                    }

                    // Tanggal supervisi terdistribusi antara Juli hingga Oktober 2026
                    $daysOffset = ($unitIndex * 5) + ($divIndex * 3) + 7;
                    $tanggal = $startDate->copy()->addDays($daysOffset % 110)->format('Y-m-d');

                    $aspek = "Supervisi & Pemantauan Operasional {$div->name} di {$unit->name}";
                    $catatan = "Hasil supervisi berkala oleh {$supervisor}. Capaian indikator {$capaian}% dengan status {$status}.";

                    PemantauanDivisi::updateOrCreate(
                        [
                            'unit_id' => $unit->id,
                            'division_id' => $div->id,
                            'nama_divisi' => $div->name,
                            'aspek_pemantauan' => $aspek,
                        ],
                        [
                            'unit_pendidikan' => $unit->name,
                            'kategori_laporan' => $kategori,
                            'petugas_supervisi' => $supervisor,
                            'id_tahun_ajaran' => $tahunAjaran?->id,
                            'id_semester' => $semester?->id,
                            'persentase_capaian' => $capaian,
                            'status_pemantauan' => $status,
                            'catatan' => $catatan,
                            'tanggal_pemantauan' => $tanggal,
                            'id_penginput' => $penginput->id,
                            'data_tambahan' => [
                                'unit_code' => $unit->code,
                                'divisi_code' => $div->code,
                                'metrik_riil' => $livePerf['indikator_riil'] ?? 'Supervisi Unit Terjadwal',
                                'sumber_data' => 'Database Terintegrasi Riil',
                            ],
                        ]
                    );

                    $counter++;
                }
            }
        });
    }
}
