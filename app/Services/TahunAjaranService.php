<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Repositories\Contracts\TahunAjaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TahunAjaranService
{
    public function __construct(
        protected TahunAjaranRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'start_date', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function dapatkanBerdasarkanId(string|int $id): ?AcademicYear
    {
        return $this->repository->findById($id);
    }

    public function simpan(array $payload): AcademicYear
    {
        $data = [
            'name' => trim($payload['name']),
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'is_active' => filter_var($payload['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'metadata' => [
                'keterangan' => $payload['keterangan'] ?? null,
            ],
        ];

        return $this->repository->create($data);
    }

    public function ubah(string|int $id, array $payload): ?AcademicYear
    {
        $data = [
            'name' => trim($payload['name']),
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'is_active' => filter_var($payload['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'metadata' => [
                'keterangan' => $payload['keterangan'] ?? null,
            ],
        ];

        return $this->repository->update($id, $data);
    }

    public function hapus(string|int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function pulihkan(string|int $id): bool
    {
        return $this->repository->restore($id);
    }

    public function setAktif(string|int $id): ?AcademicYear
    {
        return $this->repository->setAktif($id);
    }

    public function dapatkanStatistik(): array
    {
        return $this->repository->getStats();
    }

    public function dapatkanDropdown(): Collection
    {
        return $this->repository->getDropdownOptions();
    }

    public function eksporData(array $filters = []): array
    {
        $result = $this->repository->getFiltered($filters, 10000, 'start_date', 'desc');
        $rows = [];

        foreach ($result->items() as $index => $item) {
            $rows[] = [
                'no' => $index + 1,
                'id' => $item->id,
                'nama' => $item->name,
                'start_date' => $item->start_date?->format('Y-m-d'),
                'end_date' => $item->end_date?->format('Y-m-d'),
                'is_active' => $item->is_active ? 'Ya' : 'Tidak',
                'keterangan' => $item->metadata['keterangan'] ?? '',
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    public function prosesImport(array $rows, bool $overwrite = false): array
    {
        $berhasil = 0;
        $diperbarui = 0;
        $duplikat = 0;
        $gagal = 0;
        $duplicates = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $name = trim($row['name'] ?? $row['nama'] ?? $row['tahun_ajaran'] ?? '');
            if (empty($name)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Nama tahun ajaran wajib diisi.";
                continue;
            }

            $startDate = !empty($row['start_date']) ? $row['start_date'] : (!empty($row['tanggal_mulai']) ? $row['tanggal_mulai'] : null);
            $endDate = !empty($row['end_date']) ? $row['end_date'] : (!empty($row['tanggal_selesai']) ? $row['tanggal_selesai'] : null);

            // Default dates if missing
            if (!$startDate && preg_match('/(\d{4})\/(\d{4})/', $name, $m)) {
                $startDate = "{$m[1]}-07-01";
                $endDate = "{$m[2]}-06-30";
            }

            $isActive = false;
            if (isset($row['is_active']) || isset($row['status'])) {
                $val = strtolower((string) ($row['is_active'] ?? $row['status']));
                $isActive = in_array($val, ['1', 'true', 'ya', 'yes', 'aktif']);
            }

            $existing = AcademicYear::where('name', $name)->first();

            try {
                if ($existing) {
                    if ($overwrite) {
                        $this->ubah($existing->id, [
                            'name' => $name,
                            'start_date' => $startDate ?: $existing->start_date,
                            'end_date' => $endDate ?: $existing->end_date,
                            'is_active' => $isActive,
                            'keterangan' => $row['keterangan'] ?? 'Diperbarui via Impor',
                        ]);
                        $diperbarui++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $name,
                            'nama' => $name,
                            'status' => 'Ditimpa',
                            'keterangan' => 'Tahun ajaran sudah ada dan berhasil diperbarui.',
                        ];
                    } else {
                        $duplikat++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $name,
                            'nama' => $name,
                            'status' => 'Dilewati',
                            'keterangan' => "Tahun ajaran '{$name}' sudah terdaftar (mode timpa tidak aktif).",
                        ];
                    }
                } else {
                    $this->simpan([
                        'name' => $name,
                        'start_date' => $startDate ?: date('Y-07-01'),
                        'end_date' => $endDate ?: date('Y-06-30', strtotime('+1 year')),
                        'is_active' => $isActive,
                        'keterangan' => $row['keterangan'] ?? 'Diimpor via berkas',
                    ]);
                    $berhasil++;
                }
            } catch (\Throwable $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Gagal menyimpan '{$name}': " . $e->getMessage();
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
