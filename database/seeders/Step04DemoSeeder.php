<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\QrCredential;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class Step04DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $user = User::query()->where('email', 'guru@school-erp.local')->first();
        $employee = $user ? Employee::query()->where('user_id', $user->id)->first() : null;
        $unit = $employee?->unit;
        $academicYear = AcademicYear::query()
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderByDesc('is_active')
            ->first();
        $semester = $academicYear
            ? Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderByDesc('is_active')
                ->first()
            : null;
        $subject = Subject::query()
            ->where(fn ($query) => $query->where('status', true)->orWhereNull('status'))
            ->orderBy('id')
            ->first();
        $kelas = $unit && $academicYear && $semester
            ? Kelas::query()
                ->where('unit_pendidikan_id', $unit->id)
                ->where('tahun_ajaran_id', $academicYear->id)
                ->where('semester_id', $semester->id)
                ->where('status', 'Aktif')
                ->orderBy('id')
                ->first()
            : null;

        if (! $user || ! $employee || ! $unit || ! $academicYear || ! $semester || ! $subject || ! $kelas) {
            $this->command?->warn('Step04DemoSeeder dilewati: graph guru, unit, periode, mapel, atau rombel belum lengkap.');

            return;
        }

        $now = now();
        $start = $now->copy()->subMinutes(5)->format('H:i:00');
        $end = $now->copy()->addMinutes(30)->format('H:i:00');
        $schedule = ClassSchedule::query()->firstOrNew([
            'employee_id' => $employee->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'day_of_week' => $now->dayOfWeekIso,
            'metadata->source' => 'Step04DemoSeeder',
        ]);
        $schedule->fill([
            'kelas_id' => $kelas->id,
            'class_id' => null,
            'teacher_id' => null,
            'time_start' => $start,
            'time_end' => $end,
            'week_type' => 'all',
            'is_active' => true,
            'metadata' => [
                'source' => 'Step04DemoSeeder',
                'purpose' => 'teacher_qr_teaching_attendance_demo',
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $schedule->save();

        $activeCredential = QrCredential::query()
            ->where('employee_id', $employee->id)
            ->where('card_type', 'employee_card')
            ->active()
            ->first();

        if (! $activeCredential) {
            $rawToken = (string) env('STEP04_DEMO_QR_TOKEN', Str::uuid());
            $activeCredential = QrCredential::query()->create([
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'card_type' => 'employee_card',
                'token_hash' => hash('sha256', $rawToken),
                'card_version' => 'v1',
                'status' => 'active',
                'issued_at' => $now,
                'metadata' => ['source' => 'Step04DemoSeeder'],
            ]);

            $this->command?->info('STEP04_DEMO_QR_TOKEN='.$rawToken);
        }

        $this->command?->info(sprintf(
            'Step04DemoSeeder siap: guru=%s schedule=%s qr=%s',
            $employee->nama_lengkap,
            $schedule->id,
            $activeCredential->id,
        ));
    }
}
