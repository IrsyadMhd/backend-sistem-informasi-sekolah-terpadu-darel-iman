<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Position;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AccessScopeService
{
    private const GLOBAL_SCOPE_ROLES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
        'pengurus_yayasan', 'Pengurus Yayasan',
        'Sekretaris Yayasan', 'sekretaris_yayasan',
        'Bendahara Yayasan', 'bendahara_yayasan',
    ];

    /**
     * Role yang memiliki akses di atas Tata Usaha:
     * boleh melihat rekap kehadiran SELURUH pegawai unit.
     * TU dan di bawahnya hanya boleh melihat kehadiran milik diri sendiri.
     */
    public const ABOVE_TU_ROLES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
        'pengurus_yayasan', 'Pengurus Yayasan',
        'Sekretaris Yayasan', 'sekretaris_yayasan',
        'Bendahara Yayasan', 'bendahara_yayasan',
        'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
        'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan',
        'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus',
        'Kepala Sekolah', 'kepala_sekolah', 'kepala-sekolah', 'Kepsek', 'kepsek',
        'Wakil Kepala Sekolah', 'Waka Kurikulum', 'Wakil Kurikulum', 'waka_kurikulum',
        'Waka Kesiswaan', 'Wakil Kesiswaan', 'waka_kesiswaan',
    ];

    private const GLOBAL_ACCESS_MANAGER_ROLES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'pengurus_yayasan', 'Pengurus Yayasan', 'pengurus-yayasan',
    ];

    private const GLOBAL_ROLE_NAMES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
        'pengurus_yayasan', 'Pengurus Yayasan',
        'Sekretaris Yayasan', 'sekretaris_yayasan',
        'Bendahara Yayasan', 'bendahara_yayasan',
        'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
        'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan',
        'Divisi Kurikulum', 'Divisi Kesiswaan',
    ];

    private const GLOBAL_ACCESS_PERMISSIONS = [
        'sistem.hak_akses',
        'sistem.master_data',
        'sistem.pengaturan',
        'permission.manage',
        'role.manage',
        'employee.view_all',
        'employee.create',
        'employee.delete',
        'employee.import',
        'unit.view_all',
        'unit.create',
        'unit.update',
        'unit.delete',
        'master.create',
        'master.update',
        'master.delete',
    ];

    /**
     * Role yang boleh melihat seluruh cakupan data organisasi.
     */
    public function hasGlobalScope(User $user): bool
    {
        return $this->hasAnyRole($user, self::GLOBAL_SCOPE_ROLES);
    }

    /**
     * Apakah user memiliki role di atas Tata Usaha?
     * Digunakan sebagai security guard untuk endpoint presensi pegawai.
     */
    public function isAboveTu(User $user): bool
    {
        return $this->hasAnyRole($user, self::ABOVE_TU_ROLES);
    }

    private function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            try {
                if ($user->checkPermissionTo((string) $permission)) {
                    return true;
                }
            } catch (\Throwable) {
                // Abaikan bila tabel/record permission belum di-seed pada DB uji terisolasi.
            }
        }

        return false;
    }

    private function minRoleLevel(User $user): int
    {
        $level = $user->roles()->min('level');

        return $level !== null ? (int) $level : 99;
    }

    /**
     * Hanya role/permission tata kelola global yang boleh mengubah definisi akses
     * sistem secara menyeluruh. Diperiksa dinamis berbasis role DB & permission.
     */
    public function canManageGlobalAccess(User $user): bool
    {
        return $this->hasAnyRole($user, self::GLOBAL_ACCESS_MANAGER_ROLES)
            || $this->hasAnyPermission($user, ['sistem.hak_akses', 'permission.manage', 'role.manage'])
            || $this->minRoleLevel($user) === 1;
    }

    /**
     * Kewenangan mengelola assignment role/hak akses pegawai dalam cakupan unit.
     * Resolved dinamis dari permission `employee.role_access.manage` (atau global access),
     * bukan daftar role yang di-hardcode.
     */
    public function canManageUnitAccess(User $user): bool
    {
        return $this->canManageGlobalAccess($user)
            || $this->hasAnyPermission($user, ['employee.role_access.manage']);
    }

    /**
     * Kewenangan mengelola jabatan/posisi operasional pegawai per unit.
     */
    public function canManageUnitPositions(User $user): bool
    {
        return $this->canManageUnitAccess($user)
            || $this->hasAnyPermission($user, ['employee.position.manage', 'jabatan.manage', 'jabatan.view', 'employee.update']);
    }

    /**
     * Apakah user memiliki role/kapabilitas supervisor/pimpinan atau operator unit?
     */
    public function isSupervisor(User $user): bool
    {
        return $this->hasGlobalScope($user)
            || $this->isAboveTu($user)
            || $this->canManageAccess($user)
            || $this->hasAnyPermission($user, ['dashboard.tata-usaha.view', 'dashboard.operator.view', 'lms.manage', 'academic.manage'])
            || $this->minRoleLevel($user) <= 4;
    }

    /**
     * Apakah user adalah pengajar/pembimbing/wali kelas?
     */
    public function isTeacher(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran',
            'Wali Kelas', 'wali_kelas', 'wali-kelas',
            'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah',
            'Pembimbing', 'pembimbing',
        ]);
    }

    /**
     * Apakah user adalah staf sekolah, pimpinan, atau guru?
     */
    public function isStaffOrTeacher(User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->isTeacher($user)
            || $this->hasAnyRole($user, ['Operator', 'operator', 'Admin', 'admin']);
    }

    public function canManageAccess(User $user): bool
    {
        return $this->canManageGlobalAccess($user) || $this->canManageUnitAccess($user);
    }

    public function assertGlobalAccessManagement(User $user): void
    {
        abort_unless(
            $this->canManageGlobalAccess($user),
            403,
            'Hanya Super Admin, Admin, dan Pengurus Yayasan yang dapat mengubah akses global.'
        );
    }

    public function assertAccessManagement(User $user): void
    {
        abort_unless(
            $this->canManageAccess($user),
            403,
            'Akun tidak memiliki kewenangan untuk mengelola hak akses.'
        );
    }

    /**
     * Cegah pengelola unit memberikan role atau permission yang membuka
     * kewenangan global kepada pegawai.
     */
    public function assertRoleAssignmentAllowed(User $user, string $roleName, array $permissions = []): void
    {
        $this->assertAccessManagement($user);

        if ($this->hasAnyRoleName($roleName, ['Super Admin', 'Superadmin', 'super_admin', 'super-admin'])) {
            abort_unless(
                $this->hasAnyRole($user, ['Super Admin', 'Superadmin', 'super_admin', 'super-admin']),
                403,
                'Hanya Super Admin yang dapat menetapkan role Super Admin.'
            );
        }

        if ($this->canManageGlobalAccess($user)) {
            return;
        }

        abort_if(
            $this->hasAnyRoleName($roleName, self::GLOBAL_ROLE_NAMES),
            403,
            'Pengelola unit tidak dapat menetapkan role global.'
        );

        $globalPermission = collect($permissions)->first(
            fn ($permission) => in_array((string) $permission, self::GLOBAL_ACCESS_PERMISSIONS, true)
        );

        abort_if(
            $globalPermission !== null,
            403,
            'Pengelola unit tidak dapat menetapkan permission global.'
        );
    }

    /**
     * Query posisi yang terafiliasi dengan unit pengelola. Posisi global tidak
     * dipakai untuk assignment lokal agar tidak terjadi perubahan lintas unit.
     */
    public function accessiblePositions(User $user): Builder
    {
        if ($this->canManageGlobalAccess($user)) {
            return Position::query();
        }

        $unitIds = $this->accessibleEducationUnits($user)->pluck('id');

        if ($unitIds->isEmpty()) {
            return Position::query()->whereRaw('1 = 0');
        }

        return Position::query()->where(function ($query) use ($unitIds) {
            $query->whereIn('unit_sekolah_id', $unitIds)
                ->orWhere(function ($local) {
                    $local->whereNull('unit_sekolah_id')
                        ->where('satuan_kerja', 'Unit Pendidikan')
                        ->whereNotIn('scope_akses', ['semua_unit', 'bidang_pendidikan'])
                        ->where('level_jabatan', '>', 2);
                });
        });
    }

    public function assertPositionAssignment(User $user, ?string $positionId): void
    {
        if (! $positionId || $this->canManageGlobalAccess($user)) {
            return;
        }

        abort_unless(
            $this->canManageUnitPositions($user)
                && $this->accessiblePositions($user)->whereKey($positionId)->exists(),
            403,
            'Jabatan berada di luar cakupan unit akun.'
        );
    }

    /**
     * Mutation pegawai penuh bersifat global. Pengelola unit hanya boleh
     * mengubah assignment jabatan pada pegawai di unitnya sendiri.
     */
    public function assertEmployeeAssignment(User $user, Employee $employee, ?string $positionId = null): void
    {
        $this->assertAccessManagement($user);

        abort_unless(
            $this->accessibleEmployees($user)->whereKey($employee->id)->exists(),
            403,
            'Pegawai berada di luar cakupan unit akun.'
        );

        $this->assertPositionAssignment($user, $positionId);
    }

    public function assertEmployeePositionAssignment(User $user, Employee $employee, ?string $positionId = null): void
    {
        abort_unless(
            $this->canManageUnitPositions($user),
            403,
            'Akun tidak memiliki kewenangan untuk mengelola jabatan pegawai.'
        );

        abort_unless(
            $this->accessibleEmployees($user)->whereKey($employee->id)->exists(),
            403,
            'Pegawai berada di luar cakupan unit akun.'
        );

        $this->assertPositionAssignment($user, $positionId);
    }

    public function assertGlobalEmployeeMutation(User $user): void
    {
        $this->assertGlobalAccessManagement($user);
    }

    /**
     * Scope posisi master untuk operasi definisi jabatan. Pengelola unit hanya
     * dapat mengubah posisi yang benar-benar diberi unit pendidikan.
     */
    public function assertPositionDefinitionMutation(User $user, ?Position $position, array $payload = []): void
    {
        if ($this->canManageGlobalAccess($user)) {
            return;
        }

        abort_unless(
            $this->canManageUnitPositions($user),
            403,
            'Hanya pengelola unit yang dapat mengubah jabatan unit.'
        );

        $unitId = $payload['unit_sekolah_id'] ?? $position?->unit_sekolah_id;
        $level = (int) ($payload['level_jabatan'] ?? $position?->level_jabatan ?? 0);
        $scope = (string) ($payload['scope_akses'] ?? $position?->scope_akses ?? '');
        $satuanKerja = (string) ($payload['satuan_kerja'] ?? $position?->satuan_kerja ?? '');
        $roleId = $payload['role_sistem_id'] ?? $position?->role_sistem_id;

        abort_unless(
            $unitId && $this->accessibleEducationUnits($user)->whereKey($unitId)->exists(),
            403,
            'Jabatan harus terkait unit pendidikan dalam cakupan akun.'
        );

        abort_if(
            $level <= 2 || $satuanKerja !== 'Unit Pendidikan' || in_array($scope, ['semua_unit', 'bidang_pendidikan'], true),
            403,
            'Pengelola unit tidak dapat mengubah jabatan atau cakupan global.'
        );

        if ($roleId) {
            $roleName = Role::query()->whereKey($roleId)->value('name');
            abort_if(
                $roleName && $this->hasAnyRoleName($roleName, self::GLOBAL_ROLE_NAMES),
                403,
                'Pengelola unit tidak dapat mengaitkan jabatan dengan role global.'
            );
        }
    }
    /**
     * Scope query Unit Pendidikan yang berhak diakses user.
     */
    public function accessibleEducationUnits(User $user): Builder
    {
        if ($this->hasAnyRole($user, self::GLOBAL_SCOPE_ROLES)) {
            return EducationUnit::query();
        }

        if ($this->hasAnyRole($user, [
            'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan',
        ])) {
            return EducationUnit::query()->whereIn('id', $this->divisionAllowedEducationUnitIds($user));
        }

        $unitIds = collect();

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if ($employee) {
            if ($employee->unit_id) {
                $unitIds->push($employee->unit_id);
            }
            if (Schema::hasColumn('education_units', 'principal_id')) {
                $hasHeadmaster = Schema::hasColumn('education_units', 'headmaster_id');
                $managedUnitIds = EducationUnit::query()
                    ->where('principal_id', $employee->id)
                    ->when($hasHeadmaster, fn ($q) => $q->orWhere('headmaster_id', $employee->id))
                    ->pluck('id');
                $unitIds = $unitIds->merge($managedUnitIds);
            }
            $metadataManagedUnits = EducationUnit::query()
                ->where('metadata->principal_id', $employee->id)
                ->orWhere('metadata->headmaster_id', $employee->id)
                ->orWhere('metadata->kepala_sekolah_id', $employee->id)
                ->pluck('id');
            $unitIds = $unitIds->merge($metadataManagedUnits);

            // Unit dari rombel yang diajar lewat jadwal atau di-walikelasi (hanya untuk guru murni, bukan pimpinan/staf administrasi unit)
            if (! $this->hasAnyRole($user, [
                'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
                'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Waka Kurikulum', 'waka_kurikulum',
                'Wakil Kesiswaan', 'Waka Kesiswaan', 'waka_kesiswaan',
                'Tata Usaha', 'TU', 'tu', 'tata_usaha',
                'Operator', 'operator',
            ])) {
                $assignedClassUnitIds = Kelas::query()
                    ->where(function ($q) use ($employee, $user) {
                        $q->where('wali_kelas_id', $employee->id)
                          ->orWhereIn('id', ClassSchedule::query()
                                ->where('employee_id', $employee->id)
                                ->orWhere('teacher_id', $user->id)
                                ->select('kelas_id')
                                ->whereNotNull('kelas_id')
                          );
                    })
                    ->pluck('unit_pendidikan_id')
                    ->filter();
                $unitIds = $unitIds->merge($assignedClassUnitIds);
            }
        }

        $userUnitId = data_get($user->metadata, 'education_unit_id')
            ?? data_get($user->metadata, 'unit_id')
            ?? (property_exists($user, 'unit_id') ? $user->unit_id : null);
        if ($userUnitId) {
            $unitIds->push($userUnitId);
        }

        $unitIds = $unitIds->filter()->unique();

        // Khusus Guru Musyrif / Musyrifah: Hanya dapat melihat unit pendidikan bertipe boarding (pesantren/asrama), tidak bisa fullday.
        $isMusyrifOnly = $this->hasAnyRole($user, [
            'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah', 'Musyrif / Musyrifah',
        ]) && ! $this->hasAnyRole($user, array_merge(self::GLOBAL_SCOPE_ROLES, [
            'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
            'Tata Usaha', 'TU', 'tu', 'tata_usaha',
        ]));

        if ($isMusyrifOnly) {
            $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $boardingScope = function ($q) use ($likeOp) {
                $q->where(function ($sub) use ($likeOp) {
                    $sub->where('program_type', 'boarding')
                        ->orWhere('name', $likeOp, '%ponpes%')
                        ->orWhere('name', $likeOp, '%asrama%')
                        ->orWhere('name', $likeOp, '%pesantren%')
                        ->orWhere('name', $likeOp, "%ma'had%")
                        ->orWhere('name', $likeOp, '%mahad%')
                        ->orWhere('code', $likeOp, '%ponpes%')
                        ->orWhere('code', $likeOp, '%asrama%')
                        ->orWhere('code', $likeOp, '%mahad%');
                });
            };

            if ($unitIds->isNotEmpty()) {
                $assignedBoardingIds = EducationUnit::query()
                    ->whereIn('id', $unitIds)
                    ->where($boardingScope)
                    ->pluck('id');

                if ($assignedBoardingIds->isNotEmpty()) {
                    return EducationUnit::query()->whereIn('id', $assignedBoardingIds);
                }
            }

            return EducationUnit::query()->where($boardingScope);
        }

        if ($unitIds->isNotEmpty()) {
            return EducationUnit::query()->whereIn('id', $unitIds);
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        if ($student && ($student->unit_id || $student->education_unit_id)) {
            $unitId = $student->unit_id ?? $student->education_unit_id;
            return EducationUnit::query()->whereKey($unitId);
        }

        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            $parentUnitIds = Student::query()
                ->where(fn ($q) => $q->where('parent_id', $parent->id)->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id)))
                ->pluck('unit_id')
                ->filter()
                ->unique();

            return EducationUnit::query()->whereIn('id', $parentUnitIds);
        }

        return EducationUnit::query()->whereRaw('1 = 0');
    }

    /**
     * Scope query Rombel / Kelas yang berhak diakses user.
     */
    public function accessibleRombels(User $user): Builder
    {
        if ($this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan', 'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan', 'Kepala Sekolah', 'kepala_sekolah',
            'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Waka Kurikulum', 'waka_kurikulum',
            'Wakil Kesiswaan', 'Waka Kesiswaan', 'waka_kesiswaan',
            'Tata Usaha', 'TU', 'tata_usaha', 'Guru BK', 'guru_bk',
            'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah',
        ])) {
            $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
            return Kelas::query()->whereIn('unit_pendidikan_id', $unitIds);
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        $teacher = Teacher::query()
            ->where('user_id', $user->id)
            ->when($employee, fn ($q) => $q->orWhere('employee_id', $employee->id))
            ->first();

        if ($employee || $teacher) {
            // Rombel yang diajar atau di-walikelasi
            $teachingRombelIds = ClassSchedule::query()
                ->where(function ($q) use ($employee, $teacher) {
                    if ($employee && $teacher) {
                        $q->where('employee_id', $employee->id)->orWhere('teacher_id', $teacher->id);
                    } elseif ($teacher) {
                        $q->where('teacher_id', $teacher->id);
                    } elseif ($employee) {
                        $q->where('employee_id', $employee->id)->orWhere('teacher_id', $employee->id);
                    }
                })
                ->get()
                ->toBase()
                ->map(fn ($s) => $s->kelas_id ?? $s->class_id)
                ->filter()
                ->unique();

            $homeroomRombelIds = Kelas::query()
                ->where(function ($q) use ($employee, $teacher) {
                    if ($employee) {
                        $q->where('wali_kelas_id', $employee->id);
                    }
                    if ($teacher) {
                        $q->orWhere('wali_kelas_id', $teacher->id);
                    }
                })
                ->pluck('id');

            $allIds = $teachingRombelIds->merge($homeroomRombelIds)->unique();

            if ($allIds->isNotEmpty()) {
                return Kelas::query()->whereIn('id', $allIds);
            }

            // Fallback ke rombel di unit pegawai jika tidak ada pengajaran/walikelas spesifik
            if ($employee?->unit_id) {
                return Kelas::query()->where('unit_pendidikan_id', $employee->unit_id);
            }
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        if ($student && ($student->kelas_id || $student->class_id)) {
            $targetClassId = $student->kelas_id ?? $student->class_id;
            return Kelas::query()->whereKey($targetClassId);
        }

        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            $childRombelIds = Student::query()
                ->where(fn ($q) => $q->where('parent_id', $parent->id)->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id)))
                ->get()
                ->map(fn ($s) => $s->kelas_id ?? $s->class_id)
                ->filter()
                ->unique();

            return Kelas::query()->whereIn('id', $childRombelIds);
        }

        return Kelas::query()->whereRaw('1 = 0');
    }

    /**
     * Scope query Siswa yang berhak diakses user.
     */
    public function accessibleStudents(User $user): Builder
    {
        if ($this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan', 'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan', 'Kepala Sekolah', 'kepala_sekolah',
            'Wakil Kepala Sekolah', 'Wakil Kesiswaan', 'Waka Kesiswaan', 'waka_kesiswaan',
            'Tata Usaha', 'TU', 'tata_usaha',
            'Guru BK', 'guru_bk',
        ])) {
            $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
            return Student::query()->whereIn('unit_id', $unitIds);
        }

        if ($this->hasAnyRole($user, [
            'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah', 'Guru', 'guru',
        ])) {
            $rombelIds = $this->accessibleRombels($user)->pluck('id');
            return Student::query()->where(function ($query) use ($rombelIds) {
                $query->whereIn('kelas_id', $rombelIds)
                    ->orWhereIn('class_id', $rombelIds);
            });
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if ($employee) {
            $rombelIds = $this->accessibleRombels($user)->pluck('id');
            return Student::query()->where(function ($query) use ($rombelIds) {
                $query->whereIn('kelas_id', $rombelIds)->orWhereIn('class_id', $rombelIds);
            });
        }

        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            return Student::query()->where(function ($query) use ($parent) {
                $query->where('parent_id', $parent->id)
                    ->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id));
            });
        }

        return Student::query()->where('user_id', $user->id);
    }

    /**
     * Validate an explicit unit filter against the same scope used by data
     * queries. Gate/config endpoints must not trust a client-selected unit.
     */
    public function assertEducationUnitAccess(User $user, string $unitId): void
    {
        abort_unless(
            \Illuminate\Support\Str::isUuid($unitId) && $this->accessibleEducationUnits($user)->whereKey($unitId)->exists(),
            403,
            'Unit pendidikan tidak berada dalam cakupan akun.'
        );
    }

    /**
     * Scope query Pegawai & Guru yang berhak diakses user.
     */
    public function accessibleEmployees(User $user): Builder
    {
        if ($this->hasAnyRole($user, self::GLOBAL_SCOPE_ROLES)) {
            return Employee::query();
        }

        $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
        return Employee::query()->whereIn('unit_id', $unitIds);
    }

    /**
     * Scope query Jadwal Pelajaran yang berhak diakses user.
     */
    public function accessibleSchedules(User $user): Builder
    {
        if ($this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan', 'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan', 'Kepala Sekolah', 'kepala_sekolah',
            'Waka Kurikulum', 'waka_kurikulum', 'Tata Usaha', 'TU', 'tata_usaha',
        ])) {
            $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
            $rombelIds = Kelas::query()->whereIn('unit_pendidikan_id', $unitIds)->pluck('id');
            return ClassSchedule::query()->whereIn('kelas_id', $rombelIds);
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        $teacher = Teacher::query()
            ->where('user_id', $user->id)
            ->when($employee, fn ($q) => $q->orWhere('employee_id', $employee->id))
            ->first();

        if ($employee || $teacher) {
            return ClassSchedule::query()->where(function ($q) use ($employee, $teacher) {
                if ($employee && $teacher) {
                    $q->where('employee_id', $employee->id)->orWhere('teacher_id', $teacher->id);
                } elseif ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                } elseif ($employee) {
                    $q->where('employee_id', $employee->id)->orWhere('teacher_id', $employee->id);
                }
            });
        }

        $rombelIds = $this->accessibleRombels($user)->pluck('id');
        return ClassSchedule::query()->whereIn('kelas_id', $rombelIds);
    }

    /**
     * Ambil seluruh anak terhubung untuk Orang Tua.
     */
    public function accessibleChildren(User $user): Collection
    {
        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if (! $parent) {
            return collect([]);
        }

        return Student::query()
            ->with(['kelas', 'educationUnit'])
            ->where(function ($query) use ($parent) {
                $query->where('parent_id', $parent->id)
                    ->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id));
            })
            ->get();
    }

    /**
     * Scope Kelompok Binaan Tahfizh.
     */
    public function accessibleTahfizhGroups(User $user): Builder
    {
        return $this->accessibleEducationUnits($user);
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        $normalize = static fn (string $role): string => strtolower((string) preg_replace('/[\s_-]+/', '', $role));
        $actual = $user->getRoleNames()->map($normalize);

        return collect($roles)->map($normalize)->intersect($actual)->isNotEmpty();
    }

    private function hasAnyRoleName(string $actualRole, array $roles): bool
    {
        $normalize = static fn (string $role): string => strtolower((string) preg_replace('/[\s_-]+/', '', $role));

        return in_array($normalize($actualRole), collect($roles)->map($normalize)->all(), true);
    }

    /**
     * Resolve allowlist unit lintas-unit dari metadata akun yang dikelola server.
     * Nama `accessibile_unit_ids` dipertahankan sebagai alias kompatibilitas untuk
     * data lama yang terlanjur memakai ejaan tersebut. Bila allowlist belum ada,
     * unit pegawai menjadi fallback tunggal; akun tanpa keduanya harus fail closed.
     */
    private function divisionAllowedEducationUnitIds(User $user): Collection
    {
        $metadata = is_array($user->metadata) ? $user->metadata : [];
        $paths = [
            'allowed_unit_ids',
            'accessible_unit_ids',
            'accessibile_unit_ids',
            'data_scope.allowed_unit_ids',
            'data_scope.accessible_unit_ids',
            'data_scope.accessibile_unit_ids',
        ];

        foreach ($paths as $path) {
            $rawIds = data_get($metadata, $path);
            if ($rawIds === null) {
                continue;
            }

            $ids = collect(is_array($rawIds) ? $rawIds : [$rawIds])
                ->filter(fn ($id) => is_string($id) || is_int($id))
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                return $ids;
            }
        }

        $employeeUnitId = Employee::query()
            ->where('user_id', $user->id)
            ->value('unit_id');

        // Jika metadata akun secara eksplisit menetapkan data_scope lintas-unit,
        // atau akun bidang pendidikan belum terikat pada satu unit pegawai spesifik
        // dan memiliki izin report.cross_unit.view, kembalikan unit aktif.
        $dataScope = data_get($metadata, 'data_scope');
        if (
            in_array($dataScope, ['cross_unit_read', 'cross_unit', 'semua_unit'], true)
            || (! $employeeUnitId && $this->hasAnyPermission($user, ['report.cross_unit.view']))
        ) {
            return EducationUnit::query()->where('is_active', true)->pluck('id');
        }

        return collect([$employeeUnitId])->filter()->values();
    }
}
