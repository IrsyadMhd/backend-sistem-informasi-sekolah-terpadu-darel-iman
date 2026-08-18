<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ModulSemester;
use App\Models\Semester;
use App\Models\Subject;
use App\Repositories\Contracts\ModulSemesterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ModulSemesterService
{
    public function __construct(
        protected ModulSemesterRepositoryInterface $modulSemesterRepository
    ) {}

    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->modulSemesterRepository->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);
    }

    public function dapatkanSemua(array $filters = []): Collection
    {
        return $this->modulSemesterRepository->dapatkanSemua($filters);
    }

    public function cariBerdasarkanId(string $id): ?ModulSemester
    {
        return $this->modulSemesterRepository->cariBerdasarkanId($id);
    }

    public function dapatkanOpsiMaster(): array
    {
        $tahunAjaran = AcademicYear::where('is_active', true)->orWhereNotNull('id')->get(['id', 'name', 'is_active']);
        $semesters = Semester::get(['id', 'name', 'academic_year_id', 'is_active']);
        $units = EducationUnit::where('is_active', true)->orWhereNotNull('id')->get(['id', 'name', 'code', 'level']);
        $kelas = Kelas::where('status', 'Aktif')->get(['id', 'nama_kelas', 'kode_kelas', 'unit_pendidikan_id', 'tahun_ajaran_id', 'semester_id', 'jenjang']);
        $subjects = Subject::get(['id', 'code', 'name', 'unit_pendidikan_id', 'kelompok_mapel']);
        $guru = Employee::with(['unit:id,name,code', 'position:id,name'])
            ->get(['id', 'nama_lengkap', 'niy', 'nik', 'foto', 'unit_id', 'jabatan_id', 'status_pegawai', 'status', 'email', 'no_hp']);

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semesters' => $semesters,
            'unit_pendidikan' => $units,
            'kelas' => $kelas,
            'mata_pelajaran' => $subjects,
            'guru' => $guru,
            'kurikulum' => [
                'Kurikulum Merdeka',
                'Kurikulum 2013 (K13)',
                'Kurikulum Khas Islam Terpadu (JSIT)',
                'Kurikulum Tahfizh & Dinaiyah',
            ],
            'metode_pembelajaran' => [
                'Ceramah & Diskusi',
                'Tanya Jawab & Qiraah',
                'Praktikum & Simulasi',
                'Project Based Learning',
                'Problem Based Learning',
                'Hafalan & Murajaah',
            ],
            'model_pembelajaran' => [
                'Problem Based Learning (PBL)',
                'Project Based Learning (PjBL)',
                'Discovery Learning',
                'Inquiry Learning',
                'Cooperative Learning',
                'Direct Instruction',
            ],
        ];
    }

    public function simpan(array $data): ModulSemester
    {
        $this->validasiKombinasiUnik($data);
        $this->validasiTotalBobot($data);

        if (empty($data['kode_modul'])) {
            $data['kode_modul'] = $this->generateKodeModul($data);
        }

        $details = $data['details'] ?? [];
        unset($data['details']);

        return $this->modulSemesterRepository->buat($data, $details);
    }

    public function ubah(string $id, array $data): ModulSemester
    {
        $this->validasiKombinasiUnik($data, $id);
        $this->validasiTotalBobot($data);

        $details = $data['details'] ?? [];
        unset($data['details']);

        return $this->modulSemesterRepository->perbarui($id, $data, $details);
    }

    public function hapus(string $id): bool
    {
        return $this->modulSemesterRepository->hapus($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->modulSemesterRepository->pulihkan($id);
    }

    public function gantiStatus(string $id, string $status): ModulSemester
    {
        return $this->modulSemesterRepository->gantiStatus($id, $status);
    }

    public function duplikasi(string $id): ModulSemester
    {
        return $this->modulSemesterRepository->duplikasi($id);
    }

    public function dapatkanStatistik(): array
    {
        return $this->modulSemesterRepository->dapatkanStatistik();
    }

    protected function validasiKombinasiUnik(array $data, ?string $ignoreId = null): void
    {
        $query = ModulSemester::where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('kelas_id', $data['kelas_id'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id']);

        if (! empty($data['unit_pendidikan_id'])) {
            $query->where('unit_pendidikan_id', $data['unit_pendidikan_id']);
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'kombinasi' => ['Modul Semester untuk kombinasi Tahun Ajaran, Semester, Unit, Kelas, dan Mata Pelajaran ini sudah ada.'],
            ]);
        }
    }

    protected function validasiTotalBobot(array $data): void
    {
        $tugas = (float) ($data['bobot_tugas'] ?? 0);
        $quiz = (float) ($data['bobot_quiz'] ?? 0);
        $projek = (float) ($data['bobot_projek'] ?? 0);
        $uts = (float) ($data['bobot_uts'] ?? 0);
        $uas = (float) ($data['bobot_uas'] ?? 0);

        $total = $tugas + $quiz + $projek + $uts + $uas;

        if (abs($total - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'bobot' => ["Total bobot penilaian harus 100%. Saat ini total bobot adalah {$total}%."],
            ]);
        }
    }

    public function generateKodeModul(array $data): string
    {
        $prefix = 'MDS';
        $rand = strtoupper(Str::random(5));

        return "{$prefix}-".date('Ym')."-{$rand}";
    }
}
