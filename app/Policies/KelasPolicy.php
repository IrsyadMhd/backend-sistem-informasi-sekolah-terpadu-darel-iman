<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class KelasPolicy
 * Membatasi hak akses CRUD data kelas/rombel berdasarkan otorisasi pengguna.
 */
class KelasPolicy
{
    use HandlesAuthorization;

    /**
     * Tentukan apakah pengguna dapat melihat daftar kelas.
     */
    public function viewAny(User $user): bool
    {
        return true; // Pengguna terautentikasi dapat melihat daftar kelas
    }

    /**
     * Tentukan apakah pengguna dapat melihat detail kelas spesifik.
     */
    public function view(User $user, Kelas $kelas): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat membuat kelas baru.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data kelas.
     */
    public function update(User $user, Kelas $kelas): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat menghapus data kelas.
     */
    public function delete(User $user, Kelas $kelas): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat memulihkan kelas yang dihapus (soft delete).
     */
    public function restore(User $user, Kelas $kelas): bool
    {
        return true;
    }
}
