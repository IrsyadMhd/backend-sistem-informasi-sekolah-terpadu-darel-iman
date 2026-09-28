<?php

namespace App\Repositories\Eloquent;

use App\Models\EducationUnit;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class StudentRepository implements StudentRepositoryInterface
{
    public function paginate(
        string $search = '',
        int $perPage = 15,
        ?string $unitId = null,
        bool $canAccessAllUnits = false,
        ?string $kelasId = null,
        ?string $status = null
    ): LengthAwarePaginator
    {
        return Student::query()
            ->with(['kelas:id,nama_kelas,tingkat,unit_pendidikan_id', 'educationUnit:id,name'])
            ->when(! $canAccessAllUnits, function ($query) use ($unitId) {
                $query->when(
                    $unitId,
                    function ($studentQuery) use ($unitId) {
                        if (Str::isUuid($unitId)) {
                            $studentQuery->where('unit_id', $unitId);
                        } else {
                            $resolvedId = EducationUnit::where('name', $unitId)
                                ->orWhere('code', $unitId)
                                ->value('id');
                            if ($resolvedId) {
                                $studentQuery->where('unit_id', $resolvedId);
                            } else {
                                $studentQuery->whereHas('educationUnit', function ($u) use ($unitId) {
                                    $u->where('name', 'ilike', "%{$unitId}%")
                                        ->orWhere('code', 'ilike', "%{$unitId}%");
                                });
                            }
                        }
                    },
                    fn ($studentQuery) => $studentQuery->whereRaw('1 = 0')
                );
            })
            ->when($kelasId, function ($query) use ($kelasId) {
                if (Str::isUuid($kelasId)) {
                    $query->where(function ($q) use ($kelasId) {
                        $q->where('kelas_id', $kelasId)
                            ->orWhere('class_id', $kelasId);
                    });
                } else {
                    $query->whereHas('kelas', function ($q) use ($kelasId) {
                        $q->where('nama_kelas', 'ilike', "%{$kelasId}%");
                    });
                }
            })
            ->when($status, function ($query) use ($status) {
                $st = strtolower(trim($status));
                if ($st === 'aktif') {
                    $query->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('metadata->status_siswa')
                                ->orWhereRaw("LOWER(metadata->>'status_siswa') = 'aktif'");
                        });
                } elseif ($st === 'mutasi') {
                    $query->where(function ($q) {
                        $q->whereRaw("LOWER(metadata->>'status_siswa') = 'mutasi'")
                            ->orWhereNotNull('metadata->mutasi_type')
                            ->orWhereNotNull('metadata->nomor_mutasi');
                    });
                } elseif ($st === 'lulus' || $st === 'alumni') {
                    $query->where(function ($q) {
                        $q->whereRaw("LOWER(metadata->>'status_siswa') in ('lulus', 'alumni')")
                            ->orWhere('metadata->is_alumni', true);
                    });
                } elseif ($st === 'nonaktif') {
                    $query->where('is_active', false)
                        ->where(function ($q) {
                            $q->whereNull('metadata->status_siswa')
                                ->orWhereRaw("LOWER(metadata->>'status_siswa') not in ('mutasi', 'lulus', 'alumni')");
                        });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $term = "%{$search}%";
                $query->where(function ($q) use ($term) {
                    $q->where('nis', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('nisn', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage);
    }
}
