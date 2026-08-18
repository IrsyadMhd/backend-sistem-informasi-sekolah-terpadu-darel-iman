<?php

namespace App\Repositories\Contracts;

use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsUjianRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?LmsUjian;

    public function create(array $data): LmsUjian;

    public function update(string $id, array $data): ?LmsUjian;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function duplicate(string $id): ?LmsUjian;

    public function getStats(array $filters = []): array;

    // CBT Session & Auto Scoring Methods
    public function findSesiById(string $sesiId): ?LmsUjianSesi;

    public function getSesiByUjianId(string $ujianId): Collection;

    public function getSesiBySiswa(string $ujianId, string $siswaId): ?LmsUjianSesi;

    public function startSesiUjian(string $ujianId, string $siswaId, ?string $ipAddress = null): LmsUjianSesi;

    public function saveJawabanSesi(string $sesiId, array $jawabanData): bool;

    public function finalizeSesiUjian(string $sesiId): ?LmsUjianSesi;

    public function getHasilUjian(string $ujianId): array;

    public function gradeEssayAnswer(string $jawabanId, float $poinDidapat, ?string $catatanGuru = null, ?string $guruId = null): bool;
}
