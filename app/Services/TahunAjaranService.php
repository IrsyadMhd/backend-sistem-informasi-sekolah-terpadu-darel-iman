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

    public function prosesImport(array $rows): int
    {
        $importedCount = 0;
        foreach ($rows as $row) {
            if (empty($row['name']) || empty($row['start_date']) || empty($row['end_date'])) {
                continue;
            }

            $isActive = false;
            if (isset($row['is_active'])) {
                $val = strtolower((string) $row['is_active']);
                $isActive = in_array($val, ['1', 'true', 'ya', 'yes', 'aktif']);
            }

            $this->simpan([
                'name' => $row['name'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'is_active' => $isActive,
                'keterangan' => $row['keterangan'] ?? 'Impor CSV/Excel',
            ]);

            $importedCount++;
        }

        return $importedCount;
    }
}
