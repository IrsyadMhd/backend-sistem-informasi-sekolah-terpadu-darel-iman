<?php

namespace App\Repositories\Contracts;

use App\Models\LmsRapor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsRaporRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?LmsRapor;

    public function findBySiswaPeriod(string $siswaId, string $semesterId, string $tahunAjaranId): ?LmsRapor;

    public function create(array $data): LmsRapor;

    public function update(string $id, array $data): ?LmsRapor;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function generateForStudent(string $siswaId, string $semesterId, string $tahunAjaranId, ?string $kelasId = null): LmsRapor;

    public function generateForClass(string $kelasId, string $semesterId, string $tahunAjaranId): Collection;

    public function getStats(array $filters = []): array;

    public function getOptions(): array;
}
