<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsPenugasanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsPenugasanService
{
    public function __construct(
        protected LmsPenugasanRepositoryInterface $penugasanRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->penugasanRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsPenugasan
    {
        return $this->penugasanRepository->findById($id);
    }

    public function dapatkanBerdasarkanModulAjar(string $modulAjarId): Collection
    {
        return $this->penugasanRepository->getByModulAjarId($modulAjarId);
    }

    public function simpan(array $data): LmsPenugasan
    {
        // Support field alias mappings
        if (isset($data['judul']) && ! isset($data['judul_tugas'])) {
            $data['judul_tugas'] = $data['judul'];
        }
        if (isset($data['tipe']) && ! isset($data['tipe_tugas'])) {
            $data['tipe_tugas'] = $data['tipe'];
        }
        if (isset($data['tanggal_selesai']) && ! isset($data['deadline'])) {
            $data['deadline'] = $data['tanggal_selesai'];
        }
        if (isset($data['lampiran']) && ! isset($data['file_lampiran'])) {
            $data['file_lampiran'] = $data['lampiran'];
        }
        if (isset($data['status'])) {
            $statusVal = strtolower((string) $data['status']);
            $data['is_published'] = in_array($statusVal, ['dipublikasikan', 'published', '1', 'true', 'active'], true);
        }

        return $this->penugasanRepository->create($data);
    }

    public function ubah(string $id, array $data): ?LmsPenugasan
    {
        if (isset($data['judul']) && ! isset($data['judul_tugas'])) {
            $data['judul_tugas'] = $data['judul'];
        }
        if (isset($data['tipe']) && ! isset($data['tipe_tugas'])) {
            $data['tipe_tugas'] = $data['tipe'];
        }
        if (isset($data['tanggal_selesai']) && ! isset($data['deadline'])) {
            $data['deadline'] = $data['tanggal_selesai'];
        }
        if (isset($data['lampiran']) && ! isset($data['file_lampiran'])) {
            $data['file_lampiran'] = $data['lampiran'];
        }
        if (isset($data['status'])) {
            $statusVal = strtolower((string) $data['status']);
            $data['is_published'] = in_array($statusVal, ['dipublikasikan', 'published', '1', 'true', 'active'], true);
        }

        return $this->penugasanRepository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        return $this->penugasanRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->penugasanRepository->restore($id);
    }

    public function togglePublish(string $id): ?LmsPenugasan
    {
        return $this->penugasanRepository->togglePublish($id);
    }

    public function submitOrGrade(string $penugasanId, array $data): LmsPengumpulanTugas
    {
        return $this->penugasanRepository->submitOrGrade($penugasanId, $data);
    }

    public function dapatkanStatistik(array $filters = []): array
    {
        return $this->penugasanRepository->getStats($filters);
    }

    public function dapatkanOpsi(?\App\Models\User $user = null): array
    {
        $accessScope = app(\App\Services\AccessScopeService::class);
        $hasGlobal = $user ? $accessScope->hasGlobalScope($user) : false;
        $unitIds = ($user && ! $hasGlobal) ? $accessScope->accessibleEducationUnits($user)->pluck('id')->all() : [];
        $rombelIds = ($user && ! $hasGlobal) ? $accessScope->accessibleRombels($user)->pluck('id')->all() : [];

        $modulQuery = LmsModulAjar::query();
        if (! empty($unitIds)) {
            $modulQuery->whereIn('unit_pendidikan_id', $unitIds);
        }
        $modulOptions = $modulQuery->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->kode_modul ? "[{$item->kode_modul}] ".($item->judul_modul ?? $item->judul ?? 'Modul Ajar') : ($item->judul_modul ?? $item->judul ?? 'Modul Ajar'),
                'judul' => $item->judul_modul ?? $item->judul ?? 'Modul Ajar',
                'judul_modul' => $item->judul_modul ?? $item->judul ?? 'Modul Ajar',
            ])
            ->values()
            ->toArray();

        $kelasQuery = Kelas::query();
        if (! empty($rombelIds)) {
            $kelasQuery->whereIn('id', $rombelIds);
        } elseif (! empty($unitIds)) {
            $kelasQuery->whereIn('unit_pendidikan_id', $unitIds);
        }
        $kelasOptions = $kelasQuery->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->nama_kelas ?? $item->name ?? 'Kelas',
                'nama_kelas' => $item->nama_kelas ?? $item->name ?? 'Kelas',
            ])
            ->values()
            ->toArray();

        if (empty($kelasOptions) && class_exists(ClassRoom::class)) {
            $classRoomQuery = ClassRoom::query();
            if (! empty($unitIds)) {
                $classRoomQuery->whereIn('unit_pendidikan_id', $unitIds);
            }
            $kelasOptions = $classRoomQuery->get()
                ->map(fn ($item) => [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->code ?? 'Kelas',
                    'nama_kelas' => $item->name ?? 'Kelas',
                ])
                ->values()
                ->toArray();
        }

        $guruQuery = Employee::query();
        if (! empty($unitIds)) {
            $guruQuery->whereIn('unit_id', $unitIds);
        }
        $guruOptions = $guruQuery->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->nama_lengkap ?? $item->nama_panggilan ?? $item->name ?? 'Guru',
                'nama' => $item->nama_lengkap ?? $item->nama_panggilan ?? $item->name ?? 'Guru',
                'nama_lengkap' => $item->nama_lengkap ?? $item->nama_panggilan ?? $item->name ?? 'Guru',
            ])
            ->values()
            ->toArray();

        if (empty($guruOptions) && class_exists(Teacher::class)) {
            $guruOptions = Teacher::get()
                ->map(fn ($item) => [
                    'value' => $item->id,
                    'label' => $item->full_name ?? $item->name ?? 'Guru',
                    'nama' => $item->full_name ?? $item->name ?? 'Guru',
                    'nama_lengkap' => $item->full_name ?? $item->name ?? 'Guru',
                ])
                ->values()
                ->toArray();
        }

        $subjectQuery = Subject::query();
        if (! empty($unitIds)) {
            $subjectQuery->whereIn('unit_pendidikan_id', $unitIds);
        }
        $subjectOptions = $subjectQuery->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->nama_mapel ?? $item->name ?? 'Mata Pelajaran',
            ])
            ->values()
            ->toArray();

        $semesterOptions = Semester::get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->name ?? 'Semester',
            ])
            ->values()
            ->toArray();

        $tahunAjaranOptions = AcademicYear::get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->name ?? 'Tahun Ajaran',
            ])
            ->values()
            ->toArray();

        return [
            'modul_ajar' => $modulOptions,
            'kelas' => $kelasOptions,
            'guru' => $guruOptions,
            'subjects' => $subjectOptions,
            'semesters' => $semesterOptions,
            'tahun_ajaran' => $tahunAjaranOptions,
            'tipe' => [
                ['value' => 'individu', 'label' => 'Individu'],
                ['value' => 'kelompok', 'label' => 'Kelompok'],
            ],
            'jenis' => [
                ['value' => 'tugas', 'label' => 'Tugas Mandiri / PR'],
                ['value' => 'proyek', 'label' => 'Proyek / Portofolio'],
                ['value' => 'quiz', 'label' => 'Kuis Formatif'],
                ['value' => 'latihan', 'label' => 'Latihan Soal'],
            ],
            'status' => [
                ['value' => 'dipublikasikan', 'label' => 'Dipublikasikan'],
                ['value' => 'draft', 'label' => 'Draft'],
            ],
        ];
    }
}
