<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AccessScopeService
{
    /**
     * Scope query Unit Pendidikan yang berhak diakses user.
     */
    public function accessibleEducationUnits(User $user): Builder
    {
        if ($this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
        ])) {
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

        return EducationUnit::query();
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

            return Kelas::query()->whereIn('id', $allIds);
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        if ($student && $student->kelas_id) {
            return Kelas::query()->whereKey($student->kelas_id);
        }

        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            $childRombelIds = Student::query()
                ->where(fn ($q) => $q->where('parent_id', $parent->id)->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id)))
                ->pluck('kelas_id')
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
        if ($this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'pengurus_yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
        ])) {
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
