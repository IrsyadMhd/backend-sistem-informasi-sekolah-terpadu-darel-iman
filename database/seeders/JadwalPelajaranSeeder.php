<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JadwalPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $tahunAjaran = AcademicYear::query()->firstOrCreate([
            'name' => '2026/2027',
        ], [
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
            'metadata' => ['source' => 'JadwalPelajaranSeeder'],
        ]);

        $semesterGanjil = Semester::query()->updateOrCreate([
            'academic_year_id' => $tahunAjaran->id,
            'sequence' => 1,
        ], [
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'metadata' => ['source' => 'JadwalPelajaranSeeder'],
        ]);

        $semesterGenap = Semester::query()->updateOrCreate([
            'academic_year_id' => $tahunAjaran->id,
            'sequence' => 2,
        ], [
            'name' => 'Genap',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
            'metadata' => ['source' => 'JadwalPelajaranSeeder'],
        ]);

        $guru = Employee::query()
            ->where('status', 'Aktif')
            ->orderBy('nama_lengkap')
            ->orderBy('id')
            ->limit(8)
            ->get();
        $mataPelajaran = Subject::query()
            ->where(fn ($query) => $query->where('status', true)->orWhereNull('status'))
            ->orderByRaw('COALESCE(nama_mapel, name)')
            ->orderBy('id')
            ->limit(8)
            ->get();
        $kelasGanjil = Kelas::query()
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester_id', $semesterGanjil->id)
            ->where('status', 'Aktif')
            ->orderBy('kode_kelas')
            ->orderBy('id')
            ->limit(8)
            ->get();

        if ($guru->count() < 4 || $mataPelajaran->count() < 4 || $kelasGanjil->count() < 4) {
            $this->command->warn(
                'JadwalPelajaranSeeder dilewati: minimal empat guru aktif, mata pelajaran, dan kelas Ganjil diperlukan.'
            );

            return;
        }

        DB::transaction(function () use (
            $tahunAjaran,
            $semesterGanjil,
            $semesterGenap,
            $guru,
            $mataPelajaran,
            $kelasGanjil,
        ) {
            $kelasGenap = $this->buatKelasGenap($kelasGanjil, $semesterGenap);

            $this->buatJadwalSemester(
                tahunAjaran: $tahunAjaran,
                semester: $semesterGanjil,
                kelas: $kelasGanjil,
                guru: $guru,
                mataPelajaran: $mataPelajaran,
            );

            $this->buatJadwalSemester(
                tahunAjaran: $tahunAjaran,
                semester: $semesterGenap,
                kelas: $kelasGenap,
                guru: $guru,
                mataPelajaran: $mataPelajaran,
            );
        });

        $jumlahGanjil = ClassSchedule::query()
            ->where('academic_year_id', $tahunAjaran->id)
            ->where('semester_id', $semesterGanjil->id)
            ->where('metadata->source', 'JadwalPelajaranSeeder')
            ->count();
        $jumlahGenap = ClassSchedule::query()
            ->where('academic_year_id', $tahunAjaran->id)
            ->where('semester_id', $semesterGenap->id)
            ->where('metadata->source', 'JadwalPelajaranSeeder')
            ->count();

        $this->command->info(
            "JadwalPelajaranSeeder berhasil: {$jumlahGanjil} jadwal Ganjil dan {$jumlahGenap} jadwal Genap."
        );
    }

    private function buatKelasGenap(Collection $kelasGanjil, Semester $semesterGenap): Collection
    {
        return $kelasGanjil->map(function (Kelas $sumber) use ($semesterGenap) {
            $kodeKelas = substr($sumber->kode_kelas, 0, 43).'-G2';

            return Kelas::query()->updateOrCreate([
                'kode_kelas' => $kodeKelas,
            ], [
                'yayasan_id' => $sumber->yayasan_id,
                'unit_pendidikan_id' => $sumber->unit_pendidikan_id,
                'tahun_ajaran_id' => $sumber->tahun_ajaran_id,
                'semester_id' => $semesterGenap->id,
                'jenjang' => $sumber->jenjang,
                'tingkat' => $sumber->tingkat,
                'nama_kelas' => $sumber->nama_kelas,
                'wali_kelas_id' => $sumber->wali_kelas_id,
                'kapasitas' => $sumber->kapasitas,
                'ruangan' => $sumber->ruangan,
                'status' => 'Aktif',
            ]);
        });
    }

    private function buatJadwalSemester(
        AcademicYear $tahunAjaran,
        Semester $semester,
        Collection $kelas,
        Collection $guru,
        Collection $mataPelajaran,
    ): void {
        $slotJadwal = [
            ['hari' => 1, 'mulai' => '07:00:00', 'selesai' => '08:20:00'],
            ['hari' => 1, 'mulai' => '08:30:00', 'selesai' => '09:50:00'],
            ['hari' => 2, 'mulai' => '07:00:00', 'selesai' => '08:20:00'],
            ['hari' => 2, 'mulai' => '10:00:00', 'selesai' => '11:20:00'],
            ['hari' => 3, 'mulai' => '08:30:00', 'selesai' => '09:50:00'],
            ['hari' => 4, 'mulai' => '07:00:00', 'selesai' => '08:20:00'],
            ['hari' => 4, 'mulai' => '10:00:00', 'selesai' => '11:20:00'],
            ['hari' => 5, 'mulai' => '08:00:00', 'selesai' => '09:20:00'],
        ];

        foreach ($slotJadwal as $index => $slot) {
            $kelasJadwal = $kelas[$index % $kelas->count()];
            $guruJadwal = $guru[$index % $guru->count()];
            $mapelJadwal = $mataPelajaran[$index % $mataPelajaran->count()];

            ClassSchedule::query()->updateOrCreate([
                'academic_year_id' => $tahunAjaran->id,
                'semester_id' => $semester->id,
                'day_of_week' => $slot['hari'],
                'time_start' => $slot['mulai'],
            ], [
                // The acceptance fixture owns one deterministic schedule per
                // period/time slot. Do not key by mutable assignment IDs:
                // employee reseeding must update the fixture, not duplicate
                // schedules on every run.
                'kelas_id' => $kelasJadwal->id,
                'employee_id' => $guruJadwal->id,
                'subject_id' => $mapelJadwal->id,
                'class_id' => null,
                'teacher_id' => null,
                'time_end' => $slot['selesai'],
                'week_type' => 'all',
                'is_active' => true,
                'metadata' => [
                    'source' => 'JadwalPelajaranSeeder',
                    'semester' => $semester->sequence === 2 ? 'Genap' : 'Ganjil',
                    'dummy' => true,
                ],
            ]);
        }
    }
}
