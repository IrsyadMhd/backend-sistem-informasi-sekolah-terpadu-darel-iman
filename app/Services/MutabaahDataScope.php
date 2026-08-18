<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class MutabaahDataScope
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function applyEnterprise(EloquentBuilder $query, string $resource, User $user): EloquentBuilder
    {
        if ($this->isFoundationWide($user) || in_array($resource, ['categories', 'agendas'], true)) {
            return $query;
        }
        $unitIds = $this->accessibleUnitIds($user);
        abort_if($unitIds === [], 403, 'Akun belum terhubung dengan unit pendidikan.');

        return $query->whereIn('education_unit_id', $unitIds);
    }

    public function assertEnterpriseModel(User $user, Model $model): void
    {
        if ($this->isFoundationWide($user) || ! in_array('education_unit_id', $model->getFillable(), true)) {
            return;
        }
        abort_unless(
            in_array($model->getAttribute('education_unit_id'), $this->accessibleUnitIds($user), true),
            403,
            'Data berada di luar unit kewenangan Anda.'
        );
    }

    public function applyHeaders(Builder $query, User $user): Builder
    {
        if ($this->isFoundationWide($user)) {
            return $query;
        }
        if ($this->isUnitManagementRole($user)) {
            $unitIds = $this->accessibleUnitIds($user);
            abort_if($unitIds === [], 403, 'Akun belum terhubung dengan unit pendidikan.');

            return $query->whereIn('h.education_unit_id', $unitIds);
        }

        $employee = Employee::where('user_id', $user->id)->first(['id', 'unit_id']);
        abort_unless($employee, 403, 'Akun belum terhubung dengan data pegawai.');

        return $query->where('sa.employee_id', $employee->id);
    }

    public function applyStudents(EloquentBuilder $query, User $user, string $date): EloquentBuilder
    {
        if ($this->isFoundationWide($user)) {
            return $query;
        }
        if ($this->isUnitManagementRole($user)) {
            $unitIds = $this->accessibleUnitIds($user);
            abort_if($unitIds === [], 403, 'Akun belum terhubung dengan unit pendidikan.');

            return $query->where(function ($student) use ($unitIds) {
                $student->whereIn('unit_id', $unitIds)
                    ->orWhereHas('kelas', fn ($kelas) => $kelas->whereIn('unit_pendidikan_id', $unitIds));
            });
        }

        $employee = Employee::where('user_id', $user->id)->first(['id', 'unit_id']);
        abort_unless($employee, 403, 'Akun belum terhubung dengan data pegawai.');

        $assignments = MutabaahSupervisorAssignment::active()->byDate($date)->where('employee_id', $employee->id)->get();

        return $query->where(function ($scope) use ($assignments) {
            foreach ($assignments as $assignment) {
                $scope->orWhere(function ($student) use ($assignment) {
                    $student->where(function ($unit) use ($assignment) {
                        $unit->where('unit_id', $assignment->education_unit_id)
                            ->orWhereHas('kelas', fn ($kelas) => $kelas->where('unit_pendidikan_id', $assignment->education_unit_id));
                    })
                        ->when($assignment->kelas_id, fn ($q, $id) => $q->where('kelas_id', $id))
                        ->when($assignment->rombel_id, fn ($q, $id) => $q->where('kelas_id', $id))
                        ->when($assignment->mentoring_group, fn ($q, $group) => $q->where('metadata->mentoring_group', $group))
                        ->when($assignment->dormitory_id, fn ($q, $id) => $q->where('metadata->dormitory_id', $id))
                        ->when($assignment->room_id, fn ($q, $id) => $q->where('metadata->room_id', $id));
                });
            }
            if ($assignments->isEmpty()) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    public function employeeUnitId(User $user): ?string
    {
        return Employee::where('user_id', $user->id)->value('unit_id');
    }

    public function isFoundationWide(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'Super Admin', 'super_admin', 'super-admin', 'Superadmin',
            'Ketua Yayasan', 'Yayasan', 'Pengurus Yayasan', 'pengurus_yayasan',
            'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
        ]);
    }

    private function isUnitManagementRole(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
            'Tata Usaha', 'TU', 'tata_usaha', 'Operator', 'operator', 'Admin',
            'Waka Kesiswaan', 'waka_kesiswaan', 'Waka Kurikulum', 'waka_kurikulum',
            'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
            'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus',
        ]);
    }

    /** @return array<int, string> */
    private function accessibleUnitIds(User $user): array
    {
        return $this->accessScope->accessibleEducationUnits($user)
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        $normalize = static fn (string $role): string => strtolower((string) preg_replace('/[\s_-]+/', '', $role));
        $actual = $user->getRoleNames()->map($normalize);

        return collect($roles)->map($normalize)->intersect($actual)->isNotEmpty();
    }
}
