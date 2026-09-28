<?php

namespace App\Policies;

use App\Models\ParentModel;
use App\Models\Student;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Support\RoleName;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class StudentPolicy
 * Menegakkan boundary otorisasi dan proteksi IDOR pada entitas Siswa (Student).
 */
class StudentPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    /**
     * Global bypass untuk Super Admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (RoleName::userHasAny($user, ['Super Admin'])) {
            return true;
        }

        return null;
    }

    /**
     * Tentukan apakah pengguna dapat melihat daftar siswa.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('student.view_all')
            || $user->can('student.view')
            || $user->can('student.view_unit')
            || $user->can('foundation.student.view')
            || $user->can('report.cross_unit.view')
            || $user->can('parent.portal.view')
            || $user->can('student.portal.view')
            || $this->accessScope->hasGlobalScope($user);
    }

    /**
     * Tentukan apakah pengguna dapat melihat record siswa spesifik.
     * Mencegah IDOR: Siswa hanya melihat diri sendiri, Orang Tua hanya melihat anak sendiri,
     * Guru/Pegawai hanya melihat siswa di unit/kelas yang diampunya.
     */
    public function view(User $user, Student $student): bool
    {
        // 1. Siswa hanya dapat melihat profil akunnya sendiri
        if ($user->can('student.portal.view') && ! $user->can('parent.portal.view')) {
            return (string) $user->id === (string) $student->user_id;
        }

        // 2. Orang Tua hanya dapat melihat data anak kandung/perwalian yang terdaftar
        if ($user->can('parent.portal.view') || RoleName::userHasAny($user, ['Orang Tua'])) {
            return $this->accessChild($user, $student);
        }

        // 3. Pegawai/Pimpinan Yayasan dengan global scope
        if ($this->accessScope->hasGlobalScope($user) || $user->can('student.view_all')) {
            return true;
        }

        // 4. Pegawai unit/sekolah dibatasi oleh accessibleStudents query
        return $this->accessScope->accessibleStudents($user)
            ->where('students.id', $student->id)
            ->exists();
    }

    /**
     * Otorisasi khusus relasi Orang Tua -> Anak (Anti-IDOR).
     */
    public function accessChild(User $user, Student $student): bool
    {
        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if (! $parent) {
            return false;
        }

        // Cek direct foreign key pada students.parent_id
        if ((string) $student->parent_id === (string) $parent->id) {
            return true;
        }

        // Cek many-to-many pivot table student_parents
        return $student->parentsPivot()
            ->where('parents.id', $parent->id)
            ->exists();
    }

    /**
     * Tentukan apakah pengguna dapat membuat data siswa baru.
     */
    public function create(User $user): bool
    {
        return $user->can('student.create')
            || $user->can('master.create');
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data siswa.
     */
    public function update(User $user, Student $student): bool
    {
        if ($user->can('student.update') || $user->can('master.edit')) {
            // Jika bukan global admin, pastikan siswa berada di unit yang diampu
            if (! $this->accessScope->hasGlobalScope($user)) {
                return $this->accessScope->accessibleStudents($user)
                    ->where('students.id', $student->id)
                    ->exists();
            }

            return true;
        }

        return false;
    }

    /**
     * Tentukan apakah pengguna dapat menghapus data siswa.
     */
    public function delete(User $user, Student $student): bool
    {
        if ($user->can('student.delete') || $user->can('master.delete')) {
            if (! $this->accessScope->hasGlobalScope($user)) {
                return $this->accessScope->accessibleStudents($user)
                    ->where('students.id', $student->id)
                    ->exists();
            }

            return true;
        }

        return false;
    }
}
