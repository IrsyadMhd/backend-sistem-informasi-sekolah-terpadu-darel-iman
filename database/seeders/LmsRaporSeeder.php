<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Services\LmsRaporService;
use Illuminate\Database\Seeder;

class LmsRaporSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $semester = $academicYear
            ? Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->orderByDesc('is_active')
                ->orderBy('sequence')
                ->first()
            : null;
        $kelas = Kelas::query()->orderBy('id')->first();

        if (! $semester || ! $academicYear || ! $kelas) {
            return;
        }

        $students = Student::query()->orderBy('id')->take(5)->get();
        if ($students->isEmpty()) {
            return;
        }

        $wali = Employee::query()->orderBy('id')->first();

        /** @var LmsRaporService $service */
        $service = app(LmsRaporService::class);

        foreach ($students as $index => $siswa) {
            $rapor = $service->generateStudent(
                $siswa->id,
                $semester->id,
                $academicYear->id,
                $kelas?->id
            );

            $statusOptions = ['diterbitkan', 'final', 'draft', 'diterbitkan', 'final'];
            $rapor->update([
                'guru_wali_id' => $wali?->id,
                'peringkat_kelas' => $index + 1,
                'total_siswa_kelas' => count($students),
                'catatan_wali_kelas' => 'Pertahankan prestasi belajar, selalu rajin dan disiplin beribadah serta meningkatkan pemahaman konsep pelajaran.',
                'catatan_kepala_sekolah' => 'Selamat atas hasil pencapaian pembelajaran semester ini. Terus berkarya dan menjadi teladan.',
                'status_rapor' => $statusOptions[$index % count($statusOptions)],
                'tanggal_terbit' => now()->toDateString(),
                'sudah_dilihat_ortu' => $index % 2 === 0,
            ]);
        }
    }
}
