<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

final class RolePolicyDefaults
{
    public const SCOPE_PRIORITY = [
        'global' => 1,
        'cross_unit' => 2,
        'education_unit' => 3,
        'own_assignments' => 4,
        'linked_children' => 5,
        'self' => 6,
    ];

    public const VALID_SCOPES = [
        'global',
        'cross_unit',
        'education_unit',
        'own_assignments',
        'linked_children',
        'self',
    ];

    public const VALID_PORTAL_GROUPS = [
        'super_admin',
        'admin',
        'foundation',
        'education',
        'principal',
        'waka',
        'tu',
        'operator',
        'teacher',
        'musyrif',
        'parent',
        'student',
        'alumni',
        'staff',
    ];

    /**
     * Allow-listed landing routes assignable per role from database.
     * Any internal /dashboard* or /portal* path without open-redirect patterns is valid,
     * with these canonical presets surfaced in UI selectors.
     *
     * @var list<array{value:string,label:string,group:string}>
     */
    public const ROUTE_OPTIONS = [
        ['value' => '/dashboard', 'label' => 'Dashboard Utama (/dashboard)', 'group' => 'super_admin'],
        ['value' => '/dashboard/pemantauan', 'label' => 'Dashboard Pemantauan (/dashboard/pemantauan)', 'group' => 'admin'],
        ['value' => '/dashboard/yayasan', 'label' => 'Dashboard Yayasan (/dashboard/yayasan)', 'group' => 'foundation'],
        ['value' => '/dashboard/divisi-pendidikan', 'label' => 'Dashboard Divisi Pendidikan (/dashboard/divisi-pendidikan)', 'group' => 'education'],
        ['value' => '/dashboard/kepala-sekolah', 'label' => 'Dashboard Kepala Sekolah (/dashboard/kepala-sekolah)', 'group' => 'principal'],
        ['value' => '/dashboard/waka-kurikulum', 'label' => 'Dashboard Waka Kurikulum (/dashboard/waka-kurikulum)', 'group' => 'waka'],
        ['value' => '/dashboard/waka-kesiswaan', 'label' => 'Dashboard Waka Kesiswaan (/dashboard/waka-kesiswaan)', 'group' => 'waka'],
        ['value' => '/dashboard/tata-usaha', 'label' => 'Dashboard Tata Usaha (/dashboard/tata-usaha)', 'group' => 'tu'],
        ['value' => '/dashboard/operator', 'label' => 'Dashboard Operator (/dashboard/operator)', 'group' => 'operator'],
        ['value' => '/dashboard/wali-kelas', 'label' => 'Dashboard Wali Kelas (/dashboard/wali-kelas)', 'group' => 'teacher'],
        ['value' => '/dashboard/guru-tahfizh', 'label' => 'Dashboard Guru Tahfizh (/dashboard/guru-tahfizh)', 'group' => 'teacher'],
        ['value' => '/dashboard/guru-bk', 'label' => 'Dashboard Guru BK (/dashboard/guru-bk)', 'group' => 'teacher'],
        ['value' => '/portal-guru', 'label' => 'Portal Guru (/portal-guru)', 'group' => 'teacher'],
        ['value' => '/dashboard/musyrif', 'label' => 'Dashboard Musyrif Asrama (/dashboard/musyrif)', 'group' => 'musyrif'],
        ['value' => '/portal-orangtua', 'label' => 'Portal Orang Tua (/portal-orangtua)', 'group' => 'parent'],
        ['value' => '/portal-siswa', 'label' => 'Portal Siswa (/portal-siswa)', 'group' => 'student'],
        ['value' => '/portal/alumni', 'label' => 'Portal Alumni (/portal/alumni)', 'group' => 'alumni'],
    ];

    /**
     * Canonical policy defaults keyed by normalized role name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function canonicalMap(): array
    {
        return [
            'superadmin' => [
                'level' => 1,
                'scope_type' => 'global',
                'portal_group' => 'super_admin',
                'default_route' => '/dashboard',
                'is_read_only' => false,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => true,
                'is_system_protected' => true,
                'description' => 'Akses penuh sistem terpadu tanpa pembatasan unit maupun modul.',
            ],
            'admin' => [
                'level' => 1,
                'scope_type' => 'global',
                'portal_group' => 'admin',
                'default_route' => '/dashboard/pemantauan',
                'is_read_only' => false,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => true,
                'is_system_protected' => true,
                'description' => 'Admin operasional sistem untuk manajemen data global & konfigurasi.',
            ],
            'yayasan' => [
                'level' => 2,
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Pemantauan eksekutif lintas unit yayasan (mode baca).',
            ],
            'ketuayayasan' => [
                'level' => 2,
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Ketua Yayasan — pemantauan strategis lintas unit pendidikan (mode baca).',
            ],
            'pengurusyayasan' => [
                'level' => 2,
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => true,
                'is_system_protected' => false,
                'description' => 'Pengurus Yayasan — pemantauan lintas unit, SDM, & tata kelola yayasan.',
            ],
            'sekretarisyayasan' => [
                'level' => 2,
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Sekretaris Yayasan — pengawasan administrasi lintas unit (mode baca).',
            ],
            'bendaharayayasan' => [
                'level' => 2,
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Bendahara Yayasan — pengawasan laporan keuangan lintas unit (mode baca).',
            ],
            'kepalabidangpendidikan' => self::educationPolicy('Kepala Bidang Pendidikan — koordinasi & supervisi mutu pendidikan lintas unit.'),
            'divisipendidikan' => self::educationPolicy('Divisi Pendidikan — koordinasi & supervisi akademik unit binaan.'),
            'divisikurikulum' => self::educationPolicy('Divisi Kurikulum — koordinasi kurikulum & pembelajaran unit binaan.'),
            'divisikesiswaan' => self::educationPolicy('Divisi Kesiswaan — koordinasi pembinaan kesiswaan unit binaan.'),
            'divisibahasa' => self::educationPolicy('Divisi Bahasa — koordinasi program bahasa unit binaan.'),
            'divisiprogramkhusus' => self::educationPolicy('Divisi Program Khusus — koordinasi program unggulan & tahfizh.'),
            'kepalasekolah' => [
                'level' => 4,
                'scope_type' => 'education_unit',
                'portal_group' => 'principal',
                'default_route' => '/dashboard/kepala-sekolah',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Pimpinan satuan pendidikan — supervisi, approval, & hak akses pegawai unit sendiri.',
            ],
            'wakilkepalasekolah' => self::wakaPolicy('/dashboard/waka-kurikulum', 'Wakil Kepala Sekolah — koordinasi bidang akademik & kesiswaan unit.'),
            'wakakurikulum' => self::wakaPolicy('/dashboard/waka-kurikulum', 'Wakil Kepala Bidang Kurikulum — jadwal, RPP, & supervisi KBM.'),
            'wakilkurikulum' => self::wakaPolicy('/dashboard/waka-kurikulum', 'Wakil Kepala Bidang Kurikulum — jadwal, RPP, & supervisi KBM.'),
            'wakakesiswaan' => self::wakaPolicy('/dashboard/waka-kesiswaan', 'Wakil Kepala Bidang Kesiswaan — pembinaan & ketertiban peserta didik.'),
            'wakilkesiswaan' => self::wakaPolicy('/dashboard/waka-kesiswaan', 'Wakil Kepala Bidang Kesiswaan — pembinaan & ketertiban peserta didik.'),
            'wakilhumasdanprogramkhusus' => self::wakaPolicy('/dashboard/waka-kesiswaan', 'Wakil Humas & Program Khusus unit pendidikan.'),
            'tatausaha' => [
                'level' => 3,
                'scope_type' => 'education_unit',
                'portal_group' => 'tu',
                'default_route' => '/dashboard/tata-usaha',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Tata Usaha — administrasi harian siswa, pegawai, presensi gerbang, & keuangan unit.',
            ],
            'tu' => [
                'level' => 3,
                'scope_type' => 'education_unit',
                'portal_group' => 'tu',
                'default_route' => '/dashboard/tata-usaha',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Tata Usaha — administrasi harian & presensi unit pendidikan.',
            ],
            'operator' => [
                'level' => 3,
                'scope_type' => 'education_unit',
                'portal_group' => 'operator',
                'default_route' => '/dashboard/operator',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Operator Unit — pengelolaan data pokok sekolah & teknis operasional.',
            ],
            'walikelas' => self::teacherPolicy('/dashboard/wali-kelas', 'Wali Kelas — pembinaan rombongan belajar, absensi, & nilai rapor.'),
            'gurutahfizh' => self::teacherPolicy('/dashboard/guru-tahfizh', 'Guru Tahfizh — setoran hafalan, murojaah, & evaluasi tahfizh.'),
            'gurubk' => self::teacherPolicy('/dashboard/guru-bk', 'Guru BK — bimbingan konseling & pemantauan kedisiplinan siswa.'),
            'guru' => self::teacherPolicy('/portal-guru', 'Guru — pelaksanaan KBM, jurnal mengajar, presensi mapel, & penilaian.'),
            'gurumatapelajaran' => self::teacherPolicy('/portal-guru', 'Guru Mata Pelajaran — KBM kelas & penilaian mata pelajaran.'),
            'gurupai' => self::teacherPolicy('/portal-guru', 'Guru PAI — pembinaan keagamaan, pembelajaran, & mutabaah.'),
            'pembimbing' => self::teacherPolicy('/portal-guru', 'Pembimbing — pendampingan kegiatan peserta didik.'),
            'musyrif' => [
                'level' => 6,
                'scope_type' => 'own_assignments',
                'portal_group' => 'musyrif',
                'default_route' => '/dashboard/musyrif',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Musyrif Asrama — pembinaan ibadah 24 jam, kamar, tahfizh, & perizinan santri.',
            ],
            'musyrifah' => [
                'level' => 6,
                'scope_type' => 'own_assignments',
                'portal_group' => 'musyrif',
                'default_route' => '/dashboard/musyrif',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Musyrifah Asrama Putri — pembinaan asrama, ibadah, & perizinan santriwati.',
            ],
            'orangtua' => [
                'level' => 7,
                'scope_type' => 'linked_children',
                'portal_group' => 'parent',
                'default_route' => '/portal-orangtua',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Orang Tua / Wali Murid — pemantauan kehadiran, hafalan, perizinan, & tagihan anak.',
            ],
            'siswa' => [
                'level' => 8,
                'scope_type' => 'self',
                'portal_group' => 'student',
                'default_route' => '/portal-siswa',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Siswa / Santri — akses portal peserta didik pribadi.',
            ],
            'alumni' => [
                'level' => 9,
                'scope_type' => 'self',
                'portal_group' => 'alumni',
                'default_route' => '/portal/alumni',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
                'description' => 'Alumni — portal alumni & tracer study.',
            ],
        ];
    }

    private static function educationPolicy(string $description): array
    {
        return [
            'level' => 4,
            'scope_type' => 'cross_unit',
            'portal_group' => 'education',
            'default_route' => '/dashboard/divisi-pendidikan',
            'is_read_only' => false,
            'can_switch_unit' => true,
            'is_above_tu' => true,
            'can_manage_global_access' => false,
            'is_system_protected' => false,
            'description' => $description,
        ];
    }

    private static function wakaPolicy(string $route, string $description): array
    {
        return [
            'level' => 4,
            'scope_type' => 'education_unit',
            'portal_group' => 'waka',
            'default_route' => $route,
            'is_read_only' => false,
            'can_switch_unit' => false,
            'is_above_tu' => true,
            'can_manage_global_access' => false,
            'is_system_protected' => false,
            'description' => $description,
        ];
    }

    private static function teacherPolicy(string $route, string $description): array
    {
        return [
            'level' => 5,
            'scope_type' => 'own_assignments',
            'portal_group' => 'teacher',
            'default_route' => $route,
            'is_read_only' => false,
            'can_switch_unit' => false,
            'is_above_tu' => false,
            'can_manage_global_access' => false,
            'is_system_protected' => false,
            'description' => $description,
        ];
    }

    /**
     * Fallback policy for custom/unknown roles inferred by governance level.
     *
     * @return array<string, mixed>
     */
    public static function defaultForLevel(int $level): array
    {
        return match (true) {
            $level === 1 => [
                'scope_type' => 'global',
                'portal_group' => 'admin',
                'default_route' => '/dashboard/pemantauan',
                'is_read_only' => false,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => true,
                'is_system_protected' => false,
            ],
            $level === 2 => [
                'scope_type' => 'global',
                'portal_group' => 'foundation',
                'default_route' => '/dashboard/yayasan',
                'is_read_only' => true,
                'can_switch_unit' => true,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 3 => [
                'scope_type' => 'education_unit',
                'portal_group' => 'tu',
                'default_route' => '/dashboard/tata-usaha',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 4 => [
                'scope_type' => 'education_unit',
                'portal_group' => 'principal',
                'default_route' => '/dashboard/kepala-sekolah',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => true,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 5 => [
                'scope_type' => 'own_assignments',
                'portal_group' => 'staff',
                'default_route' => '/dashboard',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 6 => [
                'scope_type' => 'own_assignments',
                'portal_group' => 'musyrif',
                'default_route' => '/dashboard/musyrif',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 7 => [
                'scope_type' => 'linked_children',
                'portal_group' => 'parent',
                'default_route' => '/portal-orangtua',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level === 8 => [
                'scope_type' => 'self',
                'portal_group' => 'student',
                'default_route' => '/portal-siswa',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            $level >= 9 => [
                'scope_type' => 'self',
                'portal_group' => 'alumni',
                'default_route' => '/portal/alumni',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
            default => [
                'scope_type' => 'education_unit',
                'portal_group' => 'staff',
                'default_route' => '/dashboard',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'is_system_protected' => false,
            ],
        };
    }

    /**
     * Resolve policy defaults for a Role name and level.
     *
     * @return array<string, mixed>
     */
    public static function forRoleName(string $roleName, int $level = 5): array
    {
        $normalized = RoleName::normalize($roleName);
        $canonical = self::canonicalMap();

        if (isset($canonical[$normalized])) {
            return $canonical[$normalized];
        }

        return array_merge(self::defaultForLevel($level), [
            'level' => $level,
            'description' => null,
        ]);
    }

    /**
     * Read effective policy fields from a Role model, falling back cleanly
     * when optional columns are null.
     *
     * @return array<string, mixed>
     */
    public static function extractFromRole(Role $role): array
    {
        $defaults = self::forRoleName((string) $role->name, (int) ($role->level ?? 5));

        return [
            'name' => $role->name,
            'level' => (int) ($role->level ?? $defaults['level'] ?? 5),
            'scope_type' => $role->scope_type ?: $defaults['scope_type'],
            'portal_group' => $role->portal_group ?: $defaults['portal_group'],
            'default_route' => $role->default_route ?: $defaults['default_route'],
            'is_read_only' => $role->is_read_only !== null ? (bool) $role->is_read_only : (bool) $defaults['is_read_only'],
            'can_switch_unit' => $role->can_switch_unit !== null ? (bool) $role->can_switch_unit : (bool) $defaults['can_switch_unit'],
            'is_above_tu' => $role->is_above_tu !== null ? (bool) $role->is_above_tu : (bool) $defaults['is_above_tu'],
            'can_manage_global_access' => $role->can_manage_global_access !== null ? (bool) $role->can_manage_global_access : (bool) $defaults['can_manage_global_access'],
            'is_system_protected' => $role->is_system_protected !== null ? (bool) $role->is_system_protected : (bool) $defaults['is_system_protected'],
            'use_custom_menu_access' => (bool) ($role->use_custom_menu_access ?? false),
            'description' => $role->description ?? $defaults['description'] ?? null,
        ];
    }

    /**
     * Resolve merged access policy for a user across all their roles.
     *
     * @return array<string, mixed>
     */
    public static function resolveUserPolicy(User $user): array
    {
        $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->get();

        if ($roles->isEmpty()) {
            return [
                'role_level' => 99,
                'scope_type' => 'education_unit',
                'portal_group' => 'staff',
                'default_route' => '/dashboard',
                'is_read_only' => false,
                'can_switch_unit' => false,
                'is_above_tu' => false,
                'can_manage_global_access' => false,
                'has_custom_menu_access' => false,
                'is_super_admin' => false,
                'is_canonical_role' => false,
            ];
        }

        $extracted = $roles->map(fn ($r) => $r instanceof Role ? self::extractFromRole($r) : self::forRoleName((string) ($r->name ?? ''), (int) ($r->level ?? 5)))
            ->sortBy('level')
            ->values();

        $primary = $extracted->first();
        $canonicalMap = self::canonicalMap();

        $bestScope = $extracted->sortBy(fn ($p) => self::SCOPE_PRIORITY[$p['scope_type']] ?? 99)->first()['scope_type'];
        $isSuperAdmin = $extracted->contains(fn ($p) => $p['portal_group'] === 'super_admin');
        $hasWritableRole = $extracted->contains(fn ($p) => ! $p['is_read_only']);
        $allReadOnly = ! $hasWritableRole && $extracted->every(fn ($p) => $p['is_read_only']);
        $canSwitchUnit = $extracted->contains(fn ($p) => $p['can_switch_unit'] || in_array($p['scope_type'], ['global', 'cross_unit'], true));
        $isAboveTu = $extracted->contains(fn ($p) => $p['is_above_tu'] || in_array($p['scope_type'], ['global', 'cross_unit'], true));
        $canManageGlobalAccess = $isSuperAdmin || $extracted->contains(fn ($p) => $p['can_manage_global_access']);
        $hasCustomMenu = $extracted->contains(fn ($p) => $p['use_custom_menu_access']);
        $allCustomMenu = $extracted->every(fn ($p) => $p['use_custom_menu_access']);
        $hasCanonicalRole = $extracted->contains(fn ($p) => isset($canonicalMap[RoleName::normalize((string) $p['name'])]));

        return [
            'role_level' => (int) ($extracted->min('level') ?? 99),
            'scope_type' => $bestScope,
            'portal_group' => $primary['portal_group'] ?? 'staff',
            'default_route' => $extracted->first(fn ($p) => ! empty($p['default_route']))['default_route'] ?? '/dashboard',
            'is_read_only' => $allReadOnly,
            'can_switch_unit' => $canSwitchUnit,
            'is_above_tu' => $isAboveTu,
            'can_manage_global_access' => $canManageGlobalAccess,
            'has_custom_menu_access' => $hasCustomMenu,
            'all_custom_menu_access' => $allCustomMenu,
            'is_super_admin' => $isSuperAdmin,
            'is_canonical_role' => $hasCanonicalRole,
            'portal_groups' => $extracted->pluck('portal_group')->unique()->values()->all(),
        ];
    }

    public static function sanitizeRoute(?string $route): ?string
    {
        if (! is_string($route)) {
            return null;
        }

        $trimmed = trim($route);
        if ($trimmed === '') {
            return null;
        }

        if (! str_starts_with($trimmed, '/') || str_starts_with($trimmed, '//') || str_contains($trimmed, '://')) {
            return null;
        }

        if (! str_starts_with($trimmed, '/dashboard') && ! str_starts_with($trimmed, '/portal')) {
            return null;
        }

        return $trimmed;
    }
}
