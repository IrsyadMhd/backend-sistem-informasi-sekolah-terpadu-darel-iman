<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassSchedule;
use App\Models\LmsPresensi;
use App\Models\Student;
use App\Repositories\Contracts\LmsPresensiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LmsPresensiRepository implements LmsPresensiRepositoryInterface
{
    public function getPaginated(array $filters = [], int $perPage = 15, string $orderBy = 'tanggal', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsPresensi::query()
            ->with([
                'jadwalPelajaran.subject',
                'jadwalPelajaran.kelas',
                'jadwalPelajaran.employee',
                'siswa',
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', function ($sq) use ($search) {
                    $sq->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('nis', 'ilike', "%{$search}%")
                        ->orWhere('nisn', 'ilike', "%{$search}%");
                })->orWhereHas('jadwalPelajaran.subject', function ($sq) use ($search) {
                    $sq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                })->orWhere('keterangan', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['jadwal_pelajaran_id'])) {
            $query->where('jadwal_pelajaran_id', $filters['jadwal_pelajaran_id']);
        }

        if (! empty($filters['siswa_id'])) {
            $query->where('siswa_id', $filters['siswa_id']);
        }

        if (! empty($filters['status_hadir'])) {
            $query->where('status_hadir', strtolower($filters['status_hadir']));
        }

        if (! empty($filters['tanggal'])) {
            $query->whereDate('tanggal', $filters['tanggal']);
        }

        if (! empty($filters['tanggal_mulai']) && ! empty($filters['tanggal_selesai'])) {
            $query->whereBetween('tanggal', [$filters['tanggal_mulai'], $filters['tanggal_selesai']]);
        }

        if (! empty($filters['kelas_id'])) {
            $query->whereHas('jadwalPelajaran', function ($q) use ($filters) {
                $q->where('kelas_id', $filters['kelas_id']);
            });
        }

        if (! empty($filters['subject_id'])) {
            $query->whereHas('jadwalPelajaran', function ($q) use ($filters) {
                $q->where('subject_id', $filters['subject_id']);
            });
        }

        if (! empty($filters['employee_id'])) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('employee_id', $filters['employee_id']));
        }

        if (! empty($filters['class_ids'])) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->whereIn('kelas_id', $filters['class_ids']));
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id): ?LmsPresensi
    {
        return LmsPresensi::query()
            ->with([
                'jadwalPelajaran.subject',
                'jadwalPelajaran.kelas',
                'jadwalPelajaran.employee',
                'siswa',
            ])
            ->find($id);
    }

    public function create(array $data): LmsPresensi
    {
        if (empty($data['waktu_presensi'])) {
            $data['waktu_presensi'] = now();
        }

        return LmsPresensi::create($data);
    }

    public function update(string $id, array $data): ?LmsPresensi
    {
        $presensi = LmsPresensi::find($id);
        if (! $presensi) {
            return null;
        }

        $presensi->update($data);

        return $presensi->fresh([
            'jadwalPelajaran.subject',
            'jadwalPelajaran.kelas',
            'jadwalPelajaran.employee',
            'siswa',
        ]);
    }

    public function delete(string $id): bool
    {
        $presensi = LmsPresensi::find($id);
        if (! $presensi) {
            return false;
        }

        return (bool) $presensi->delete();
    }

    public function restore(string $id): bool
    {
        $presensi = LmsPresensi::withTrashed()->find($id);
        if (! $presensi) {
            return false;
        }

        return (bool) $presensi->restore();
    }

    public function bulkUpsert(string $jadwalPelajaranId, string $tanggal, int $pertemuanKe, array $presensiItems): Collection
    {
        $results = new Collection;

        DB::transaction(function () use ($jadwalPelajaranId, $tanggal, $pertemuanKe, $presensiItems, &$results) {
            foreach ($presensiItems as $item) {
                $presensi = LmsPresensi::updateOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwalPelajaranId,
                        'siswa_id' => $item['siswa_id'],
                        'tanggal' => $tanggal,
                    ],
                    [
                        'status_hadir' => strtolower($item['status_hadir'] ?? 'hadir'),
                        'keterangan' => $item['keterangan'] ?? null,
                        'pertemuan_ke' => $pertemuanKe,
                        'waktu_presensi' => now(),
                    ]
                );

                $results->push($presensi);
            }
        });

        return $results->load(['jadwalPelajaran.subject', 'jadwalPelajaran.kelas', 'siswa']);
    }

    public function getStats(array $filters = []): array
    {
        $query = LmsPresensi::query();

        if (! empty($filters['jadwal_pelajaran_id'])) {
            $query->where('jadwal_pelajaran_id', $filters['jadwal_pelajaran_id']);
        }

        if (! empty($filters['tanggal'])) {
            $query->whereDate('tanggal', $filters['tanggal']);
        }

        $total = (clone $query)->count();
        $hadir = (clone $query)->where('status_hadir', 'hadir')->count();
        $izin = (clone $query)->where('status_hadir', 'izin')->count();
        $sakit = (clone $query)->where('status_hadir', 'sakit')->count();
        $alpa = (clone $query)->where('status_hadir', 'alpa')->count();
        $terlambat = (clone $query)->where('status_hadir', 'terlambat')->count();

        $persentaseHadir = $total > 0 ? round((($hadir + $terlambat) / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'terlambat' => $terlambat,
            'persentase_hadir' => $persentaseHadir,
        ];
    }

    public function getOptions(?string $employeeId = null): array
    {
        $query = ClassSchedule::query()
            ->with([
                'subject:id,name,code',
                'kelas:id,nama_kelas,kode_kelas',
                'schoolClass:id,nama_kelas,name',
                'employee:id,full_name,nip',
                'teacher:id,full_name,name',
            ])
            ->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('is_active', 1)
                    ->orWhereNull('is_active');
            });

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $schedules = $query->get()
            ->map(function ($s) {
                $subjectName = $s->subject->name ?? $s->subject->nama ?? 'Mata Pelajaran';
                $kelasName = $s->kelas->nama_kelas ?? $s->schoolClass->nama_kelas ?? $s->schoolClass->name ?? 'Kelas';
                $teacherName = $s->employee->full_name ?? $s->teacher->full_name ?? $s->teacher->name ?? 'Guru';
                $dayName = $s->nama_hari ?? ($s->day_of_week ? "Hari {$s->day_of_week}" : '');
                $timeStart = $s->time_start ? substr($s->time_start, 0, 5) : '';
                $timeEnd = $s->time_end ? substr($s->time_end, 0, 5) : '';
                $timeStr = ($timeStart && $timeEnd) ? " ({$timeStart}-{$timeEnd})" : '';

                $label = sprintf(
                    '%s | %s | %s%s',
                    $dayName ?: 'Jadwal',
                    $subjectName,
                    $kelasName,
                    $timeStr
                );

                return [
                    'id' => $s->id,
                    'label' => $label,
                    'nama_jadwal' => $label,
                    'name' => $label,
                    'kelas_id' => $s->kelas_id ?? $s->class_id,
                    'class_id' => $s->class_id ?? $s->kelas_id,
                    'subject_id' => $s->subject_id,
                    'teacher_name' => $teacherName,
                    'day_name' => $dayName,
                ];
            });

        $studentsQuery = Student::query()
            ->select('id', 'full_name', 'nis', 'nisn', 'class_id', 'kelas_id')
            ->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('is_active', 1)
                    ->orWhereNull('is_active');
            });

        if ($employeeId && $schedules->isNotEmpty()) {
            $classIds = $schedules->map(fn ($s) => $s['kelas_id'] ?? $s['class_id'])->filter()->unique()->values()->all();
            if (! empty($classIds)) {
                $studentsQuery->where(function ($q) use ($classIds) {
                    $q->whereIn('class_id', $classIds)->orWhereIn('kelas_id', $classIds);
                });
            }
        }

        $students = $studentsQuery->orderBy('full_name')->get();

        return [
            'schedules' => $schedules,
            'students' => $students,
            'statuses' => [
                ['value' => 'hadir', 'label' => 'Hadir', 'color' => 'emerald'],
                ['value' => 'izin', 'label' => 'Izin', 'color' => 'indigo'],
                ['value' => 'sakit', 'label' => 'Sakit', 'color' => 'sky'],
                ['value' => 'alpa', 'label' => 'Alpa', 'color' => 'rose'],
                ['value' => 'terlambat', 'label' => 'Terlambat', 'color' => 'amber'],
            ],
        ];
    }
}
