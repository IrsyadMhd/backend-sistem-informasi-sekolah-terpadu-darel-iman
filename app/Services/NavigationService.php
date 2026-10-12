<?php

namespace App\Services;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\User;
use App\Support\RoleName;

class NavigationService
{
    /**
     * Return authorized navigation modules and items for the given user,
     * filtered by platform and evaluated strictly against Spatie database permissions.
     */
    public function getModulesForUser(User $user, ?string $platform = 'web'): array
    {
        $platform = empty($platform) ? 'web' : strtolower($platform);

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
            $accessibleMenus = [];

            foreach ($module->menus as $menu) {
                if ($this->canAccessMenu($user, $menu)) {
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
     * Check if a user can access a given menu based on permissions.
     */
    public function canAccessMenu(User $user, AppMenu $menu): bool
    {
        // 1. Strict contextual scoping for dashboard module menus
        // Even if Super Admin / Admin has full bypass permissions, the DASHBOARD accordion
        // should only show executive, administrative & monitoring dashboards for Super Admin & Admin,
        // rather than operational micro-workspaces of specific roles (Guru, Musyrif, Ortu, Siswa, dll).
        if ($menu->module_id === 'dashboard') {
            return $this->canAccessDashboardMenuFallback($user, $menu);
        }

        // 2. Evaluate database permissions via Laravel Gate for other modules
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

        // 2. Evaluate explicit required permissions first for non-superadmin users
        $requiredPermissions = (array) $menu->required_permissions;
        if (! empty($requiredPermissions)) {
            foreach ($requiredPermissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        // 3. Dedicated Single/Operational Roles (standard role-based fallback):
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
     * Check if any normalized role exists in needle list.
     */
    protected function hasAnyNormalizedRole(array $userRoles, array $allowedRoles): bool
    {
        return RoleName::matchesAny($userRoles, $allowedRoles);
    }
}
