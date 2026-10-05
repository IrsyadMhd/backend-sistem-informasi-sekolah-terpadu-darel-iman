<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\Subject;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsBankSoalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LmsBankSoalService
{
    public function __construct(
        protected LmsBankSoalRepositoryInterface $bankSoalRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->bankSoalRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsBankSoal
    {
        return $this->bankSoalRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsBankSoal
    {
        if (empty($data['kode_soal'])) {
            $data['kode_soal'] = 'SOAL-'.strtoupper(Str::random(6));
        }

        // Auto-assign mata_pelajaran_id from Kisi-kisi if not set
        if (empty($data['mata_pelajaran_id']) && ! empty($data['kisi_kisi_id'])) {
            $kisi = LmsKisiKisi::find($data['kisi_kisi_id']);
            if ($kisi) {
                $data['mata_pelajaran_id'] = $kisi->mata_pelajaran_id;
            }
        }

        if (! isset($data['status'])) {
            $data['status'] = true;
        }

        if (! isset($data['poin'])) {
            $data['poin'] = 1.0;
        }

        if (! isset($data['tingkat_kesulitan'])) {
            $data['tingkat_kesulitan'] = 'sedang';
        }

        $soal = $this->bankSoalRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Butir Soal Baru di Bank Soal', [
            'soal_id' => $soal->id,
            'kode_soal' => $soal->kode_soal,
            'kisi_kisi_id' => $soal->kisi_kisi_id,
            'tipe_soal' => $soal->tipe_soal,
            'user_id' => auth()->id(),
        ]);

        return $soal;
    }

    public function ubah(string $id, array $data): ?LmsBankSoal
    {
        $existing = $this->bankSoalRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if (empty($data['mata_pelajaran_id']) && ! empty($data['kisi_kisi_id'])) {
            $kisi = LmsKisiKisi::find($data['kisi_kisi_id']);
            if ($kisi) {
                $data['mata_pelajaran_id'] = $kisi->mata_pelajaran_id;
            }
        }

        $updated = $this->bankSoalRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Butir Soal Bank Soal', [
            'soal_id' => $id,
            'kode_soal' => $existing->kode_soal,
            'tipe_soal' => $updated->tipe_soal ?? $existing->tipe_soal,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $soal = $this->bankSoalRepository->findById($id);
        if (! $soal) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Butir Soal Bank Soal (Soft Delete)', [
            'soal_id' => $id,
            'kode_soal' => $soal->kode_soal,
            'user_id' => auth()->id(),
        ]);

        return $this->bankSoalRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Butir Soal Bank Soal', [
            'soal_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->bankSoalRepository->restore($id);
    }

    public function duplikasi(string $id): ?LmsBankSoal
    {
        $duplicated = $this->bankSoalRepository->duplicate($id);

        if ($duplicated) {
            Log::info('[AUDIT LOG] Menduplikasi Butir Soal Bank Soal', [
                'soal_id_asal' => $id,
                'soal_id_baru' => $duplicated->id,
                'user_id' => auth()->id(),
            ]);
        }

        return $duplicated;
    }

    public function statistik(array $filters = []): array
    {
        return $this->bankSoalRepository->getStats($filters);
    }

    public function opsi(?string $unitId = null): array
    {
        $user = Auth::user();
        $accessScope = app(\App\Services\AccessScopeService::class);
        $hasGlobal = $user ? $accessScope->hasGlobalScope($user) : false;

        if ($user && ! $hasGlobal && empty($unitId)) {
            $unitId = $accessScope->accessibleEducationUnits($user)->value('id');
        }

        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;
        $teacher = $user ? (Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null)) : null;
        $isTeacherScope = ($user && ! $hasGlobal && ($employee || $teacher) && ! $user->hasAnyRole(['Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'tu', 'Waka Kurikulum', 'waka_kurikulum']));

        $teacherIds = array_values(array_filter(array_unique([
            $employee?->id,
            $teacher?->id,
        ])));

        $subjectQuery = Subject::where(function ($q) {
            $q->where('status', true)->orWhereNull('status');
        });

        $kelasQuery = Kelas::select('id', 'nama_kelas', 'tingkat', 'unit_pendidikan_id');

        $kisiKisiQuery = LmsKisiKisi::with(['subject:id,name,unit_pendidikan_id', 'kelas:id,nama_kelas,unit_pendidikan_id'])
            ->where('status', true)
            ->orderBy('judul_kisi', 'asc');

        if ($isTeacherScope) {
            $employeeId = $employee?->id;
            $teacherId = $teacher?->id;

            // 1. Filter Mata Pelajaran: Hanya mapel yang diampu oleh guru bersangkutan
            $scheduleSubjectIds = ClassSchedule::query()
                ->where(function ($q) use ($employeeId, $teacherId) {
                    if ($employeeId && $teacherId) {
                        $q->where('employee_id', $employeeId)->orWhere('teacher_id', $teacherId);
                    } elseif ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    } elseif ($employeeId) {
                        $q->where('employee_id', $employeeId)->orWhere('teacher_id', $employeeId);
                    }
                })
                ->pluck('subject_id')
                ->filter()
                ->unique();

            $assignedSubjectIds = Subject::query()
                ->where(function ($q) use ($employeeId, $teacherId) {
                    if ($employeeId) {
                        $q->where('guru_pengampu_id', $employeeId)
                            ->orWhereHas('teachers', fn ($tq) => $tq->where('guru_id', $employeeId));
                    }
                    if ($teacherId) {
                        $q->orWhereHas('teachers', fn ($tq) => $tq->where('guru_id', $teacherId));
                    }
                })
                ->pluck('id');

            $allTeacherSubjectIds = $scheduleSubjectIds->merge($assignedSubjectIds)->unique()->values();

            if ($allTeacherSubjectIds->isNotEmpty()) {
                $subjectQuery->whereIn('id', $allTeacherSubjectIds);
            } elseif ($employee?->unit_id) {
                $subjectQuery->where('unit_pendidikan_id', $employee->unit_id);
            }

            // 2. Filter Kelas/Rombel: Hanya kelas yang diajar atau di-walikelasi
            $scheduleClassIds = ClassSchedule::query()
                ->where(function ($q) use ($employeeId, $teacherId) {
                    if ($employeeId && $teacherId) {
                        $q->where('employee_id', $employeeId)->orWhere('teacher_id', $teacherId);
                    } elseif ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    } elseif ($employeeId) {
                        $q->where('employee_id', $employeeId)->orWhere('teacher_id', $employeeId);
                    }
                })
                ->get()
                ->toBase()
                ->map(fn ($s) => $s->kelas_id ?? $s->class_id)
                ->filter()
                ->unique();

            $waliClassIds = Kelas::query()
                ->where(function ($q) use ($employeeId, $teacherId) {
                    if ($employeeId) {
                        $q->where('wali_kelas_id', $employeeId);
                    }
                    if ($teacherId) {
                        $q->orWhere('wali_kelas_id', $teacherId);
                    }
                })
                ->pluck('id');

            $allTeacherClassIds = $scheduleClassIds->merge($waliClassIds)->unique()->values();

            if ($allTeacherClassIds->isNotEmpty()) {
                $kelasQuery->whereIn('id', $allTeacherClassIds);
            } elseif ($employee?->unit_id) {
                $kelasQuery->where('unit_pendidikan_id', $employee->unit_id);
            }

            // 3. Filter Kisi-Kisi: Hanya kisi-kisi guru bersangkutan atau yang ia buat
            $kisiKisiQuery->where(function ($q) use ($teacherIds, $user) {
                if (! empty($teacherIds)) {
                    $q->whereIn('guru_id', $teacherIds)->orWhere('created_by', $user->id);
                } else {
                    $q->where('created_by', $user->id);
                }
            });
        } else {
            if ($unitId) {
                $subjectQuery->where('unit_pendidikan_id', $unitId);
                $kelasQuery->where('unit_pendidikan_id', $unitId);
                $kisiKisiQuery->whereHas('subject', fn ($sq) => $sq->where('unit_pendidikan_id', $unitId))
                    ->orWhereHas('kelas', fn ($kq) => $kq->where('unit_pendidikan_id', $unitId));
            }
        }

        $kisiKisiList = $kisiKisiQuery->get(['id', 'judul_kisi', 'jenis_ujian', 'mata_pelajaran_id', 'kelas_id', 'jumlah_soal', 'alokasi_waktu_menit'])
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'judul_kisi' => $k->judul_kisi,
                    'jenis_ujian' => $k->jenis_ujian,
                    'mata_pelajaran_id' => $k->mata_pelajaran_id,
                    'unit_pendidikan_id' => $k->subject->unit_pendidikan_id ?? $k->kelas->unit_pendidikan_id ?? null,
                    'subject_name' => $k->subject->name ?? '',
                    'kelas_id' => $k->kelas_id,
                    'kelas_name' => $k->kelas->nama_kelas ?? '',
                    'jumlah_soal' => (int) ($k->jumlah_soal ?? 0),
                    'alokasi_waktu_menit' => (int) ($k->alokasi_waktu_menit ?? 0),
                ];
            });

        $subjects = $subjectQuery
            ->orderBy('nama_mapel')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $name = $item->nama_mapel ?? $item->name ?? 'Mata Pelajaran';
                $code = $item->kode_mapel ?? $item->code ?? '';

                return [
                    'id' => $item->id,
                    'name' => $name,
                    'nama' => $name,
                    'nama_mapel' => $name,
                    'code' => $code,
                    'kode' => $code,
                    'kode_mapel' => $code,
                    'unit_pendidikan_id' => $item->unit_pendidikan_id,
                    'label' => $code ? "{$code} - {$name}" : $name,
                ];
            })
            ->values();

        $kelasList = $kelasQuery->orderBy('nama_kelas')->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'nama_kelas' => $k->nama_kelas,
                'name' => $k->nama_kelas,
                'tingkat' => $k->tingkat,
                'unit_pendidikan_id' => $k->unit_pendidikan_id,
            ])
            ->values();

        $tipeSoalList = [
            ['value' => 'pg', 'label' => 'Pilihan Ganda'],
            ['value' => 'esai', 'label' => 'Essay / Esai'],
            ['value' => 'benar_salah', 'label' => 'Benar / Salah'],
            ['value' => 'menjodohkan', 'label' => 'Menjodohkan'],
        ];

        $tingkatKesulitanList = [
            ['value' => 'mudah', 'label' => 'Mudah'],
            ['value' => 'sedang', 'label' => 'Sedang'],
            ['value' => 'sulit', 'label' => 'Sulit'],
        ];

        return [
            'kisi_kisi' => $kisiKisiList,
            'subjects' => $subjects,
            'kelas' => $kelasList,
            'tipe_soal' => $tipeSoalList,
            'tingkat_kesulitan' => $tingkatKesulitanList,
        ];
    }
}
