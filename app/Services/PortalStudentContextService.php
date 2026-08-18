<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class PortalStudentContextService
{
    /**
     * Dapatkan siswa terkait berdasarkan user login dan pilihan anak (jika orang tua).
     */
    public function getStudentContext(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // Pilihan anak aktif melalui header atau query parameter
        $selectedChildId = $request->header('X-Child-Id')
            ?? $request->query('child_id')
            ?? $request->input('child_id');

        if ($selectedChildId) {
            $parent = ParentModel::query()->where('user_id', $user->id)->first();
            if ($parent) {
                return $this->parentStudentsQuery($parent)
                    ->with(['kelas', 'educationUnit'])
                    ->whereKey($selectedChildId)
                    ->where('is_active', true)
                    ->first();
            }

            return Student::query()
                ->with(['kelas', 'educationUnit'])
                ->where('user_id', $user->id)
                ->whereKey($selectedChildId)
                ->first();
        }

        // Cek relasi langsung siswa
        $student = Student::query()
            ->with(['kelas', 'educationUnit'])
            ->where('user_id', $user->id)
            ->first();

        if ($student) {
            return $student;
        }

        // Fallback untuk akun Super Admin / Admin agar dapat mengecek data portal
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return Student::query()
                ->with(['kelas', 'educationUnit'])
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * Dapatkan daftar seluruh anak yang terhubung dengan akun orang tua.
     */
    public function getChildrenForUser(User $user)
    {
        $parent = ParentModel::query()->where('user_id', $user->id)->first();

        if (! $parent) {
            $student = Student::query()
                ->with(['kelas', 'educationUnit'])
                ->where('user_id', $user->id)
                ->first();

            if ($student) {
                return collect([$student]);
            }

            if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
                $fallback = Student::query()->with(['kelas', 'educationUnit'])->where('is_active', true)->get();

                return $fallback;
            }

            return collect([]);
        }

        return $this->parentStudentsQuery($parent)
            ->with(['kelas', 'educationUnit'])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Ambil data konteks akademik aktif (Tahun Ajaran & Semester).
     */
    public function getAcademicContext(): array
    {
        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first();
        $activeSemester = Semester::query()->where('is_active', true)->first();

        return [
            'academic_year' => $activeAcademicYear?->name ?? '2025/2026',
            'academic_year_id' => $activeAcademicYear?->id,
            'semester' => $activeSemester?->name ?? 'Ganjil',
            'semester_id' => $activeSemester?->id,
            'date' => now()->translatedFormat('l, d F Y'),
        ];
    }

    /**
     * Query pembantu relasi orang tua ke anak (langsung & pivot).
     */
    public function parentStudentsQuery(ParentModel $parent)
    {
        return Student::query()->where(function ($query) use ($parent) {
            $query->where('parent_id', $parent->id)
                ->orWhereHas('parentsPivot', fn ($pivot) => $pivot->whereKey($parent->id));
        });
    }
}
