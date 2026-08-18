<?php

namespace App\Repositories\Contracts;

use App\Models\Kelas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface KelasRepositoryInterface
 * Kontrak untuk repository data kelas / rombel.
 */
interface KelasRepositoryInterface
{
    /**
     * Mengambil daftar kelas berpaginasi berdasarkan filter, pencarian, dan pengurutan.
     */
    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    /**
     * Mengambil semua data kelas tanpa paginasi.
     */
    public function dapatkanSemua(array $filters = []): Collection;

    /**
     * Mengambil detail kelas berdasarkan ID.
     */
    public function cariBerdasarkanId(string $id): ?Kelas;

    /**
     * Membuat data kelas baru.
     */
    public function buat(array $data): Kelas;

    /**
     * Memperbarui data kelas.
     */
    public function perbarui(string $id, array $data): Kelas;

    /**
     * Menghapus data kelas (soft delete).
     */
    public function hapus(string $id): bool;

    /**
     * Memulihkan data kelas yang dihapus (soft delete restore).
     */
    public function pulihkan(string $id): bool;

    /**
     * Mengambil statistik ringkasan kelas (total kelas, aktif, wali terisi, kapasitas).
     */
    public function dapatkanStatistik(?array $allowedKelasIds = null): array;
}
