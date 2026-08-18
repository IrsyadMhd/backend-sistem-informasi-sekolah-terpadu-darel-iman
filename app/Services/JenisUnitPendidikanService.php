<?php

namespace App\Services;

use App\Models\JenisUnitPendidikan;
use App\Repositories\Contracts\JenisUnitPendidikanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class JenisUnitPendidikanService
{
    public function __construct(
        protected JenisUnitPendidikanRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->repository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function dapatkanStatistik(): array
    {
        return $this->repository->getStats();
    }

    public function dapatkanDropdown(): Collection
    {
        return $this->repository->getDropdownOptions();
    }

    public function cariBerdasarkanId(string|int $id): ?JenisUnitPendidikan
    {
        return $this->repository->findById($id);
    }

    public function simpan(array $data, int|string|null $userId = null): JenisUnitPendidikan
    {
        $payload = array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'created_by' => $userId,
            'status' => isset($data['status']) ? filter_var($data['status'], FILTER_VALIDATE_BOOLEAN) : true,
            'urutan' => (int) ($data['urutan'] ?? 1),
            'warna_badge' => $data['warna_badge'] ?? '#10B981',
            'icon' => $data['icon'] ?? 'School',
        ]);

        return $this->repository->create($payload);
    }

    public function ubah(string|int $id, array $data, int|string|null $userId = null): ?JenisUnitPendidikan
    {
        $payload = array_merge($data, [
            'updated_by' => $userId,
            'status' => isset($data['status']) ? filter_var($data['status'], FILTER_VALIDATE_BOOLEAN) : true,
            'urutan' => (int) ($data['urutan'] ?? 1),
        ]);

        return $this->repository->update($id, $payload);
    }

    public function hapus(string|int $id, int|string|null $userId = null): array
    {
        $item = $this->repository->findById($id);
        if (! $item) {
            return [
                'success' => false,
                'message' => 'Data jenis unit pendidikan tidak ditemukan.',
            ];
        }

        // Integrity check: If unit pendidikan exists with this jenis_unit_id
        if (Schema::hasColumn('education_units', 'jenis_unit_id')) {
            if ($item->unitPendidikan()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Jika data sudah digunakan oleh Unit Pendidikan maka data tidak dapat dihapus.',
                ];
            }
        }

        $berhasil = $this->repository->delete($id, $userId);

        return [
            'success' => $berhasil,
            'message' => $berhasil ? 'Data jenis unit berhasil dihapus.' : 'Gagal menghapus data jenis unit.',
        ];
    }

    public function pulihkan(string|int $id): bool
    {
        return $this->repository->restore($id);
    }

    public function eksporData(array $filters = []): array
    {
        $items = JenisUnitPendidikan::filter($filters)
            ->orderBy('urutan', 'asc')
            ->get();

        return $items->map(function ($item, $index) {
            return [
                'no' => $index + 1,
                'kode_jenis' => $item->kode_jenis,
                'nama_jenis' => $item->nama_jenis,
                'singkatan' => $item->singkatan ?? '-',
                'jenjang' => $item->jenjang,
                'warna_badge' => $item->warna_badge ?? '#10B981',
                'icon' => $item->icon ?? 'School',
                'urutan' => $item->urutan,
                'status' => $item->status ? 'Aktif' : 'Tidak Aktif',
                'keterangan' => $item->keterangan ?? '-',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '-',
            ];
        })->toArray();
    }

    public function prosesImport(array $rows, int|string|null $userId = null): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $kode = trim($row['kode_jenis'] ?? '');
            $nama = trim($row['nama_jenis'] ?? '');
            $jenjang = trim($row['jenjang'] ?? 'SD');

            if (empty($kode) || empty($nama)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Kode dan Nama jenis wajib diisi.";

                continue;
            }

            try {
                $status = isset($row['status'])
                    ? (is_bool($row['status']) ? $row['status'] : in_array(strtolower((string) $row['status']), ['1', 'true', 'aktif', 'active']))
                    : true;

                JenisUnitPendidikan::updateOrCreate(
                    ['kode_jenis' => $kode],
                    [
                        'uuid' => (string) Str::uuid(),
                        'nama_jenis' => $nama,
                        'singkatan' => $row['singkatan'] ?? $kode,
                        'jenjang' => $jenjang,
                        'warna_badge' => $row['warna_badge'] ?? '#10B981',
                        'icon' => $row['icon'] ?? 'School',
                        'urutan' => (int) ($row['urutan'] ?? ($rowNum)),
                        'keterangan' => $row['keterangan'] ?? null,
                        'status' => $status,
                        'updated_by' => $userId,
                        'created_by' => $userId,
                    ]
                );
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        return [
            'total' => count($rows),
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }
}
