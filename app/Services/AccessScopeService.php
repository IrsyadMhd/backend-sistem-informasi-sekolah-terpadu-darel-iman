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

    private const GLOBAL_ACCESS_MANAGER_ROLES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'pengurus_yayasan', 'Pengurus Yayasan', 'pengurus-yayasan',
    ];

    private const UNIT_ACCESS_MANAGER_ROLES = [
        'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
        'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan', 'kepala-bidang-pendidikan',
        'Kepala Sekolah', 'kepala_sekolah', 'kepala-sekolah', 'Kepsek', 'kepsek',
    ];

    private const GLOBAL_ROLE_NAMES = [
        'Super Admin', 'Superadmin', 'super_admin', 'super-admin',
        'Admin', 'admin',
        'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
        'pengurus_yayasan', 'Pengurus Yayasan',
        'Sekretaris Yayasan', 'sekretaris_yayasan',
        'Bendahara Yayasan', 'bendahara_yayasan',
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
     * Hanya role ini yang boleh mengubah definisi akses global dan seluruh
     * data pegawai/unit.
     */
    public function canManageGlobalAccess(User $user): bool
    {
        return $this->hasAnyRole($user, self::GLOBAL_ACCESS_MANAGER_ROLES);
    }

    /**
     * Pengelola unit boleh mengatur assignment lokal, tetapi tidak definisi
     * role/permission global.
     */
    public function canManageUnitAccess(User $user): bool
    {
        return $this->hasAnyRole($user, self::UNIT_ACCESS_MANAGER_ROLES);
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
            $this->canManageUnitAccess($user)
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
            $this->canManageUnitAccess($user),
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
        }

        $userUnitId = data_get($user->metadata, 'education_unit_id')
            ?? data_get($user->metadata, 'unit_id')
            ?? (property_exists($user, 'unit_id') ? $user->unit_id : null);
        if ($userUnitId) {
            $unitIds->push($userUnitId);
        }

        $unitIds = $unitIds->filter()->unique();
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
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan', 'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan', 'Kepala Sekolah', 'kepala_sekolah',
            'Waka Kurikulum', 'waka_kurikulum', 'Waka Kesiswaan', 'waka_kesiswaan',
            'Tata Usaha', 'TU', 'tata_usaha', 'Guru BK', 'guru_bk',
            'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah', 'Guru', 'guru',
        ])) {
            $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
            return Kelas::query()->whereIn('unit_pendidikan_id', $unitIds);
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if ($employee) {
            // Rombel yang diajar atau di-walikelasi
            $teachingRombelIds = ClassSchedule::query()
                ->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere('teacher_id', $employee->id))
                ->pluck('kelas_id')
                ->filter();
            $homeroomRombelIds = Kelas::query()->where('wali_kelas_id', $employee->id)->pluck('id');
            $allIds = $teachingRombelIds->merge($homeroomRombelIds)->unique();

            if ($allIds->isNotEmpty()) {
                return Kelas::query()->whereIn('id', $allIds);
            }

            // Fallback ke rombel di unit pegawai jika tidak ada pengajaran/walikelas spesifik
            if ($employee->unit_id) {
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
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan', 'Kepala Bidang Pendidikan',
            'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
            'Divisi Program Khusus', 'divisi_pendidikan', 'Kepala Sekolah', 'kepala_sekolah',
            'Waka Kesiswaan', 'waka_kesiswaan', 'Tata Usaha', 'TU', 'tata_usaha',
            'Guru BK', 'guru_bk',
            'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah', 'Guru', 'guru',
        ])) {
            $unitIds = $this->accessibleEducationUnits($user)->pluck('id');
            return Student::query()->whereIn('unit_id', $unitIds);
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if ($employee) {
            $rombelIds = $this->accessibleRombels($user)->pluck('id');
            return Student::query()->whereIn('kelas_id', $rombelIds);
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
            $this->accessibleEducationUnits($user)->whereKey($unitId)->exists(),
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
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
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
        if ($employee) {
            return ClassSchedule::query()->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere('teacher_id', $employee->id));
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

        return collect([$employeeUnitId])->filter()->values();
    }
}
