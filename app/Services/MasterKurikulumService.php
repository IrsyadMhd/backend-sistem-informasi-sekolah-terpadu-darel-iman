<?php

namespace App\Services;

use App\Models\MasterKurikulum;
use App\Repositories\Contracts\MasterKurikulumRepositoryInterface;
use App\Services\CodeGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterKurikulumService
{
    public function __construct(
        protected MasterKurikulumRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function dapatkanStatistik(array $filters = []): array
    {
        return $this->repository->getStats($filters);
    }

    public function dapatkanDropdown(?string $unitPendidikanId = null): Collection
    {
        return $this->repository->getDropdownOptions($unitPendidikanId);
    }

    public function cariBerdasarkanId(string $id): ?MasterKurikulum
    {
        return $this->repository->findById($id);
    }

    public function simpan(array $data, ?string $userId = null): MasterKurikulum
    {
        if ($userId) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        if (empty($data['kode_kurikulum'])) {
            $jenjang = strtoupper($data['jenjang'] ?? 'SIT');
            $data['kode_kurikulum'] = 'KUR-'.$jenjang.'-'.strtoupper(Str::random(5));
        }

        return $this->repository->create($data);
    }

    public function ubah(string $id, array $data, ?string $userId = null): ?MasterKurikulum
    {
        if ($userId) {
            $data['updated_by'] = $userId;
        }

        return $this->repository->update($id, $data);
    }

    public function hapus(string $id, ?string $userId = null): array
    {
        $kurikulum = $this->repository->findById($id);
        if (! $kurikulum) {
            return [
                'success' => false,
                'message' => 'Data master kurikulum tidak ditemukan.',
            ];
        }

        // Pengecekan proteksi keterkaitan relasi (misal jika sudah digunakan oleh modul lain)
        // Saat ini soft delete dengan pengisian audit deleted_by
        $deleted = $this->repository->delete($id, $userId);

        return [
            'success' => $deleted,
            'message' => $deleted ? 'Data master kurikulum berhasil dihapus.' : 'Gagal menghapus data master kurikulum.',
        ];
    }

    public function pulihkan(string $id): bool
    {
        return $this->repository->restore($id);
    }

    public function prosesImport(array $rows, ?string $userId = null): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        // Preload default active academic year & semester
        $academicPeriod = app(\App\Services\AcademicPeriodService::class);
        $defaultTahun = $academicPeriod->getActiveYear()
            ?? \App\Models\AcademicYear::where('is_active', true)->first()
            ?? \App\Models\AcademicYear::orderBy('created_at', 'desc')->first();

        $defaultUnit = \App\Models\EducationUnit::first();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $nama = trim((string) ($row['nama_kurikulum'] ?? $row['nama'] ?? ''));
                if (empty($nama)) {
                    throw new \InvalidArgumentException('Nama kurikulum wajib diisi.');
                }

                // 1. Resolusi Unit Pendidikan (UUID, Nama, Kode, atau Jenjang)
                $unitId = null;
                $rawUnit = trim((string) ($row['unit_pendidikan_id'] ?? $row['unit_id'] ?? $row['unit_pendidikan'] ?? $row['unit'] ?? ''));
                if (!empty($rawUnit)) {
                    if (\Illuminate\Support\Str::isUuid($rawUnit)) {
                        $unitId = \App\Models\EducationUnit::where('id', $rawUnit)->value('id');
                    } else {
                        $unitId = \App\Models\EducationUnit::where('code', $rawUnit)
                            ->orWhere('name', 'ILIKE', '%' . $rawUnit . '%')
                            ->orWhere('level', 'ILIKE', '%' . $rawUnit . '%')
                            ->value('id');
                    }
                }

                $rawJenjang = trim((string) ($row['jenjang'] ?? ''));
                if (!$unitId && !empty($rawJenjang)) {
                    $unitId = \App\Models\EducationUnit::where('level', 'ILIKE', '%' . $rawJenjang . '%')
                        ->orWhere('name', 'ILIKE', '%' . $rawJenjang . '%')
                        ->orWhere('code', 'ILIKE', '%' . $rawJenjang . '%')
                        ->value('id');
                }

                $unitId = $unitId ?: $defaultUnit?->id;
                if (!$unitId) {
                    throw new \InvalidArgumentException('Unit Pendidikan tidak valid atau tidak ditemukan.');
                }

                // 2. Resolusi Tahun Ajaran (UUID, Nama, atau Default Aktif)
                $tahunId = null;
                $rawTahun = trim((string) ($row['tahun_ajaran_id'] ?? $row['tahun_ajaran'] ?? $row['tahun'] ?? ''));
                if (!empty($rawTahun)) {
                    if (\Illuminate\Support\Str::isUuid($rawTahun)) {
                        $tahunId = \App\Models\AcademicYear::where('id', $rawTahun)->value('id');
                    } else {
                        $tahunId = \App\Models\AcademicYear::where('name', $rawTahun)
                            ->orWhere('name', 'ILIKE', '%' . $rawTahun . '%')
                            ->value('id');
                    }
                }
                $tahunId = $tahunId ?: $defaultTahun?->id;
                if (!$tahunId) {
                    throw new \InvalidArgumentException('Tahun Ajaran tidak valid atau tidak ditemukan.');
                }

                // 3. Resolusi Semester (Opsional)
                $semesterId = null;
                $rawSemester = trim((string) ($row['semester_id'] ?? $row['semester'] ?? ''));
                if (!empty($rawSemester) && $rawSemester !== '-') {
                    if (\Illuminate\Support\Str::isUuid($rawSemester)) {
                        $semesterId = \App\Models\Semester::where('id', $rawSemester)->value('id');
                    } else {
                        $semesterId = \App\Models\Semester::where('academic_year_id', $tahunId)
                            ->where(function ($q) use ($rawSemester) {
                                $q->where('name', $rawSemester)
                                    ->orWhere('name', 'ILIKE', '%' . $rawSemester . '%');
                            })->value('id');
                    }
                }

                // 4. Resolusi Jenjang
                $jenjang = !empty($rawJenjang) ? strtoupper($rawJenjang) : null;
                if (!$jenjang && $unitId) {
                    $unitObj = \App\Models\EducationUnit::find($unitId);
                    $jenjang = strtoupper($unitObj?->level ?? 'SD');
                }
                if (!in_array($jenjang, ['TK', 'PAUD', 'TAUD', 'SD', 'MI', 'SMP', 'MTS', 'SMA', 'MA', 'SMK', 'PESANTREN', 'PONDOK', 'SIT', 'UMUM'])) {
                    $jenjang = 'SD';
                }

                // 5. Resolusi Kode Kurikulum
                $kode = trim((string) ($row['kode_kurikulum'] ?? $row['kode'] ?? ''));
                if (empty($kode)) {
                    $kode = CodeGeneratorService::generate('curriculum_code', $unitId, $tahunId, ['extra' => $jenjang]);
                }

                // 6. Tanggal Mulai & Tanggal Selesai
                $tanggalMulai = trim((string) ($row['tanggal_mulai'] ?? ''));
                if (empty($tanggalMulai) || $tanggalMulai === '-' || !strtotime($tanggalMulai)) {
                    $tanggalMulai = now()->toDateString();
                } else {
                    $tanggalMulai = date('Y-m-d', strtotime($tanggalMulai));
                }

                $tanggalSelesai = trim((string) ($row['tanggal_selesai'] ?? ''));
                if (empty($tanggalSelesai) || $tanggalSelesai === '-' || !strtotime($tanggalSelesai)) {
                    $tanggalSelesai = null;
                } else {
                    $tanggalSelesai = date('Y-m-d', strtotime($tanggalSelesai));
                }

                // 7. Resolusi Status
                $rawStatus = $row['status'] ?? true;
                $status = true;
                if (is_bool($rawStatus)) {
                    $status = $rawStatus;
                } else {
                    $strStatus = strtolower(trim((string) $rawStatus));
                    if (in_array($strStatus, ['nonaktif', 'non-aktif', 'inactive', 'false', '0', 'tidak'])) {
                        $status = false;
                    } elseif (in_array($strStatus, ['aktif', 'active', 'true', '1', 'ya'])) {
                        $status = true;
                    }
                }

                // 8. Jenis Kurikulum
                $rawJenis = trim((string) ($row['jenis_kurikulum'] ?? $row['jenis'] ?? 'SIT'));
                $validJenis = ['Nasional', 'Merdeka', 'SIT', 'Lokal', 'Pesantren', 'Lainnya'];
                $jenisKurikulum = 'SIT';
                foreach ($validJenis as $vj) {
                    if (strcasecmp($vj, $rawJenis) === 0) {
                        $jenisKurikulum = $vj;
                        break;
                    }
                }

                MasterKurikulum::updateOrCreate(
                    ['kode_kurikulum' => $kode],
                    [
                        'nama_kurikulum' => $nama,
                        'jenis_kurikulum' => $jenisKurikulum,
                        'unit_pendidikan_id' => $unitId,
                        'jenjang' => $jenjang,
                        'tahun_ajaran_id' => $tahunId,
                        'semester_id' => $semesterId,
                        'tanggal_mulai' => $tanggalMulai,
                        'tanggal_selesai' => $tanggalSelesai,
                        'status' => $status,
                        'deskripsi' => $row['deskripsi'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );

                \Illuminate\Support\Facades\DB::commit();
                $berhasil++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                $gagal++;
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
                \Illuminate\Support\Facades\Log::warning("Gagal impor baris {$rowNum} kurikulum: " . $e->getMessage(), [
                    'row' => $row,
                ]);
            }
        }

        return [
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }

    public function eksporData(array $filters = []): array
    {
        $paginated = $this->repository->getFiltered($filters, 5000, 'kode_kurikulum', 'asc');

        $result = [];
        foreach ($paginated->items() as $index => $item) {
            $result[] = [
                'no' => $index + 1,
                'kode_kurikulum' => $item->kode_kurikulum,
                'nama_kurikulum' => $item->nama_kurikulum,
                'jenis_kurikulum' => $item->jenis_kurikulum,
                'jenjang' => $item->jenjang,
                'unit_pendidikan' => $item->unitPendidikan?->name ?? '',
                'tahun_ajaran' => $item->tahunAjaran?->name ?? '',
                'semester' => $item->semester?->name ?? '',
                'tanggal_mulai' => $item->tanggal_mulai ? $item->tanggal_mulai->format('Y-m-d') : '',
                'tanggal_selesai' => $item->tanggal_selesai ? $item->tanggal_selesai->format('Y-m-d') : '',
                'status' => $item->status ? 'Aktif' : 'Nonaktif',
                'deskripsi' => $item->deskripsi ?? '',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '',
            ];
        }

        return $result;
    }
}
