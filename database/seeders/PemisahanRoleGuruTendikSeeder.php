<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class PemisahanRoleGuruTendikSeeder extends Seeder
{
    /**
     * Memisahkan role akses antara Pendidik (Guru) dan Tenaga Kependidikan / Struktural (Tendik).
     * Sesuai arahan: Role Guru murni Guru, dan Tendik (Kepsek, TU, Yayasan, Divisi) murni Tendik.
     * Tidak boleh ada penggabungan tumpang-tindih peran dalam satu akun.
     */
    public function run(): void
    {
        $this->command?->info('Memulai pemisahan role akses Guru vs Tendik...');

        // 1. Akun Kepala Sekolah: Murni Kepala Sekolah
        $kepsekUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Kepala Sekolah', 'kepala_sekolah']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Superadmin', 'super_admin']))
            ->get();

        foreach ($kepsekUsers as $user) {
            // Lepas role guru/wali kelas
            $user->removeRole(['Guru', 'Guru Mata Pelajaran', 'Wali Kelas']);
            if (! $user->hasRole('Kepala Sekolah')) {
                $user->assignRole('Kepala Sekolah');
            }
        }
        $this->command?->info("Pemisahan selesai untuk {$kepsekUsers->count()} akun Kepala Sekolah.");

        // 2. Akun Tata Usaha: Murni Tata Usaha
        $tuUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Tata Usaha', 'tata_usaha', 'TU']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Superadmin', 'super_admin']))
            ->get();

        foreach ($tuUsers as $user) {
            $user->removeRole(['Guru', 'Guru Mata Pelajaran', 'Wali Kelas', 'Guru Tahfizh']);
            if (! $user->hasRole('Tata Usaha')) {
                $user->assignRole('Tata Usaha');
            }
        }
        $this->command?->info("Pemisahan selesai untuk {$tuUsers->count()} akun Tata Usaha.");

        // 3. Akun Divisi Pendidikan: Murni Divisi Pendidikan
        $divisiUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Divisi Pendidikan', 'divisi_pendidikan']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Superadmin', 'super_admin']))
            ->get();

        foreach ($divisiUsers as $user) {
            $user->removeRole(['Guru', 'Guru Mata Pelajaran', 'Wali Kelas']);
            if (! $user->hasRole('Divisi Pendidikan')) {
                $user->assignRole('Divisi Pendidikan');
            }
        }
        $this->command?->info("Pemisahan selesai untuk {$divisiUsers->count()} akun Divisi Pendidikan.");

        // 4. Akun Yayasan / Ketua Yayasan: Murni Yayasan
        $yayasanUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Yayasan', 'Ketua Yayasan', 'ketua_yayasan', 'pengurus_yayasan']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Superadmin', 'super_admin']))
            ->get();

        foreach ($yayasanUsers as $user) {
            $user->removeRole(['Guru', 'Guru Mata Pelajaran', 'Wali Kelas']);
        }
        $this->command?->info("Pemisahan selesai untuk {$yayasanUsers->count()} akun Yayasan.");

        // Verifikasi hasil
        $remainingOverlap = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Kepala Sekolah', 'Tata Usaha', 'Divisi Pendidikan', 'Yayasan', 'Ketua Yayasan']))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Guru', 'Guru Mata Pelajaran', 'Wali Kelas']))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Superadmin', 'super_admin']))
            ->count();

        $this->command?->info("Verifikasi selesai: sisa akun tumpang tindih = {$remainingOverlap}.");
    }
}
