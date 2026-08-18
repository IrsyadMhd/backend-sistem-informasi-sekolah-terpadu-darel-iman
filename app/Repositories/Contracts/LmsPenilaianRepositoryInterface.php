<?php

namespace App\Repositories\Contracts;

use App\Models\StudentGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsPenilaianRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?StudentGrade;

    public function create(array $data): StudentGrade;

    public function update(string $id, array $data): ?StudentGrade;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function calculateAndSyncClass(string $kelasId, string $subjectId, string $semesterId, array $weights = []): Collection;

    public function getStats(array $filters = []): array;
}
