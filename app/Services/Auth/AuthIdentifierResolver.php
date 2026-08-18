<?php

namespace App\Services\Auth;

use App\Models\Employee;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolver identifier autentikasi multi-identifier berbasis PostgreSQL.
 *
 * Satu-satunya tempat yang menerjemahkan identifier login (email / No. HP /
 * NIY / NIK / NIS / NISN) menjadi User + profile aktor. Tidak ada mapping
 * identifier di controller maupun hardcode akun.
 *
 * Kontrak:
 *   Identifier → normalize → deteksi source → resolve User → resolve profile
 *
 * Return di setiap method selalu berisi key:
 *   - <actor>   : model profile atau null
 *   - user      : User atau null
 *   - matched_by: label source yang cocok atau null
 */
class AuthIdentifierResolver
{
    /* =====================================================================
     * PUBLIC API
     * ===================================================================== */

    /**
     * Resolve user portal admin (Super Admin / Admin) via email atau No. HP.
     */
    public function resolveAdminUser(string $identifier): ?User
    {
        $input = $this->normalize($identifier);

        $email = strtolower($input);
        if (str_contains($email, '@')) {
            return User::query()->where('email', $email)->first();
        }

        return $this->userByPhoneVariants($input);
    }

    /**
     * Resolve pegawai/guru dari NIY / No. HP / Email / NIK (employees),
     * dengan fallback ke teachers.employee_number / phone / email.
     *
     * @return array{employee: ?Employee, teacher: ?Teacher, user: ?User, matched_by: ?string}
     */
    public function resolveEmployee(string $identifier): array
    {
        $input = $this->normalize($identifier);

        $employee = Employee::query()
            ->where(function (Builder $q) use ($input) {
                $q->where('niy', $input)
                    ->orWhere('niy', strtoupper($input))
                    ->orWhere('nik', $input)
                    ->orWhere('email', strtolower($input))
                    ->orWhereIn('no_hp', PhoneNormalizer::variants($input));
            })
            ->orderBy('created_at')
            ->first();

        if ($employee) {
            return [
                'employee' => $employee,
                'teacher' => null,
                'user' => $employee->user_id ? User::query()->find($employee->user_id) : null,
                'matched_by' => 'employees',
            ];
        }

        $teacher = Teacher::query()
            ->where(function (Builder $q) use ($input) {
                $q->where('employee_number', $input)
                    ->orWhere('employee_number', strtoupper($input))
                    ->orWhere('email', strtolower($input))
                    ->orWhereIn('phone', PhoneNormalizer::variants($input));
            })
            ->orderBy('created_at')
            ->first();

        if ($teacher) {
            return [
                'employee' => null,
                'teacher' => $teacher,
                'user' => $teacher->user_id ? User::query()->find($teacher->user_id) : null,
                'matched_by' => 'teachers',
            ];
        }

        return [
            'employee' => null,
            'teacher' => null,
            'user' => null,
            'matched_by' => null,
        ];
    }

    /**
     * Resolve orang tua dari No. HP / NIK / NIK Ayah / NIK Ibu / Email /
     * NIS salah satu anak. Urutan deterministic:
     * phone → nik → father_nik → mother_nik → email → child NIS/NISN.
     *
     * @return array{parent: ?ParentModel, user: ?User, child: ?Student, matched_by: ?string, child_matched: bool}
     */
    public function resolveParent(string $identifier): array
    {
        $input = $this->normalize($identifier);

        $lookups = [
            'phone' => fn (Builder $q) => $q->whereIn('phone', PhoneNormalizer::variants($input)),
            'nik' => fn (Builder $q) => $q->where('nik', $input),
            'father_nik' => fn (Builder $q) => $q->where('father_nik', $input),
            'mother_nik' => fn (Builder $q) => $q->where('mother_nik', $input),
            'email' => fn (Builder $q) => $q->where('email', strtolower($input)),
        ];

        foreach ($lookups as $source => $closure) {
            $parent = ParentModel::query()
                ->where($closure)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();

            if ($parent) {
                return $this->parentResult($parent, $source, null);
            }
        }

        // Fallback terakhir: NIS / NISN salah satu anak → resolve household.
        $student = Student::query()
            ->where(function (Builder $query) use ($input) {
                $query->where('nis', $input)
                    ->orWhere('nisn', $input);
            })
            ->where('is_active', true)
            ->first();

        if ($student) {
            $parent = $this->firstParentOfStudent($student);
            if ($parent) {
                return $this->parentResult($parent, 'child_nis', $student);
            }

            return [
                'parent' => null,
                'user' => null,
                'child' => $student,
                'matched_by' => null,
                'child_matched' => true,
            ];
        }

        return [
            'parent' => null,
            'user' => null,
            'child' => null,
            'matched_by' => null,
            'child_matched' => false,
        ];
    }

    /**
     * Resolve siswa dari NIS / NISN / email / No. HP akun terkait.
     * Scope selalu self: hanya profile siswa milik user tersebut.
     *
     * @return array{student: ?Student, user: ?User, matched_by: ?string}
     */
    public function resolveStudent(string $identifier): array
    {
        $input = $this->normalize($identifier);

        $student = Student::query()
            ->where(function (Builder $q) use ($input) {
                $q->where('nis', $input)
                    ->orWhere('nisn', $input);
            })
            ->first();

        if ($student) {
            return [
                'student' => $student,
                'user' => $student->user_id ? User::query()->find($student->user_id) : null,
                'matched_by' => 'students',
            ];
        }

        // Backward compat: identifier berupa email/HP akun siswa.
        $user = str_contains($input, '@')
            ? User::query()->where('email', strtolower($input))->first()
            : $this->userByPhoneVariants($input);

        if ($user) {
            $byUser = Student::query()->where('user_id', $user->id)->first();
            if ($byUser) {
                return [
                    'student' => $byUser,
                    'user' => $user,
                    'matched_by' => 'users',
                ];
            }
        }

        return [
            'student' => null,
            'user' => null,
            'matched_by' => null,
        ];
    }

    /**
     * Seluruh anak terhubung ke sebuah parent (parent_id kolom + pivot).
     * Digunakan untuk response login portal orang tua & child switcher.
     */
    public function childrenForParent(ParentModel $parent): Collection
    {
        return Student::query()
            ->where(function (Builder $q) use ($parent) {
                $q->where('parent_id', $parent->id)
                    ->orWhereHas('parentsPivot', fn (Builder $p) => $p->whereKey($parent->id));
            })
            ->where('is_active', true)
            ->orderBy('nis')
            ->get();
    }

    /* =====================================================================
     * INTERNAL HELPERS
     * ===================================================================== */

    private function normalize(string $identifier): string
    {
        return trim($identifier);
    }

    private function userByPhoneVariants(string $input): ?User
    {
        if (! PhoneNormalizer::isLikelyPhone($input)) {
            return null;
        }

        return User::query()
            ->whereIn('phone', PhoneNormalizer::variants($input))
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Parent primer dari seorang siswa: relasi parentsPivot (is_primary dulu),
     * fallback ke kolom students.parent_id.
     */
    private function firstParentOfStudent(Student $student): ?ParentModel
    {
        $pivotParent = $student->parentsPivot()
            ->orderByDesc('student_parents.is_primary')
            ->orderBy('student_parents.created_at')
            ->first();

        if ($pivotParent) {
            return $pivotParent;
        }

        return $student->parent_id
            ? ParentModel::query()->whereKey($student->parent_id)->first()
            : null;
    }

    private function parentResult(?ParentModel $parent, string $source, ?Student $child): array
    {
        return [
            'parent' => $parent,
            'user' => $parent?->user_id ? User::query()->find($parent->user_id) : null,
            'child' => $child,
            'matched_by' => $source,
            'child_matched' => $source === 'child_nis',
        ];
    }
}
