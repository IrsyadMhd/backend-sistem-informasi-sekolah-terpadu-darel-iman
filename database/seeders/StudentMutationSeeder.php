<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StudentMutationSeeder extends Seeder
{
    public function run(): void
    {
        // Guard against running in production environment
        if (! app()->environment(['local', 'development', 'testing'])) {
            if (isset($this->command) && method_exists($this->command, 'warn')) {
                $this->command->warn('StudentMutationSeeder: Lingkungan production terdeteksi. Seeder mutasi dilewati.');
            }

            return;
        }

        $units = EducationUnit::query()->orderBy('code')->get();
        if ($units->isEmpty()) {
            if (isset($this->command) && method_exists($this->command, 'warn')) {
                $this->command->warn('StudentMutationSeeder: Tidak ada EducationUnit di database. Mengabaikan seeder mutasi.');
            }

            return;
        }

        $academicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::where('is_active', true)->first() ?? Semester::first();
        $kelas = Kelas::first();
        $user = User::first();

        // Ambil atau buat 30 siswa untuk memiliki metadata mutasi
        $existingStudents = Student::query()->orderBy('id')->get();

        $reasons = [
            'Pindah domisili orang tua',
            'Mengikuti pindah tugas orang tua',
            'Melanjutkan ke sekolah lain',
            'Alasan kesehatan / pengobatan',
            'Penyesuaian kebutuhan pendidikan anak',
            'Pindah antarunit yayasan Dar el-Iman',
            'Berhenti atas permintaan orang tua',
            'Dekat dengan rumah keluarga',
        ];

        $externalSchools = [
            'SDN 01 Padang',
            'SDIT Al-Azhar Padang',
            'SMPN 2 50 Kota',
            'SMA N 1 Padang',
            'MIN 3 Padang',
            'MTsN 1 Bukittinggi',
            'SDN 05 Solok',
            'SMPIT Permata Hati',
        ];

        // Definisi 30 skenario data mutasi
        // 10 Masuk, 10 Keluar, 5 Berhenti, 5 Antarunit
        $scenarios = [];

        // 10 Mutasi Masuk
        for ($i = 1; $i <= 10; $i++) {
            $scenarios[] = [
                'type' => 'masuk',
                'prefix' => 'MUT-IN',
                'is_external_in' => true,
            ];
        }
        // 10 Mutasi Keluar
        for ($i = 1; $i <= 10; $i++) {
            $scenarios[] = [
                'type' => 'keluar',
                'prefix' => 'MUT-OUT',
                'is_external_out' => true,
            ];
        }
        // 5 Berhenti
        for ($i = 1; $i <= 5; $i++) {
            $scenarios[] = [
                'type' => 'berhenti',
                'prefix' => 'MUT-STOP',
                'is_external_out' => true,
            ];
        }
        // 5 Pindah Antarunit
        for ($i = 1; $i <= 5; $i++) {
            $scenarios[] = [
                'type' => 'antar_unit',
                'prefix' => 'MUT-TR',
                'is_inter_unit' => true,
            ];
        }

        // Status distribution: 18 selesai, 6 proses, 3 diajukan, 3 ditolak
        $statuses = array_merge(
            array_fill(0, 18, 'Selesai'),
            array_fill(0, 6, 'Proses'),
            array_fill(0, 3, 'Diajukan'),
            array_fill(0, 3, 'Ditolak')
        );
        shuffle($statuses);

        // Rentang 12 bulan terakhir untuk tren bulanan
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();

        foreach ($scenarios as $index => $scen) {
            $status = $statuses[$index % count($statuses)];
            $unitAsal = $units->get($index % $units->count());
            $unitTujuan = $units->get(($index + 1) % $units->count());
            if ($unitAsal->id === $unitTujuan->id && count($units) > 1) {
                $unitTujuan = $units->get(($index + 2) % $units->count());
            }

            $dateOffsetDays = rand(0, 25);
            $effectiveDate = (clone $startDate)->addMonths($index % 12)->addDays($dateOffsetDays);
            $submissionDate = (clone $effectiveDate)->subDays(rand(3, 10));

            $nis = 'DEMO-MUT-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $studentName = 'Siswa Mutasi '.($index + 1).' ('.ucfirst($scen['type']).')';
            $refNumber = $scen['prefix'].'-'.$effectiveDate->format('Ym').'-'.str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            // Logika unit asal/tujuan & sekolah eksternal
            $unitAsalId = null;
            $unitAsalName = '-';
            $unitTujuanId = null;
            $unitTujuanName = '-';
            $externalSchool = null;

            if ($scen['type'] === 'masuk') {
                $unitTujuanId = $unitTujuan->id;
                $unitTujuanName = $unitTujuan->name;
                $externalSchool = $externalSchools[$index % count($externalSchools)];
                $unitAsalName = $externalSchool;
            } elseif ($scen['type'] === 'keluar') {
                $unitAsalId = $unitAsal->id;
                $unitAsalName = $unitAsal->name;
                $externalSchool = $externalSchools[$index % count($externalSchools)];
                $unitTujuanName = $externalSchool;
            } elseif ($scen['type'] === 'berhenti') {
                $unitAsalId = $unitAsal->id;
                $unitAsalName = $unitAsal->name;
                $unitTujuanName = 'Berhenti / Non-Aktif';
            } elseif ($scen['type'] === 'antar_unit') {
                $unitAsalId = $unitAsal->id;
                $unitAsalName = $unitAsal->name;
                $unitTujuanId = $unitTujuan->id;
                $unitTujuanName = $unitTujuan->name;
            }

            $existingStudent = $existingStudents->get($index);

            $metadata = array_merge($existingStudent?->metadata ?? [], [
                'is_demo' => true,
                'nomor_mutasi' => $refNumber,
                'mutasi_type' => $scen['type'],
                'mutasi_status' => $status,
                'unit_asal_id' => $unitAsalId,
                'unit_asal_name' => $unitAsalName,
                'unit_tujuan_id' => $unitTujuanId,
                'unit_tujuan_name' => $unitTujuanName,
                'sekolah_eksternal' => $externalSchool,
                'alasan' => $reasons[$index % count($reasons)],
                'tanggal_pengajuan' => $submissionDate->toDateString(),
                'tanggal_efektif' => $effectiveDate->toDateString(),
                'kelas_asal_name' => $kelas ? $kelas->nama_kelas : 'Kelas 5A',
                'kelas_tujuan_name' => $kelas ? $kelas->nama_kelas : 'Kelas 5B',
                'academic_year_id' => $academicYear?->id,
                'semester_id' => $semester?->id,
                'created_by_name' => $user ? $user->name : 'Pengurus Yayasan',
            ]);

            if ($existingStudent) {
                $existingStudent->update([
                    'unit_id' => $unitAsalId ?? $unitTujuanId ?? $existingStudent->unit_id,
                    'metadata' => $metadata,
                ]);
            } else {
                Student::create([
                    'id' => (string) Str::uuid(),
                    'nis' => $nis,
                    'nisn' => '009'.str_pad($index + 1, 7, '0', STR_PAD_LEFT),
                    'full_name' => $studentName,
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'birth_place' => 'Padang',
                    'birth_date' => '2015-05-15',
                    'address' => 'Jl. Dar el-Iman No. '.($index + 1),
                    'unit_id' => $unitAsalId ?? $unitTujuanId ?? $units->first()->id,
                    'kelas_id' => $kelas?->id,
                    'is_active' => $status !== 'Ditolak',
                    'tahun_masuk' => 2023,
                    'metadata' => $metadata,
                ]);
            }
        }
    }
}
