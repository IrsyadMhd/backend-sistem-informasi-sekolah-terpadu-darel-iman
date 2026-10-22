<?php

namespace App\Services;

use App\Models\LmsPresensi;
use App\Repositories\Contracts\LmsPresensiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsPresensiService
{
    public function __construct(
        protected LmsPresensiRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'tanggal', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsPresensi
    {
        return $this->repository->findById($id);
    }

    public function simpan(array $data): LmsPresensi
    {
        $presensi = $this->repository->create($data);
        $this->broadcastPresensi($presensi);
        return $presensi;
    }

    public function ubah(string $id, array $data): ?LmsPresensi
    {
        $presensi = $this->repository->update($id, $data);
        if ($presensi) {
            $this->broadcastPresensi($presensi);
        }
        return $presensi;
    }

    public function hapus(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->repository->restore($id);
    }

    public function simpanBulk(string $jadwalPelajaranId, string $tanggal, int $pertemuanKe, array $presensiItems): Collection
    {
        $results = $this->repository->bulkUpsert($jadwalPelajaranId, $tanggal, $pertemuanKe, $presensiItems);
        foreach ($results as $lp) {
            $this->broadcastPresensi($lp);
        }
        return $results;
    }

    protected function broadcastPresensi(LmsPresensi $lp): void
    {
        try {
            $lp->loadMissing(['jadwalPelajaran.subject', 'jadwalPelajaran.teacher', 'jadwalPelajaran.employee', 'jadwalPelajaran.kelas', 'siswa']);
            $studentId = $lp->siswa_id;
            if (! $studentId) return;

            $sched = $lp->jadwalPelajaran;
            $subjectName = $sched?->subject?->name ?? $sched?->subject?->nama ?? 'Pelajaran';
            $teacherName = $sched?->teacher?->full_name ?? $sched?->teacher?->name ?? $sched?->employee?->name ?? 'Guru Pengajar';

            $rawStat = strtolower($lp->status_hadir ?? $lp->status ?? 'hadir');
            $bLabel = 'Hadir';
            $bType = 'green';
            if (in_array($rawStat, ['i', 'izin'])) {
                $bLabel = 'Izin';
                $bType = 'amber';
            } elseif (in_array($rawStat, ['s', 'sakit'])) {
                $bLabel = 'Sakit';
                $bType = 'blue';
            } elseif (in_array($rawStat, ['t', 'terlambat'])) {
                $bLabel = 'Terlambat';
                $bType = 'amber';
            } elseif (in_array($rawStat, ['a', 'alpa', 'tidak_hadir'])) {
                $bLabel = 'Alpa';
                $bType = 'red';
            }

            $nowWib = \Carbon\Carbon::now()->timezone('Asia/Jakarta');
            $recTime = $lp->arrival_time 
                ? substr((string) $lp->arrival_time, 0, 5) 
                : ($sched?->start_time 
                    ? substr((string) $sched->start_time, 0, 5) 
                    : ($sched?->jam_mulai ? substr((string) $sched->jam_mulai, 0, 5) : $nowWib->format('H:i')));

            $dateStr = $lp->tanggal ? \Carbon\Carbon::parse($lp->tanggal)->format('Y-m-d') : $nowWib->format('Y-m-d');
            $rawTime = $dateStr . ' ' . $recTime . ':00';

            app(RealtimeBroadcastService::class)->broadcastStudentActivity((string) $studentId, [
                'id' => 'lesson-presensi-' . $lp->id,
                'student_id' => (string) $studentId,
                'raw_time' => $rawTime,
                'type' => 'mapel',
                'title' => 'Absensi kelas ' . $subjectName,
                'subtitle' => 'Guru: ' . $teacherName,
                'date_label' => 'Hari ini',
                'time_label' => $recTime,
                'is_past' => false,
                'badge_label' => $bLabel,
                'badge_type' => $bType,
                'icon' => 'account-check',
                'icon_bg' => '#ECFDF5',
                'icon_color' => '#10B981',
                'screen' => 'Absensi',
                'verifier_role' => 'Guru Pengampu ' . $subjectName,
                'verifier_name' => $teacherName,
                'keterangan' => $lp->keterangan ?? $lp->notes ?? null,
            ]);
        } catch (\Throwable) {}
    }

    public function dapatkanStatistik(array $filters = []): array
    {
        return $this->repository->getStats($filters);
    }

    public function dapatkanOpsi(?string $employeeId = null): array
    {
        return $this->repository->getOptions($employeeId);
    }
}
