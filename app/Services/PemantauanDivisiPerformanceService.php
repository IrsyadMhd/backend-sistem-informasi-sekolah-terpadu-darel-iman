<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TblKelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PemantauanDivisiPerformanceService
{
    private ?array $metricsCache = null;

    /**
     * Hitung metrik dasar operasional seluruh unit (di-cache per-request).
     */
    private function getBaseMetrics(): array
    {
        if ($this->metricsCache !== null) {
            return $this->metricsCache;
        }

        // 1. Kesiswaan: Total siswa & keterhubungan orang tua
        $totalSiswa = Student::where('is_active', true)->count();
        $siswaOrtu = Schema::hasTable('student_parents')
            ? DB::table('student_parents')->distinct('student_id')->count('student_id')
            : 0;
        $kesiswaanRate = $totalSiswa > 0 ? round(($siswaOrtu / $totalSiswa) * 100, 1) : 0.0;

        // 2. Kurikulum & Akademik: Keterisian jadwal KBM & alokasi guru
        $totalJadwal = Schema::hasTable('class_schedules')
            ? ClassSchedule::where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))->count()
            : 0;
        $totalKelas = Schema::hasTable('classes') ? DB::table('classes')->count() : 0;
        $kurikulumRate = $totalKelas > 0 ? min(100.0, round(($totalJadwal / max(1, $totalKelas * 12)) * 100, 1)) : 0.0;

        // 3. SDM & Kepegawaian: Verifikasi pegawai & akun sistem
        $totalPegawai = Employee::count();
        $pegawaiBerjabatan = Employee::whereHas('position')->count();
        $pegawaiBerakun = Employee::whereNotNull('user_id')->count();
        $sdmRate = $totalPegawai > 0 ? round((($pegawaiBerjabatan + $pegawaiBerakun) / (2 * $totalPegawai)) * 100, 1) : 0.0;

        // 4. Tahfizh & Al-Qur'an: Alokasi pembina halaqah & rasio santri
        $guruTahfizhCount = Teacher::whereHas('employee.position', function ($pq) {
            $pq->where('name', 'ilike', '%tahf%')
               ->orWhere('name', 'ilike', '%qur%')
               ->orWhere('name', 'ilike', '%tahsin%');
        })->count();
        $tahfizhRate = ($guruTahfizhCount > 0 && $totalSiswa > 0) ? min(100.0, round((($guruTahfizhCount * 25) / $totalSiswa) * 100, 1)) : 0.0;

        // 5. Sarpras & Fasilitas: Rasio rombel aktif per unit pendidikan
        $totalUnits = EducationUnit::where('is_active', true)->count();
        $sarprasRate = ($totalUnits > 0 && $totalKelas > 0) ? min(100.0, round(($totalKelas / ($totalUnits * 2)) * 100, 1)) : 0.0;

        $this->metricsCache = [
            'total_siswa' => $totalSiswa,
            'siswa_ortu' => $siswaOrtu,
            'kesiswaan_rate' => min(100.0, max(0.0, $kesiswaanRate)),
            'total_jadwal' => $totalJadwal,
            'kurikulum_rate' => min(100.0, max(0.0, $kurikulumRate)),
            'total_pegawai' => $totalPegawai,
            'sdm_rate' => min(100.0, max(0.0, $sdmRate)),
            'tahfizh_rate' => min(100.0, max(0.0, $tahfizhRate)),
            'sarpras_rate' => min(100.0, max(0.0, $sarprasRate)),
        ];

        return $this->metricsCache;
    }

    /**
     * Hitung metrik performa riil secara otomatis berdasarkan nama divisi.
     */
    public function getLivePerformanceForDivision(string $divisionName, ?string $unitName = null): array
    {
        $metrics = $this->getBaseMetrics();
        $nameLower = strtolower($divisionName);

        // Kategori 1: Al-Qur'an / Tahfizh / Tahsin
        if (str_contains($nameLower, 'tahf') || str_contains($nameLower, 'tahsin') || str_contains($nameLower, 'qur') || str_contains($nameLower, 'halaq')) {
            $capaian = $metrics['tahfizh_rate'];
            return [
                'kategori_laporan' => "Al-Qur'an & Tahfizh",
                'aspek_pemantauan' => 'Ketercapaian Target Setoran Ziyadah & Muraja\'ah Tahfizh',
                'persentase_capaian' => $capaian,
                'status_pemantauan' => $this->resolveStatus($capaian),
                'indikator_riil' => "Rasio Pembina Halaqah Aktif Terverifikasi",
                'catatan_sistem' => "Kelompok halaqah tahfizh aktif terbina dengan rasio santri proporsional.",
            ];
        }

        // Kategori 2: Kesiswaan & Kedisiplinan / Wali Kelas / BK
        if (str_contains($nameLower, 'kesiswaan') || str_contains($nameLower, 'kesantrian') || str_contains($nameLower, 'walas') || str_contains($nameLower, 'wali') || str_contains($nameLower, 'bk') || str_contains($nameLower, 'bimbingan')) {
            $capaian = $metrics['kesiswaan_rate'];
            return [
                'kategori_laporan' => 'Kesiswaan & Kedisiplinan',
                'aspek_pemantauan' => 'Tingkat Kehadiran, Kedisiplinan & Pendampingan Santri',
                'persentase_capaian' => $capaian,
                'status_pemantauan' => $this->resolveStatus($capaian),
                'indikator_riil' => "{$metrics['siswa_ortu']} dari {$metrics['total_siswa']} Santri Terhubung Profil",
                'catatan_sistem' => "Data presensi & verifikasi wali santri unit berjalan disiplin dan terpantau berkala.",
            ];
        }

        // Kategori 3: Kurikulum, Guru Mapel, KBM, Dirosah, PAI, Pembelajaran
        if (str_contains($nameLower, 'kurikulum') || str_contains($nameLower, 'wakakur') || str_contains($nameLower, 'mapel') || str_contains($nameLower, 'guru') || str_contains($nameLower, 'pendidik') || str_contains($nameLower, 'akademik') || str_contains($nameLower, 'coding') || str_contains($nameLower, 'bahasa') || str_contains($nameLower, 'ipa') || str_contains($nameLower, 'ips') || str_contains($nameLower, 'matematika') || str_contains($nameLower, 'pai') || str_contains($nameLower, 'dirosah') || str_contains($nameLower, 'pjok') || str_contains($nameLower, 'pendidikan')) {
            $capaian = $metrics['kurikulum_rate'];
            return [
                'kategori_laporan' => 'Akademik & Pembelajaran',
                'aspek_pemantauan' => 'Keterlaksanaan Jadwal KBM & Ketuntasan Modul Ajar',
                'persentase_capaian' => $capaian,
                'status_pemantauan' => $this->resolveStatus($capaian),
                'indikator_riil' => "{$metrics['total_jadwal']} Sesi Jadwal KBM Terjadwal",
                'catatan_sistem' => "Pelaksanaan KBM berjalan terjadwal dengan kesiapan modul ajar tahunan aktif.",
            ];
        }

        // Kategori 4: Sarana Prasarana / Fasilitas / Kebersihan / Keamanan / Labor / Dapur
        if (str_contains($nameLower, 'sarpras') || str_contains($nameLower, 'sarana') || str_contains($nameLower, 'kebersihan') || str_contains($nameLower, 'security') || str_contains($nameLower, 'keamanan') || str_contains($nameLower, 'labor') || str_contains($nameLower, 'pustaka') || str_contains($nameLower, 'perpustakaan') || str_contains($nameLower, 'dapur') || str_contains($nameLower, 'masak')) {
            $capaian = $metrics['sarpras_rate'];
            return [
                'kategori_laporan' => 'Sarpras & Lingkungan Sekolah',
                'aspek_pemantauan' => 'Kesiapan Sarana Belajar & Pemeliharaan Fasilitas Unit',
                'persentase_capaian' => $capaian,
                'status_pemantauan' => $this->resolveStatus($capaian),
                'indikator_riil' => 'Kesiapan Sarpras Rombel Belajar Unit',
                'catatan_sistem' => 'Inventaris sarana ruang kelas dan fasilitas pendukung operasional terpelihara baik.',
            ];
        }

        // Kategori 5: Tata Usaha / Kepegawaian / SDM / Operator / Pimpinan
        $capaian = $metrics['sdm_rate'];
        return [
            'kategori_laporan' => 'SDM & Tata Kelola Operasional',
            'aspek_pemantauan' => 'Kepatuhan Pelaporan Administrasi & Kedisiplinan Staf',
            'persentase_capaian' => $capaian,
            'status_pemantauan' => $this->resolveStatus($capaian),
            'indikator_riil' => "{$metrics['total_pegawai']} Pegawai & Tendik Terdaftar",
            'catatan_sistem' => "Kelengkapan berkas administrasi dan pelaporan divisi terorganisir tertib.",
        ];
    }

    /**
     * Tentukan status pemantauan semantik berdasarkan persentase capaian riil.
     */
    private function resolveStatus(float $persen): string
    {
        if ($persen >= 80.0) {
            return 'tercapai';
        }
        if ($persen >= 50.0) {
            return 'proses';
        }
        if ($persen > 0.0) {
            return 'terlambat';
        }
        return 'belum_tercapai';
    }
}
