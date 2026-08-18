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
            $jenjangCode = preg_replace('/[^A-Za-z0-9]/', '', $data['jenjang'] ?? 'KLS');
            $data['kode_kelas'] = 'KLS-'.strtoupper($jenjangCode).'-'.rand(100, 999);
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
    public function dapatkanStatistik(?array $allowedKelasIds = null): array
    {
        return $this->kelasRepository->dapatkanStatistik($allowedKelasIds);
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
            return [];
        }

        $siswa = Student::where('kelas_id', $kelasId)->get();

        return [
            'kelas' => [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'kode_kelas' => $kelas->kode_kelas,
                'kapasitas' => $kelas->kapasitas,
                'jumlah_siswa' => $siswa->count(),
            ],
            'siswa' => $siswa,
        ];
    }

    /**
     * Proses impor data kelas dari array data Excel/CSV.
     */
    public function prosesImport(array $rows): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $tahunDefault = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semesterDefault = Semester::where('is_active', true)->first() ?? Semester::first();

        foreach ($rows as $index => $row) {
            try {
                $kode = trim($row['kode_kelas'] ?? $row['kode'] ?? '');
                $nama = trim($row['nama_kelas'] ?? $row['nama'] ?? '');

                if (empty($kode) || empty($nama)) {
                    $gagal++;
                    $errors[] = 'Baris '.($index + 1).': Kode dan Nama kelas wajib diisi.';

                    continue;
                }

                $unitId = $row['unit_pendidikan_id'] ?? null;
                if (! $unitId) {
                    $gagal++;
                    $errors[] = 'Baris '.($index + 1).': Unit pendidikan wajib diisi.';

                    continue;
                }

                Kelas::updateOrCreate(
                    ['kode_kelas' => $kode],
                    [
                        'unit_pendidikan_id' => $unitId,
                        'tahun_ajaran_id' => $row['tahun_ajaran_id'] ?? $tahunDefault?->id,
                        'semester_id' => $row['semester_id'] ?? $semesterDefault?->id,
                        'jenjang' => $row['jenjang'] ?? 'SDIT',
                        'tingkat' => $row['tingkat'] ?? '1',
                        'nama_kelas' => $nama,
                        'ruangan' => $row['ruangan'] ?? 'Utama',
                        'kapasitas' => (int) ($row['kapasitas'] ?? 30),
                        'status' => $row['status'] ?? 'Aktif',
                    ]
                );
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = 'Baris '.($index + 1).': '.$e->getMessage();
            }
        }

        return [
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }
}
