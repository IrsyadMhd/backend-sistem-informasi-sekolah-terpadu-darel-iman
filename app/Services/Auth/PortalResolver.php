<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Satu pemetaan role database ke workspace default.
 *
 * Route yang dikembalikan di sini adalah allow-listed; client tidak boleh
 * mengubahnya menjadi redirect URL arbitrary.
 */
class PortalResolver
{
    /** @var list<array{key:string,label:string,route:string,roles:list<string>}> */
    private const WORKSPACES = [
        ['key' => 'admin', 'label' => 'Admin', 'route' => '/dashboard/pemantauan', 'roles' => ['Admin']],
        ['key' => 'super_admin', 'label' => 'Super Admin', 'route' => '/dashboard', 'roles' => ['Super Admin', 'Superadmin', 'super_admin']],
        ['key' => 'foundation', 'label' => 'Yayasan', 'route' => '/dashboard/yayasan', 'roles' => ['Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'ketua_yayasan', 'pengurus_yayasan', 'sekretaris_yayasan', 'bendahara_yayasan']],
        ['key' => 'education', 'label' => 'Divisi Pendidikan', 'route' => '/dashboard/divisi-pendidikan', 'roles' => ['Kepala Bidang Pendidikan', 'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus', 'divisi_pendidikan']],
        ['key' => 'principal', 'label' => 'Kepala Sekolah', 'route' => '/dashboard/kepala-sekolah', 'roles' => ['Kepala Sekolah', 'kepala_sekolah', 'kepsek']],
        ['key' => 'waka_kurikulum', 'label' => 'Waka Kurikulum', 'route' => '/dashboard/waka-kurikulum', 'roles' => ['Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Waka Kurikulum', 'waka_kurikulum']],
        ['key' => 'waka_kesiswaan', 'label' => 'Waka Kesiswaan', 'route' => '/dashboard/waka-kesiswaan', 'roles' => ['Wakil Kesiswaan', 'Waka Kesiswaan', 'waka_kesiswaan']],
        ['key' => 'operator', 'label' => 'Operator', 'route' => '/dashboard/operator', 'roles' => ['Operator', 'operator']],
        ['key' => 'tu', 'label' => 'Tata Usaha', 'route' => '/dashboard/tata-usaha', 'roles' => ['Tata Usaha', 'TU', 'tu', 'tata_usaha']],
        ['key' => 'homeroom', 'label' => 'Wali Kelas', 'route' => '/dashboard/wali-kelas', 'roles' => ['Wali Kelas', 'walas', 'wali_kelas']],
        ['key' => 'tahfizh', 'label' => 'Guru Tahfizh', 'route' => '/dashboard/guru-tahfizh', 'roles' => ['Guru Tahfizh', 'guru_tahfizh']],
        ['key' => 'musyrif', 'label' => 'Musyrif', 'route' => '/dashboard/musyrif', 'roles' => ['Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah']],
        ['key' => 'guru_bk', 'label' => 'Guru BK', 'route' => '/dashboard/guru-bk', 'roles' => ['Guru BK', 'guru_bk']],
        ['key' => 'teacher', 'label' => 'Guru', 'route' => '/portal-guru', 'roles' => ['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran', 'Guru PAI', 'Pembimbing']],
        ['key' => 'parent', 'label' => 'Orang Tua', 'route' => '/portal-orangtua', 'roles' => ['Orang Tua', 'orang_tua', 'Orangtua', 'Wali Murid', 'parent']],
        ['key' => 'student', 'label' => 'Siswa', 'route' => '/portal-siswa', 'roles' => ['Siswa', 'siswa', 'student']],
        ['key' => 'alumni', 'label' => 'Alumni', 'route' => '/portal/alumni', 'roles' => ['Alumni', 'alumni']],
    ];

    /**
     * @return array{default_portal:string,default_redirect:string,available_workspaces:list<array{key:string,label:string,route:string}>}
     */
    public function resolve(User $user): array
    {
        $isRestricted = !$this->hasAnyRole($user, ['Super Admin', 'Superadmin', 'super_admin'])
            && $this->hasAnyRole($user, [
                'Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
                'ketua_yayasan', 'pengurus_yayasan', 'sekretaris_yayasan', 'bendahara_yayasan',
                'Kepala Sekolah', 'kepala_sekolah', 'kepsek'
            ]);

        $matches = collect(self::WORKSPACES)
            ->filter(fn (array $workspace) => $this->hasAnyRole($user, $workspace['roles']))
            ->when($isRestricted, fn ($collection) => $collection->reject(
                fn (array $workspace) => in_array($workspace['key'], ['teacher', 'musyrif'], true)
            ))
            ->values();

        if ($matches->isEmpty() && $user->employee()->exists()) {
            $matches = collect([
                ['key' => 'employee', 'label' => 'Pegawai', 'route' => '/dashboard', 'roles' => []],
            ]);
        }

        $default = $matches->first() ?? [
            'key' => 'admin',
            'label' => 'Admin',
            'route' => '/dashboard',
            'roles' => [],
        ];

        return [
            'default_portal' => $default['key'],
            'default_redirect' => $default['route'],
            'available_workspaces' => $matches
                ->map(fn (array $workspace) => [
                    'key' => $workspace['key'],
                    'label' => $workspace['label'],
                    'route' => $workspace['route'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        $actual = $user->getRoleNames()
            ->map(fn (string $role) => $this->normalizeRole($role));

        return collect($roles)
            ->map(fn (string $role) => $this->normalizeRole($role))
            ->intersect($actual)
            ->isNotEmpty();
    }

    private function normalizeRole(string $role): string
    {
        return strtolower((string) preg_replace('/[\s_-]+/', '', $role));
    }
}
