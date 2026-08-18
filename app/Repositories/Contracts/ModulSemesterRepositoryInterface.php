<?php

namespace App\Repositories\Contracts;

use App\Models\ModulSemester;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ModulSemesterRepositoryInterface
{
    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function dapatkanSemua(array $filters = []): Collection;

    public function cariBerdasarkanId(string $id): ?ModulSemester;

    public function buat(array $data, array $details = []): ModulSemester;

    public function perbarui(string $id, array $data, array $details = []): ModulSemester;

    public function hapus(string $id): bool;

    public function pulihkan(string $id): bool;

    public function gantiStatus(string $id, string $status): ModulSemester;

    public function duplikasi(string $id): ModulSemester;

    public function dapatkanStatistik(): array;
}
