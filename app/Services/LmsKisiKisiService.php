<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsKisiKisi;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TujuanPembelajaran;
use App\Repositories\Contracts\LmsKisiKisiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LmsKisiKisiService
{
    public function __construct(
        protected LmsKisiKisiRepositoryInterface $kisiKisiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->kisiKisiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsKisiKisi
    {
        return $this->kisiKisiRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsKisiKisi
    {
        if (! isset($data['status'])) {
            $data['status'] = true;
        }

        if (empty($data['distribusi_bobot'])) {
            $data['distribusi_bobot'] = [
                'pg' => 60,
                'isian' => 20,
                'esai' => 20,
            ];
        }

        $user = Auth::user();
        $adminRoles = ['superadmin', 'yayasan', 'ketuayayasan', 'pengurusyayasan', 'sekretarisyayasan', 'bendaharayayasan', 'kepalasekolah', 'tatausaha', 'tu', 'divisipendidikan'];
        $isAdmin = false;
        if ($user) {
            $userRoles = $user->getRoleNames()->map(fn ($r) => strtolower((string) preg_replace('/[\s_-]+/', '', $r)));
            $isAdmin = $userRoles->intersect($adminRoles)->isNotEmpty();
        }

        if ($user && ! $isAdmin) {
            $employee = Employee::where('user_id', $user->id)->first();
            $teacher = Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null);
            if ($employee || $teacher) {
                $data['guru_id'] = $employee?->id ?? $teacher?->id;
            }
            $data['created_by'] = $user->id;
        }

        $kisi = $this->kisiKisiRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Kisi-kisi Ujian Baru', [
            'kisi_kisi_id' => $kisi->id,
            'judul_kisi' => $kisi->judul_kisi,
            'mata_pelajaran_id' => $kisi->mata_pelajaran_id,
            'cp_id' => $kisi->cp_id,
            'tp_id' => $kisi->tp_id,
            'jenis_ujian' => $kisi->jenis_ujian,
            'user_id' => auth()->id(),
        ]);

        return $kisi;
    }

    public function ubah(string $id, array $data): ?LmsKisiKisi
    {
        $existing = $this->kisiKisiRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $user = Auth::user();
        $adminRoles = ['superadmin', 'yayasan', 'ketuayayasan', 'pengurusyayasan', 'sekretarisyayasan', 'bendaharayayasan', 'kepalasekolah', 'tatausaha', 'tu', 'divisipendidikan'];
        $isAdmin = false;
        if ($user) {
            $userRoles = $user->getRoleNames()->map(fn ($r) => strtolower((string) preg_replace('/[\s_-]+/', '', $r)));
            $isAdmin = $userRoles->intersect($adminRoles)->isNotEmpty();
        }

        if ($user && ! $isAdmin) {
            $employee = Employee::where('user_id', $user->id)->first();
            $teacher = Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null);
            if ($employee || $teacher) {
                $data['guru_id'] = $employee?->id ?? $teacher?->id;
            }
            $data['updated_by'] = $user->id;
        }

        $updated = $this->kisiKisiRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Kisi-kisi Ujian', [
            'kisi_kisi_id' => $id,
            'judul_sebelum' => $existing->judul_kisi,
            'judul_sesudah' => $updated->judul_kisi ?? $existing->judul_kisi,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $kisi = $this->kisiKisiRepository->findById($id);
        if (! $kisi) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Kisi-kisi Ujian (Soft Delete)', [
            'kisi_kisi_id' => $id,
            'judul_kisi' => $kisi->judul_kisi,
            'user_id' => auth()->id(),
        ]);

        return $this->kisiKisiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Kisi-kisi Ujian', [
            'kisi_kisi_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->kisiKisiRepository->restore($id);
    }

    public function duplikasi(string $id): ?LmsKisiKisi
    {
        $duplicated = $this->kisiKisiRepository->duplicate($id);

        if ($duplicated) {
            Log::info('[AUDIT LOG] Menduplikasi Kisi-kisi Ujian', [
                'original_id' => $id,
                'new_id' => $duplicated->id,
                'user_id' => auth()->id(),
            ]);
        }

        return $duplicated;
    }

    public function statistik(): array
    {
        return $this->kisiKisiRepository->getStats();
    }

    public function opsi(?string $mataPelajaranId = null, ?string $cpId = null, ?string $unitId = null): array
    {
        $user = Auth::user();
        $adminRoles = ['superadmin', 'yayasan', 'ketuayayasan', 'pengurusyayasan', 'sekretarisyayasan', 'bendaharayayasan', 'kepalasekolah', 'tatausaha', 'tu', 'divisipendidikan', 'wakakurikulum', 'kurikulum'];
        $isAdmin = false;
        if ($user) {
            $userRoles = $user->getRoleNames()->map(fn ($r) => strtolower((string) preg_replace('/[\s_-]+/', '', $r)));
            $isAdmin = $userRoles->intersect($adminRoles)->isNotEmpty();
        }

        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;
        $teacher = $user ? (Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null)) : null;
        $isTeacherScope = ($user && ! $isAdmin && ($employee || $teacher));

        $subjectQuery = Subject::where(function ($q) {
            $q->where('status', true)->orWhereNull('status');
        });

        $kelasQuery = Kelas::select('id', 'nama_kelas', 'tingkat', 'unit_pendidikan_id');

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

            // 2. Filter Kelas: Hanya rombel/kelas yang diajar atau di-walikelasi oleh guru
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

            $gurus = Employee::where('id', $employee?->id)
                ->select('id', 'nama_lengkap', 'niy', 'nik')
                ->get();
        } else {
            if ($unitId) {
                $subjectQuery->where('unit_pendidikan_id', $unitId);
                $kelasQuery->where('unit_pendidikan_id', $unitId);
            }
            $gurus = Employee::select('id', 'nama_lengkap', 'niy', 'nik')->limit(100)->get();
        }

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

        $kurikulumList = MasterKurikulum::select('id', 'nama_kurikulum', 'kode_kurikulum')->get();
        $kelasList = $kelasQuery->orderBy('nama_kelas')->get();
        $semesters = Semester::select('id', 'name', 'sequence')
            ->get()
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'nama' => $semester->name,
                'nama_semester' => $semester->nama_semester,
                'tipe_semester' => $semester->tipe_semester,
            ])
            ->values();
        $tahunAjaranList = AcademicYear::select('id', 'name', 'is_active')
            ->get()
            ->map(fn (AcademicYear $tahunAjaran) => [
                'id' => $tahunAjaran->id,
                'name' => $tahunAjaran->name,
                'status' => $tahunAjaran->is_active,
                'is_active' => $tahunAjaran->is_active,
            ])
            ->values();

        // ── Query Capaian Pembelajaran (CP) dengan Isolasi Role Guru & Smart Matching ──
        $cpQuery = CapaianPembelajaran::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhereNull('status');
            });

        if ($isTeacherScope) {
            if (! empty($mataPelajaranId) && $mataPelajaranId !== '' && $mataPelajaranId !== 'null') {
                $selectedSub = Subject::find($mataPelajaranId);
                $cpQuery->where(function ($q) use ($mataPelajaranId, $selectedSub, $employee) {
                    $q->where('mata_pelajaran_id', $mataPelajaranId);
                    if ($selectedSub) {
                        $cleanKode = strtoupper((string) ($selectedSub->kode_mapel ?? $selectedSub->code ?? ''));
                        $cleanNama = strtoupper((string) ($selectedSub->nama_mapel ?? $selectedSub->name ?? ''));
                        $q->orWhere(function ($subQ) use ($cleanKode, $cleanNama, $employee) {
                            if (! empty($cleanKode)) {
                                $subQ->where('kode_cp', 'like', "%{$cleanKode}%");
                            } elseif (! empty($cleanNama)) {
                                $subQ->where('nama_cp', 'like', "%{$cleanNama}%");
                            }
                            if ($employee?->unit_id) {
                                $subQ->where('unit_pendidikan_id', $employee->unit_id);
                            }
                        });
                    }
                });
            } else {
                // Saat opsi awal, batasi hanya ke CP dari mapel yang diampu oleh guru
                if (! empty($allTeacherSubjectIds) && $allTeacherSubjectIds->isNotEmpty()) {
                    $cpQuery->whereIn('mata_pelajaran_id', $allTeacherSubjectIds);
                } elseif ($employee?->unit_id) {
                    $cpQuery->where('unit_pendidikan_id', $employee->unit_id);
                } else {
                    $cpQuery->whereRaw('1 = 0');
                }
            }
        } else {
            // Role Admin / Kurikulum / Yayasan
            if (! empty($mataPelajaranId) && $mataPelajaranId !== '' && $mataPelajaranId !== 'null') {
                $cpQuery->where('mata_pelajaran_id', $mataPelajaranId);
            } elseif ($unitId) {
                $cpQuery->where('unit_pendidikan_id', $unitId);
            }
        }

        $cps = $cpQuery->get()
            ->map(function ($item) {
                $nama = $item->nama_cp ?? $item->deskripsi ?? 'Capaian Pembelajaran';
                $kode = $item->kode_cp ?? '';

                return [
                    'id' => $item->id,
                    'mata_pelajaran_id' => $item->mata_pelajaran_id,
                    'kode_cp' => $kode,
                    'nama_cp' => $nama,
                    'deskripsi' => $item->deskripsi ?? $nama,
                    'fase' => $item->fase ?? null,
                    'label' => $kode ? "{$kode} - {$nama}" : $nama,
                ];
            })
            ->values();

        // ── Query Tujuan Pembelajaran (TP) dengan Isolasi Ketat & Chained Lazy Filter ──
        $tpQuery = TujuanPembelajaran::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhereNull('status');
            });

        if (! empty($cpId) && $cpId !== '' && $cpId !== 'null') {
            // Jika CP spesifik dipilih: hanya ambil TP turunan langsung dari CP tersebut
            $tpQuery->where('cp_id', $cpId);
        } elseif (! empty($mataPelajaranId) && $mataPelajaranId !== '' && $mataPelajaranId !== 'null') {
            // Jika Mapel dipilih tapi CP belum: hanya ambil TP dari CP yang berelasi dengan mapel tersebut
            $matchingCpIds = (clone $cpQuery)->pluck('id');
            if ($matchingCpIds->isNotEmpty()) {
                $tpQuery->whereIn('cp_id', $matchingCpIds);
            } else {
                $tpQuery->whereRaw('1 = 0');
            }
        } else {
            // Jika Mapel maupun CP belum dipilih:
            // JANGAN BOCORKAN SELURUH DATA TP DATABASE!
            if ($isTeacherScope) {
                $teacherCpIds = (clone $cpQuery)->pluck('id');
                if ($teacherCpIds->isNotEmpty()) {
                    $tpQuery->whereIn('cp_id', $teacherCpIds);
                } else {
                    $tpQuery->whereRaw('1 = 0');
                }
            } elseif ($unitId) {
                $unitCpIds = CapaianPembelajaran::where('unit_pendidikan_id', $unitId)->pluck('id');
                $tpQuery->whereIn('cp_id', $unitCpIds);
            } else {
                // Admin tanpa filter mapel/cp: jangan dump seluruh database
                $tpQuery->whereRaw('1 = 0');
            }
        }

        $tps = $tpQuery->get()
            ->map(function ($item) {
                $nama = $item->nama_tp ?? $item->deskripsi ?? 'Tujuan Pembelajaran';
                $kode = $item->kode_tp ?? '';

                return [
                    'id' => $item->id,
                    'cp_id' => $item->cp_id,
                    'kode_tp' => $kode,
                    'nama_tp' => $nama,
                    'deskripsi' => $item->deskripsi ?? $nama,
                    'label' => $kode ? "{$kode} - {$nama}" : $nama,
                ];
            })
            ->values();

        return [
            'subjects' => $subjects,
            'kurikulum' => $kurikulumList,
            'kelas' => $kelasList,
            'semesters' => $semesters,
            'tahun_ajaran' => $tahunAjaranList,
            'guru' => $gurus,
            'capaian_pembelajaran' => $cps,
            'tujuan_pembelajaran' => $tps,
            'jenis_ujian_options' => [
                ['id' => 'UH', 'nama' => 'Ulangan Harian (UH)'],
                ['id' => 'PTS', 'nama' => 'Penilaian Tengah Semester (PTS)'],
                ['id' => 'UTS', 'nama' => 'Ujian Tengah Semester (UTS)'],
                ['id' => 'PAS', 'nama' => 'Penilaian Akhir Semester (PAS)'],
                ['id' => 'UAS', 'nama' => 'Ujian Akhir Semester (UAS)'],
                ['id' => 'CBT', 'nama' => 'Computer Based Test (CBT)'],
                ['id' => 'Remedial', 'nama' => 'Ujian Remedial'],
            ],
            'level_kognitif_options' => [
                ['id' => 'C1 - Mengingat', 'nama' => 'C1 - Mengingat (Remembering)'],
                ['id' => 'C2 - Memahami', 'nama' => 'C2 - Memahami (Understanding)'],
                ['id' => 'C3 - Mengaplikasikan', 'nama' => 'C3 - Mengaplikasikan (Applying)'],
                ['id' => 'C4 - Menganalisis', 'nama' => 'C4 - Menganalisis (Analyzing)'],
                ['id' => 'C5 - Mengevaluasi', 'nama' => 'C5 - Mengevaluasi (Evaluating)'],
                ['id' => 'C6 - Mencipta', 'nama' => 'C6 - Mencipta (Creating)'],
            ],
        ];
    }
}
