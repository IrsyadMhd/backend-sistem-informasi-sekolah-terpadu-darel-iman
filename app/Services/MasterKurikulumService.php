<?php

namespace App\Services;

use App\Models\MasterKurikulum;
use App\Repositories\Contracts\MasterKurikulumRepositoryInterface;
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

    public function dapatkanStatistik(): array
    {
        return $this->repository->getStats();
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

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $kode = trim($row['kode_kurikulum'] ?? '');
                    if (empty($kode)) {
                        $jenjang = strtoupper($row['jenjang'] ?? 'SIT');
                        $kode = 'KUR-'.$jenjang.'-'.rand(100, 999);
                    }

                    $nama = trim($row['nama_kurikulum'] ?? '');
                    $unitId = $row['unit_pendidikan_id'] ?? null;
                    $tahunId = $row['tahun_ajaran_id'] ?? null;

                    if (empty($nama) || empty($unitId) || empty($tahunId)) {
                        $gagal++;
                        $errors[] = 'Baris '.($index + 1).': Nama kurikulum, Unit Pendidikan, dan Tahun Ajaran wajib diisi.';

                        continue;
                    }

                    MasterKurikulum::updateOrCreate(
                        ['kode_kurikulum' => $kode],
                        [
                            'nama_kurikulum' => $nama,
                            'jenis_kurikulum' => $row['jenis_kurikulum'] ?? 'SIT',
                            'unit_pendidikan_id' => $unitId,
                            'jenjang' => $row['jenjang'] ?? 'SD',
                            'tahun_ajaran_id' => $tahunId,
                            'semester_id' => $row['semester_id'] ?? null,
                            'tanggal_mulai' => $row['tanggal_mulai'] ?? now()->toDateString(),
                            'tanggal_selesai' => $row['tanggal_selesai'] ?? null,
                            'status' => filter_var($row['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'deskripsi' => $row['deskripsi'] ?? null,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );

                    $berhasil++;
                } catch (\Exception $e) {
                    $gagal++;
                    $errors[] = 'Baris '.($index + 1).': '.$e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'berhasil' => 0,
                'gagal' => count($rows),
                'errors' => [$e->getMessage()],
            ];
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
                'id' => $item->id,
                'kode_kurikulum' => $item->kode_kurikulum,
                'nama_kurikulum' => $item->nama_kurikulum,
                'jenis_kurikulum' => $item->jenis_kurikulum,
                'unit_pendidikan' => $item->unitPendidikan?->name ?? '-',
                'jenjang' => $item->jenjang,
                'tahun_ajaran' => $item->tahunAjaran?->name ?? '-',
                'semester' => $item->semester?->name ?? '-',
                'tanggal_mulai' => $item->tanggal_mulai ? $item->tanggal_mulai->format('Y-m-d') : '-',
                'tanggal_selesai' => $item->tanggal_selesai ? $item->tanggal_selesai->format('Y-m-d') : '-',
                'status' => $item->status ? 'Aktif' : 'Nonaktif',
                'deskripsi' => $item->deskripsi ?? '',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '-',
            ];
        }

        return $result;
    }
}
