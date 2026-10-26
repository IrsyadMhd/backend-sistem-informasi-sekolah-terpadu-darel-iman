<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use App\Services\LmsRaporService;
use Illuminate\Database\Seeder;

class LmsRaporSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) return;
        $actor = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['Operator', 'Tata Usaha', 'Super Admin']))->orderBy('id')->first();
        if (! $actor) return;
        $service = app(LmsRaporService::class);
        $targetStudentId = env('DEMO_GRADE_STUDENT_ID');
        Student::query()->with(['kelas.unitPendidikan'])->where('is_active', true)->whereNotNull('kelas_id')
            ->when($targetStudentId, fn ($q) => $q->whereKey($targetStudentId))
            ->when(! $targetStudentId, fn ($q) => $q->where(function ($query) {
                $query->where('metadata->is_demo', true)
                    ->orWhere('metadata->fixture', 'canonical_role_login');
            }))->orderBy('id')
            ->chunkById(100, function ($students) use ($actor, $service) {
                foreach ($students as $student) {
                    $kelas = $student->kelas;
                    if (! $kelas?->tahun_ajaran_id) continue;

                    $semesters = \App\Models\Semester::where('academic_year_id', $kelas->tahun_ajaran_id)->get();
                    if ($semesters->isEmpty() && $kelas->semester_id) {
                        $semesters = \App\Models\Semester::whereKey($kelas->semester_id)->get();
                    }

                    foreach ($semesters as $sem) {
                        $rapor = $service->generateStudent($student->id, $sem->id, $kelas->tahun_ajaran_id, $kelas->id);
                        if ($rapor->total_mapel < 1) continue;
                        $rapor->update([
                            'guru_wali_id' => $kelas->wali_kelas_id,
                            'status_rapor' => 'diterbitkan',
                            'tanggal_terbit' => now()->toDateString(),
                            'created_by' => $actor->id,
                            'updated_by' => $actor->id,
                            'catatan_wali_kelas' => 'Pertahankan kebiasaan belajar yang baik dan tingkatkan kompetensi yang belum optimal.',
                        ]);
                    }
                }
            });
    }
}
