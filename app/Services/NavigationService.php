<?php

namespace App\Services;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\User;
use App\Support\RoleName;
use App\Support\RolePolicyDefaults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class NavigationService
{
    public const CACHE_VERSION_KEY = 'navigation_cache_version';

    public static function getCacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    public static function bumpCacheVersion(): void
    {
        $next = self::getCacheVersion() + 1;
        Cache::forever(self::CACHE_VERSION_KEY, $next);
    }

    /**
     * Return authorized route paths for the authenticated user.
     *
     * @return list<string>
     */
    public function getAccessiblePathsForUser(User $user, ?string $platform = 'web'): array
    {
        $modules = $this->getModulesForUser($user, $platform);

        return collect($modules)
            ->flatMap(fn (array $module) => collect($module['items'] ?? [])->pluck('path'))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Return authorized navigation modules and items for the given user,
     * filtered by platform and evaluated dynamically against DB role menu policies
     * and Spatie database permissions.
     */
    public function getModulesForUser(User $user, ?string $platform = 'web'): array
    {
        $platform = empty($platform) ? 'web' : strtolower($platform);
        $menuOverrideState = $this->resolveUserMenuOverrideState($user);

        $modules = AppModule::query()
            ->active()
            ->forPlatform($platform)
            ->with(['menus' => function ($query) use ($platform) {
                $query->active()
                    ->forPlatform($platform)
                    ->orderBy('sort_order', 'asc');
            }])
            ->orderBy('sort_order', 'asc')
            ->get();

        $result = [];

        foreach ($modules as $module) {
            // Sembunyikan modul Pengaturan untuk Kepala Sekolah KECUALI bila ada explicit allow DB di role_menu_access atau custom menu mode
            $hasPengaturanAllow = $module->menus->contains(fn ($m) => isset($menuOverrideState['allow'][$m->id]));
            if (
                $module->id === 'pengaturan'
                && ! $hasPengaturanAllow
                && ! $menuOverrideState['all_custom']
                && RoleName::userHasAny($user, ['kepala sekolah', 'kepala_sekolah', 'kepsek'])
            ) {
                continue;
            }

            $accessibleMenus = [];

            foreach ($module->menus as $menu) {
                if ($this->canAccessMenuWithState($user, $menu, $menuOverrideState)) {
                    $accessibleMenus[] = [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'path' => $menu->path,
                        'icon' => $menu->icon,
                        'sort_order' => $menu->sort_order,
                        'platform' => $menu->platform,
                    ];
                }
            }

            if (! empty($accessibleMenus)) {
                $result[] = [
                    'id' => $module->id,
                    'name' => $module->name,
                    'icon' => $module->icon,
                    'description' => $module->description,
                    'sort_order' => $module->sort_order,
                    'platform' => $module->platform,
                    'items' => $accessibleMenus,
                ];
            }
        }

        return $result;
    }

    /**
     * Preload explicit DB menu access overrides for user's roles.
     *
     * @return array{allow:array<string,bool>,deny:array<string,bool>,any_custom:bool,all_custom:bool,is_super_admin:bool,is_canonical_role:bool}
     */
    private function resolveUserMenuOverrideState(User $user): array
    {
        $policy = RolePolicyDefaults::resolveUserPolicy($user);
        $allow = [];
        $deny = [];

        if (Schema::hasTable('role_menu_access')) {
            $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->get();
            if ($roles->isNotEmpty()) {
                $roles->loadMissing('menuAccesses');
                foreach ($roles as $role) {
                    foreach ($role->menuAccesses as $access) {
                        if ($access->access_mode === 'allow') {
                            $allow[$access->app_menu_id] = true;
                        } elseif ($access->access_mode === 'deny') {
                            $deny[$access->app_menu_id] = true;
                        }
                    }
                }
            }
        }

        return [
            'allow' => $allow,
            'deny' => $deny,
            'any_custom' => (bool) ($policy['has_custom_menu_access'] ?? false),
            'all_custom' => (bool) ($policy['all_custom_menu_access'] ?? false),
            'is_super_admin' => (bool) ($policy['is_super_admin'] ?? false),
            'is_canonical_role' => (bool) ($policy['is_canonical_role'] ?? true),
            'portal_groups' => $policy['portal_groups'] ?? [],
        ];
    }

    /**
     * Check if a user can access a given menu based on dynamic DB role overrides,
     * Spatie permissions, and target_roles.
     */
    public function canAccessMenu(User $user, AppMenu $menu): bool
    {
        return $this->canAccessMenuWithState($user, $menu, $this->resolveUserMenuOverrideState($user));
    }

    private function canAccessMenuWithState(User $user, AppMenu $menu, array $state): bool
    {
        // 1. Explicit DB allow row ALWAYS wins for the menu
        if (isset($state['allow'][$menu->id])) {
            return true;
        }

        // 2. Strict custom menu mode: only menus explicitly checked in role_menu_access are shown
        if ($state['all_custom']) {
            return false;
        }

        // 3. Explicit DB deny row blocks access unless allowed by another role
        if (isset($state['deny'][$menu->id])) {
            return false;
        }

        // 4. Super Admin full access
        if ($state['is_super_admin']) {
            return true;
        }

        // 5. Pure custom/non-canonical roles evaluate strictly via DB portal_group + Spatie permissions
        if (! $state['is_canonical_role']) {
            $targetRoles = is_array($menu->target_roles) ? $menu->target_roles : [];
            $externalOnlyTokens = ['student', 'siswa', 'parent', 'orangtua', 'orang tua', 'alumni'];
            if (! empty($targetRoles)) {
                $normalizedTargets = array_map(fn ($r) => RoleName::normalize((string) $r), $targetRoles);
                $isExternalPortalMenu = count(array_diff($normalizedTargets, array_map([RoleName::class, 'normalize'], $externalOnlyTokens))) === 0;
                $userGroups = array_map([RoleName::class, 'normalize'], $state['portal_groups'] ?? []);
                $hasMatchingPortalGroup = count(array_intersect($normalizedTargets, $userGroups)) > 0;
                if ($isExternalPortalMenu && ! $hasMatchingPortalGroup && ! $this->userMatchesTargetRoles($user, $targetRoles)) {
                    return false;
                }
            }

            $requiredPermissions = is_array($menu->required_permissions) ? $menu->required_permissions : [];
            if (empty($requiredPermissions)) {
                return true;
            }

            foreach ($requiredPermissions as $permission) {
                try {
                    if ($user->checkPermissionTo($permission)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Permission not seeded
                }
            }

            return false;
        }

        return $this->evaluateLegacyCanAccessMenu($user, $menu);
    }

    protected function evaluateLegacyCanAccessMenu(User $user, AppMenu $menu): bool
    {
        $targetRoles = array_values(array_filter(
            (array) $menu->target_roles,
            fn ($role) => is_string($role) && trim($role) !== ''
        ));
        $hasExplicitTargetRoles = ! empty($targetRoles);

        if ($hasExplicitTargetRoles && ! $this->userMatchesTargetRoles($user, $targetRoles)) {
            return false;
        }

        // 1. Strict contextual scoping for dashboard module menus when target_roles is not explicitly set.
        // Even if Super Admin / Admin has full bypass permissions, the DASHBOARD accordion
        // should only show executive, administrative & monitoring dashboards for Super Admin & Admin,
        // rather than operational micro-workspaces of specific roles (Guru, Musyrif, Ortu, Siswa, dll).
        if ($menu->module_id === 'dashboard' && ! $hasExplicitTargetRoles) {
            return $this->canAccessDashboardMenuFallback($user, $menu);
        }

        // 2. Strict scoping untuk menu Jadwal Pelajaran, Jadwal Ujian Master, Presensi Gerbang, dan Master LMS Institusi:
        // Guru dilarang mengakses Master Jadwal, Presensi Gerbang, serta modul duplikat (Modul Ajar, Materi Master, Penugasan Master, Penilaian/Rapor Master).
        // Guru memusatkan KBM harian di Portal Guru (/portal-guru/workspace), sedangkan Kisi-kisi Ujian, Bank Soal, dan CBT Ujian tetap dapat diakses.
        if (in_array($menu->id, [
                'akademik-jadwal', 'akademik-jadwal-ujian', 'presensi-gerbang', 'presensi-device',
                'lms-modul-ajar', 'lms-materi', 'lms-diskusi', 'lms-penugasan', 'lms-pengumpulan-tugas', 'lms-penilaian', 'lms-rapor',
            ], true)
            || in_array($menu->path, [
                '/dashboard/akademik/jadwal',
                '/dashboard/jadwal-ujian',
                '/dashboard/jadwal-pelajaran',
                '/dashboard/rekap-absensi-gerbang',
                '/dashboard/absensi-gerbang',
                '/dashboard/lms/modul-ajar',
                '/dashboard/lms/materi',
                '/dashboard/lms/diskusi',
                '/dashboard/lms/penugasan',
                '/dashboard/lms/pengumpulan-tugas',
                '/dashboard/lms/penilaian',
                '/dashboard/lms/rapor',
            ], true)) {
            $isManagementOrAdmin = RoleName::userHasAny($user, [
                'super admin', 'superadmin', 'super_admin', 'admin',
                'kepala sekolah', 'kepala_sekolah', 'kepsek',
                'wakil kepala sekolah', 'waka', 'waka_kurikulum', 'waka_kesiswaan',
                'tata usaha', 'tu', 'staf_tu', 'staff tu', 'admin tu', 'operator',
                'divisi pendidikan', 'divisi_pendidikan',
                'yayasan', 'ketua yayasan', 'pengurus yayasan', 'sekretaris yayasan', 'bendahara yayasan',
            ]);
            $isGuru = RoleName::userHasAny($user, [
                'guru', 'guru mata pelajaran', 'guru pai', 'guru tahfizh', 'guru bk', 'wali kelas', 'walas',
            ]);

            if ($isGuru && ! $isManagementOrAdmin) {
                return false;
            }
        }

        // 3. Strict scoping untuk menu Mutabaah Master (/mutabaah, /dashboard/mutabaah):
        // Orang Tua dan Siswa dilarang mengakses Dashboard Mutabaah institusi.
        // Orang Tua mengakses mutabaah di Portal Orang Tua (/portal-orangtua), dan Siswa di Portal Siswa (/portal-siswa).
        if (in_array($menu->id, ['mutabaah-harian', 'mutabaah-agenda'], true)
            || in_array($menu->path, ['/mutabaah', '/dashboard/mutabaah', '/mutabaah/agenda', '/dashboard/mutabaah/agenda'], true)
            || str_starts_with((string) $menu->path, '/dashboard/mutabaah')) {
            $isParentOrStudent = RoleName::userHasAny($user, [
                'orang tua', 'orang_tua', 'orang-tua', 'orangtua', 'wali murid', 'parent',
                'siswa', 'student',
            ]);
            if ($isParentOrStudent) {
                return false;
            }
        }

        // 4. Strict scoping untuk menu LMS Master (/dashboard/lms/*, kisi-kisi, bank-soal, cbt ujian, penugasan, dll):
        // Orang Tua dan Siswa dilarang mengakses pengelolaan LMS institusi master.
        // Orang Tua mengakses kisi-kisi, ujian, tugas, materi via Portal Orang Tua (/portal-orangtua), dan Siswa via Portal Siswa (/portal-siswa).
        if (str_starts_with((string) $menu->path, '/dashboard/lms')
            || str_starts_with((string) $menu->id, 'lms-')
            || in_array($menu->id, [
                'lms-kisi-kisi', 'lms-bank-soal', 'lms-ujian', 'lms-ujian-online',
                'lms-penugasan', 'lms-materi', 'lms-modul-ajar', 'lms-diskusi',
                'lms-pengumpulan-tugas', 'lms-penilaian', 'lms-rapor',
            ], true)
            || in_array($menu->path, [
                '/dashboard/kisi-kisi-ujian', '/dashboard/bank-soal',
                '/dashboard/ujian-online', '/dashboard/penugasan',
                '/dashboard/pengumpulan-tugas', '/dashboard/materi-pembelajaran',
                '/dashboard/master-modul-semester',
            ], true)) {
            $isParentOrStudent = RoleName::userHasAny($user, [
                'orang tua', 'orang_tua', 'orang-tua', 'orangtua', 'wali murid', 'parent',
                'siswa', 'student',
            ]);
            if ($isParentOrStudent) {
                return false;
            }
        }

        // 5. Strict scoping untuk menu Jenis Unit Pendidikan (/dashboard/master-jenis-unit):
        // Kepala Sekolah dan Divisi Pendidikan dilarang mengelola jenis unit institusional yayasan (selaras web routes).
        if ($menu->id === 'master-jenis-unit' || $menu->path === '/dashboard/master-jenis-unit') {
            $isRestricted = RoleName::userHasAny($user, [
                'kepala sekolah', 'kepala_sekolah', 'kepsek',
                'divisi pendidikan', 'divisi_pendidikan', 'kepala bidang pendidikan',
            ]);
            if ($isRestricted) {
                return false;
            }
        }

        // 5b. Strict scoping selaras PermissionElement web routes:
        // - /dashboard/master-jabatan disembunyikan untuk Divisi Pendidikan & Guru
        // - /dashboard/lms/penilaian & /dashboard/lms/rapor disembunyikan untuk Yayasan
        // - /dashboard/absensi-gerbang disembunyikan untuk Yayasan & Divisi Pendidikan
        $isAdmin = RoleName::userHasAny($user, ['super admin', 'superadmin', 'super_admin', 'admin']);
        if (! $isAdmin) {
            if ($menu->id === 'master-jabatan' || $menu->path === '/dashboard/master-jabatan') {
                if (RoleName::userHasAny($user, [
                    'divisi pendidikan', 'divisi_pendidikan', 'kepala bidang pendidikan',
                    'guru', 'guru mata pelajaran', 'guru pai', 'guru tahfizh', 'guru bk', 'wali kelas', 'walas',
                ])) {
                    return false;
                }
            }

            if (in_array($menu->id, ['lms-penilaian', 'lms-rapor'], true)
                || in_array($menu->path, ['/dashboard/lms/penilaian', '/dashboard/lms/penilaian-rapor', '/dashboard/lms/rapor'], true)) {
                if (RoleName::userHasAny($user, [
                    'yayasan', 'ketua yayasan', 'ketua_yayasan', 'pengurus yayasan', 'pengurus_yayasan',
                    'sekretaris yayasan', 'sekretaris_yayasan', 'bendahara yayasan', 'bendahara_yayasan',
                ])) {
                    return false;
                }
            }

            if (in_array($menu->id, ['presensi-device'], true)
                || in_array($menu->path, ['/dashboard/absensi-gerbang', '/absensi-gerbang'], true)) {
                if (RoleName::userHasAny($user, [
                    'yayasan', 'ketua yayasan', 'ketua_yayasan', 'pengurus yayasan', 'pengurus_yayasan',
                    'sekretaris yayasan', 'sekretaris_yayasan', 'bendahara yayasan', 'bendahara_yayasan',
                    'divisi pendidikan', 'divisi_pendidikan', 'kepala bidang pendidikan',
                ])) {
                    return false;
                }
            }

            if ($menu->id === 'presensi-gerbang' || $menu->path === '/dashboard/rekap-absensi-gerbang') {
                if (RoleName::userHasAny($user, [
                    'yayasan', 'ketua yayasan', 'ketua_yayasan', 'pengurus yayasan', 'pengurus_yayasan',
                    'sekretaris yayasan', 'sekretaris_yayasan', 'bendahara yayasan', 'bendahara_yayasan',
                ])) {
                    return false;
                }
            }
        }

        // 6. Strict scoping untuk modul dan menu Pengaturan:
        // Role Kepala Sekolah dilarang mengakses modul maupun menu pengaturan institusi.
        if ($menu->module_id === 'pengaturan'
            || str_starts_with((string) $menu->path, '/dashboard/pengaturan')
            || in_array($menu->id, ['set-sekolah', 'master-opsi-bisnis', 'set-mobile', 'pengaturan-info-sekolah', 'pengaturan-menu-navigasi', 'pengaturan-pusat-data-sistem'], true)) {
            $isKepalaSekolah = RoleName::userHasAny($user, [
                'kepala sekolah', 'kepala_sekolah', 'kepsek',
            ]);
            if ($isKepalaSekolah) {
                return false;
            }
        }

        // 7. Evaluate database permissions via Laravel Gate for other modules
        // Gate::before natively handles Super Admin bypass.
        $requiredPermissions = (array) $menu->required_permissions;

        if (! empty($requiredPermissions)) {
            foreach ($requiredPermissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        // If no permissions required, menu is accessible to any authenticated user
        return empty($requiredPermissions);
    }

    /**
     * Role and permission based scoping for dashboard module items.
     */
    protected function canAccessDashboardMenuFallback(User $user, AppMenu $menu): bool
    {
        $isSuperAdminOrAdmin = RoleName::userHasAny($user, ['super admin', 'superadmin', 'super_admin', 'admin'])
            || (bool) ($user->is_superadmin ?? false);

        // 1. Super Admin & Admin View:
        // Only show executive, infrastructure, and monitoring dashboards
        if ($isSuperAdminOrAdmin) {
            return match ($menu->id) {
                'dash-super-admin',
                'dash-tahfizh',
                'dash-monitoring',
                'dash-pemantauan' => true,
                default => false,
            };
        }

        // 2. Strict scoping untuk dash-tahfizh: Didedikasikan untuk Guru Tahfizh, Kepsek, & Musyrif
        if ($menu->id === 'dash-tahfizh') {
            $isDenied = RoleName::userHasAny($user, [
                'yayasan', 'ketua yayasan', 'tata usaha', 'tu', 'operator',
                'orang tua', 'siswa',
            ]);
            if ($isDenied) {
                return false;
            }
        }

        // 3. Evaluate explicit required permissions first for non-superadmin users
        $requiredPermissions = (array) $menu->required_permissions;
        if (! empty($requiredPermissions)) {
            foreach ($requiredPermissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        // 4. Dedicated Single/Operational Roles (standard role-based fallback):
        return match ($menu->id) {
            'dash-super-admin' => false,
            'dash-yayasan', 'dash-profil-yayasan' => RoleName::userHasAny($user, [
                'yayasan', 'ketua yayasan', 'pengurus yayasan', 'sekretaris yayasan', 'bendahara yayasan',
            ]),
            'dash-kepsek' => RoleName::userHasAny($user, [
                'kepala sekolah', 'kepsek',
            ]),
            'dash-divisi' => RoleName::userHasAny($user, [
                'divisi pendidikan', 'kepala bidang pendidikan', 'divisi kurikulum', 'divisi kesiswaan', 'divisi bahasa', 'divisi program khusus',
            ]),
            'dash-waka-kurikulum' => RoleName::userHasAny($user, [
                'waka kurikulum', 'wakil kurikulum', 'wakil kepala sekolah',
            ]),
            'dash-tu' => RoleName::userHasAny($user, [
                'tata usaha', 'tu', 'operator',
            ]),
            'dash-tahfizh' => RoleName::userHasAny($user, [
                'guru tahfizh', 'super admin', 'admin', 'kepala sekolah', 'kepsek',
            ]),
            'portal-guru-workspace' => RoleName::userHasAny($user, [
                'guru', 'guru mata pelajaran', 'guru pai', 'guru bk', 'wali kelas', 'pembimbing',
            ]),
            'portal-musyrif-workspace' => RoleName::userHasAny($user, [
                'musyrif', 'musyrifah', 'musyrif / musyrifah', 'pengasuh', 'wali asrama', 'pembimbing asrama',
            ]),
            'dash-monitoring' => RoleName::userHasAny($user, [
                'kepala sekolah', 'kepsek', 'divisi pendidikan', 'kepala bidang pendidikan', 'yayasan', 'ketua yayasan',
            ]),
            'dash-pemantauan' => RoleName::userHasAny($user, [
                'divisi pendidikan', 'kepala bidang pendidikan', 'kepala sekolah', 'kepsek',
            ]),
            'portal-sivitas' => RoleName::userHasAny($user, [
                'orang tua', 'orangtua', 'wali murid', 'parent', 'siswa', 'student',
            ]),
            default => false,
        };
    }

    /**
     * Evaluate canonical and Spatie target_roles assigned to a menu item.
     */
    protected function userMatchesTargetRoles(User $user, array $targetRoles): bool
    {
        $aliasMap = [
            'student' => ['siswa', 'student'],
            'parent' => ['orang tua', 'orang_tua', 'orang-tua', 'orangtua', 'wali murid', 'parent'],
            'teacher' => ['guru', 'teacher', 'guru mata pelajaran', 'guru pai', 'guru tahfizh', 'guru bk', 'wali kelas', 'walas'],
            'principal' => ['kepala sekolah', 'kepala_sekolah', 'kepsek', 'principal', 'wakil kepala sekolah', 'waka', 'waka_kurikulum', 'waka_kesiswaan', 'waka kurikulum', 'wakil kurikulum'],
            'tu' => ['tata usaha', 'tu', 'staf_tu', 'staff tu', 'admin tu', 'operator', 'staff', 'staf'],
            'foundation' => ['yayasan', 'ketua yayasan', 'pengurus yayasan', 'sekretaris yayasan', 'bendahara yayasan', 'foundation'],
            'divisi' => ['divisi pendidikan', 'divisi_pendidikan', 'kepala bidang pendidikan', 'divisi kurikulum', 'divisi kesiswaan', 'divisi bahasa', 'divisi program khusus', 'divisi'],
            'musyrif' => ['musyrif', 'musyrifah', 'musyrif / musyrifah', 'pengasuh', 'wali asrama', 'pembimbing asrama', 'asrama', 'pembimbing'],
            'alumni' => ['alumni'],
            'super_admin' => ['super admin', 'superadmin', 'super_admin', 'admin'],
            'admin' => ['super admin', 'superadmin', 'super_admin', 'admin'],
        ];

        $expanded = [];
        $allowsSuperAdmin = false;

        foreach ($targetRoles as $role) {
            $trimmed = trim((string) $role);
            if ($trimmed === '') {
                continue;
            }
            $expanded[] = $trimmed;

            $key = strtolower(str_replace([' ', '-'], '_', $trimmed));
            if (isset($aliasMap[$key])) {
                foreach ($aliasMap[$key] as $alias) {
                    $expanded[] = $alias;
                }
            }
            if (in_array($key, ['super_admin', 'superadmin', 'admin'], true)) {
                $allowsSuperAdmin = true;
            }
        }

        if ($allowsSuperAdmin && (bool) ($user->is_superadmin ?? false)) {
            return true;
        }

        return RoleName::userHasAny($user, array_values(array_unique($expanded)));
    }

    /**
     * Check if any normalized role exists in needle list.
     */
    protected function hasAnyNormalizedRole(array $userRoles, array $allowedRoles): bool
    {
        return RoleName::matchesAny($userRoles, $allowedRoles);
    }
}
