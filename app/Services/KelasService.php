<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Repositories\Contracts\KelasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Class KelasService
 * Layer Layanan Bisnis untuk manajemen kelas/rombel.
 */
class KelasService
{
    public function __construct(
        protected KelasRepositoryInterface $kelasRepository
    ) {}

    /**
     * Dapatkan daftar kelas berpaginasi dengan filter.
     */
    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->kelasRepository->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);
    }

    /**
     * Dapatkan detail kelas berdasarkan ID.
     */
    public function cariBerdasarkanId(string $id): ?Kelas
    {
        return $this->kelasRepository->cariBerdasarkanId($id);
    }

    /**
     * Simpan data kelas baru.
     */
    public function simpan(array $data): Kelas
    {
        if (empty($data['kode_kelas'])) {
            $codeGenerator = app(\App\Services\CodeGeneratorService::class);
            $data['kode_kelas'] = $codeGenerator->generate(
                'class_code',
                $data['unit_pendidikan_id'] ?? null,
                $data['tahun_ajaran_id'] ?? null,
                ['jenjang' => $data['jenjang'] ?? 'KLS']
            );
        }

        return $this->kelasRepository->buat($data);
    }

    /**
     * Ubah data kelas.
     */
    public function ubah(string $id, array $data): Kelas
    {
        return $this->kelasRepository->perbarui($id, $data);
    }

    /**
     * Hapus data kelas (Soft Delete).
     */
    public function hapus(string $id): bool
    {
        return $this->kelasRepository->hapus($id);
    }

    /**
     * Pulihkan data kelas yang dihapus.
     */
    public function pulihkan(string $id): bool
    {
        return $this->kelasRepository->pulihkan($id);
    }

    /**
     * Dapatkan statistik ringkasan kelas.
     */
    public function dapatkanStatistik(?array $allowedKelasIds = null, ?string $unitId = null): array
    {
        return $this->kelasRepository->dapatkanStatistik($allowedKelasIds, $unitId);
    }

    /**
     * Dapatkan daftar opsi data master untuk dropdown form/filter.
     */
    public function dapatkanOpsiMaster(?array $allowedUnitIds = null): array
    {
        $units = EducationUnit::query()
            ->where('is_active', true)
            ->when($allowedUnitIds !== null, fn ($query) => $query->whereIn('id', $allowedUnitIds))
            ->select('id', 'name', 'code', 'level')
            ->orderBy('name', 'asc')
            ->get();
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->select('id', 'name', 'is_active')->get();
        $semesters = Semester::orderBy('sequence', 'asc')->select('id', 'academic_year_id', 'name', 'sequence', 'is_active')->get();

        // Ambil data Pegawai / Guru (hanya dari tabel employees, tidak membuat baru)
        $employees = Employee::where('status', 'Aktif')
            ->when($allowedUnitIds !== null, fn ($query) => $query->whereIn('unit_id', $allowedUnitIds))
            ->select('id', 'niy', 'nama_lengkap', 'gelar_depan', 'gelar_belakang', 'unit_id', 'jabatan_id')
            ->orderBy('nama_lengkap', 'asc')
            ->get()
            ->map(function ($emp) {
                $gelarDepan = $emp->gelar_depan ? $emp->gelar_depan.' ' : '';
                $gelarBelakang = $emp->gelar_belakang ? ', '.$emp->gelar_belakang : '';

                return [
                    'id' => $emp->id,
                    'niy' => $emp->niy,
                    'nama_lengkap' => $emp->nama_lengkap,
                    'nama_tampil' => trim($gelarDepan.$emp->nama_lengkap.$gelarBelakang),
                    'unit_id' => $emp->unit_id,
                ];
            });

        $jenjangList = ['TKIT', 'SDIT', 'SMPIT', 'SMAIT', 'MIT', 'MA'];
        $tingkatList = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'TK A', 'TK B'];

        return [
            'units' => $units,
            'tahun_ajaran' => $academicYears,
            'semesters' => $semesters,
            'employees' => $employees,
            'guru' => $employees, // Alias untuk kompatibilitas
            'jenjang' => $jenjangList,
            'tingkat' => $tingkatList,
        ];
    }

    /**
     * Dapatkan daftar siswa dalam rombel tertentu.
     */
    public function dapatkanSiswaRombel(string $kelasId): array
    {
        $kelas = $this->kelasRepository->cariBerdasarkanId($kelasId);
        if (! $kelas) {
            $legacyClass = \App\Models\SchoolClass::find($kelasId);
            if (! $legacyClass) {
                return [];
            }
            $kelas = new Kelas();
            $kelas->id = $legacyClass->id;
            $kelas->nama_kelas = $legacyClass->name;
            $kelas->kode_kelas = $legacyClass->code ?? $legacyClass->name;
            $kelas->kapasitas = $legacyClass->capacity ?? 30;
        }

        $matchingKelasIds = [$kelasId];
        if (! empty($kelas->nama_kelas)) {
            $tblIds = Kelas::where('nama_kelas', $kelas->nama_kelas)
                ->orWhere('kode_kelas', $kelas->nama_kelas)
                ->pluck('id')
                ->toArray();
            $legacyIds = \App\Models\SchoolClass::where('name', $kelas->nama_kelas)
                ->pluck('id')
                ->toArray();
            $matchingKelasIds = array_values(array_unique(array_filter(array_merge($matchingKelasIds, $tblIds, $legacyIds))));
        }

        $siswa = Student::where(function ($q) use ($matchingKelasIds) {
            $q->whereIn('kelas_id', $matchingKelasIds)
              ->orWhereIn('class_id', $matchingKelasIds);
        })->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->orderBy('full_name')->get();

        $siswaIds = $siswa->pluck('id')->toArray();
        $logsByStudent = \App\Models\TahfizhDailyLog::whereIn('student_id', $siswaIds)
            ->whereNotNull('hafalan_surah_number')
            ->orderBy('record_date', 'desc')
            ->get()
            ->groupBy('student_id');

        $siswaMapped = $siswa->map(function ($st) use ($logsByStudent) {
            $stLogs = $logsByStudent->get($st->id) ?? collect();
            $latestLog = $stLogs->first();

            $totalAyats = $stLogs->sum(function ($l) {
                if ($l->hafalan_ayah_start && $l->hafalan_ayah_end) {
                    return max((int)$l->hafalan_ayah_end - (int)$l->hafalan_ayah_start + 1, 0);
                }
                return (int)($l->hafalan_baris ?? 0);
            });
            $uniqueSurahs = $stLogs->pluck('hafalan_surah_number')->filter()->unique()->count();
            $progressPct = $totalAyats > 0 ? round(($totalAyats / 6236) * 100, 2) : 0;

            $stArray = $st->toArray();
            $stArray['total_ayats_memorized'] = $totalAyats;
            $stArray['total_surahs_memorized'] = $uniqueSurahs;
            $stArray['hafalan_surah_terakhir'] = $latestLog->hafalan_surah_name ?? null;
            $stArray['hafalan_ayat_terakhir'] = ($latestLog && $latestLog->hafalan_ayah_start) 
                ? "{$latestLog->hafalan_ayah_start} - {$latestLog->hafalan_ayah_end}" 
                : null;
            $stArray['progress_percentage'] = $progressPct;

            return $stArray;
        });

        return [
            'kelas' => [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'kode_kelas' => $kelas->kode_kelas,
                'kapasitas' => $kelas->kapasitas,
                'jumlah_siswa' => $siswa->count(),
            ],
            'siswa' => $siswaMapped,
        ];
    }

    /**
     * Proses impor data kelas dari array data Excel/CSV.
     *
     * @param  array  $rows
     * @param  bool   $overwrite Jika true, data yang sudah ada akan ditimpa/diperbarui. Jika false, dilewati.
     * @return array
     */
    public function prosesImport(array $rows, bool $overwrite = false): array
    {
        $berhasil = 0;
        $diperbarui = 0;
        $duplikat = 0;
        $gagal = 0;
        $duplicates = [];
        $errors = [];

        $academicPeriod = app(\App\Services\AcademicPeriodService::class);
        $tahunDefault = $academicPeriod->getActiveYear() ?? AcademicYear::where('status', 'Aktif')->first() ?? AcademicYear::first();
        $semesterDefault = $academicPeriod->getActiveSemester($tahunDefault?->id) ?? ($tahunDefault ? Semester::where('academic_year_id', $tahunDefault->id)->first() : null) ?? Semester::first();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            try {
                $kode = trim((string) ($row['kode_kelas'] ?? $row['kode'] ?? ''));
                $nama = trim((string) ($row['nama_kelas'] ?? $row['nama'] ?? ''));

                if (empty($kode) || empty($nama)) {
                    $gagal++;
                    $errors[] = "Baris {$rowNum}: Kode dan Nama kelas wajib diisi.";

                    continue;
                }

                $unitId = $row['unit_pendidikan_id'] ?? null;
                if (! $unitId) {
                    $gagal++;
                    $errors[] = "Baris {$rowNum}: Unit pendidikan wajib diisi.";

                    continue;
                }

                $rawTahun = trim((string) ($row['tahun_ajaran_id'] ?? ''));
                $tahunId = null;
                if ($rawTahun !== '') {
                    $tahunId = AcademicYear::where('id', $rawTahun)->orWhere('name', $rawTahun)->value('id');
                }
                $tahunId = $tahunId ?: $tahunDefault?->id;

                $rawSem = trim((string) ($row['semester_id'] ?? ''));
                $semId = null;
                if ($rawSem !== '') {
                    $semId = Semester::where('id', $rawSem)->orWhere('name', $rawSem)->value('id');
                }
                $semId = $semId ?: $semesterDefault?->id;

                // Cek apakah data kelas sudah ada berdasarkan kode_kelas atau kombinasi (nama_kelas dan unit_pendidikan_id)
                $existing = Kelas::withTrashed()
                    ->where(function ($q) use ($kode, $nama, $unitId) {
                        $q->where('kode_kelas', $kode)
                            ->orWhere(function ($sub) use ($nama, $unitId) {
                                $sub->where('unit_pendidikan_id', $unitId)
                                    ->where('nama_kelas', $nama);
                            });
                    })
                    ->first();

                if ($existing) {
                    if ($overwrite) {
                        if ($existing->trashed()) {
                            $existing->restore();
                        }
                        $existing->update([
                            'unit_pendidikan_id' => $unitId,
                            'tahun_ajaran_id' => $tahunId ?? $existing->tahun_ajaran_id ?? $tahunDefault?->id,
                            'semester_id' => $semId ?? $existing->semester_id ?? $semesterDefault?->id,
                            'jenjang' => $row['jenjang'] ?? $existing->jenjang ?? 'SDIT',
                            'tingkat' => $row['tingkat'] ?? $existing->tingkat ?? '1',
                            'nama_kelas' => $nama,
                            'ruangan' => $row['ruangan'] ?? $existing->ruangan ?? 'Utama',
                            'kapasitas' => (int) ($row['kapasitas'] ?? $existing->kapasitas ?? 30),
                            'status' => $row['status'] ?? $existing->status ?? 'Aktif',
                        ]);
                        $diperbarui++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $kode,
                            'nama' => $nama,
                            'status' => 'Ditimpa',
                            'keterangan' => "Data kelas '{$nama}' ({$kode}) sudah ada dan berhasil ditimpa.",
                        ];
                    } else {
                        $duplikat++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $kode,
                            'nama' => $nama,
                            'status' => 'Duplikat (Dilewati)',
                            'keterangan' => "Data kelas '{$nama}' ({$kode}) sudah ada dan dilewati karena opsi timpa tidak aktif.",
                        ];
                    }

                    continue;
                }

                // Buat data kelas baru
                Kelas::create([
                    'unit_pendidikan_id' => $unitId,
                    'tahun_ajaran_id' => $tahunId,
                    'semester_id' => $semId,
                    'jenjang' => $row['jenjang'] ?? 'SDIT',
                    'tingkat' => $row['tingkat'] ?? '1',
                    'kode_kelas' => $kode,
                    'nama_kelas' => $nama,
                    'ruangan' => $row['ruangan'] ?? 'Utama',
                    'kapasitas' => (int) ($row['kapasitas'] ?? 30),
                    'status' => $row['status'] ?? 'Aktif',
                ]);
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        return [
            'total' => count($rows),
            'berhasil' => $berhasil,
            'diperbarui' => $diperbarui,
            'duplikat' => $duplikat,
            'gagal' => $gagal,
            'overwrite' => $overwrite,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }
}
