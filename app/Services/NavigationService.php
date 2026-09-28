<?php

namespace App\Services;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\User;

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
     * Role-based scoping for dashboard module items.
     */
    protected function canAccessDashboardMenuFallback(User $user, AppMenu $menu): bool
    {
        $roleNames = $user->getRoleNames()->map(fn ($r) => strtolower(trim($r)))->all();
        $isSuperAdminOrAdmin = in_array('super admin', $roleNames, true)
            || in_array('superadmin', $roleNames, true)
            || in_array('admin', $roleNames, true)
            || (bool) ($user->is_superadmin ?? false);

        // 1. Super Admin & Admin View:
        // Only show executive, infrastructure, and monitoring dashboards
        if ($isSuperAdminOrAdmin) {
            return match ($menu->id) {
                'dash-super-admin',
                'dash-monitoring',
                'dash-pemantauan' => true,
                default => false,
            };
        }

        // 2. Dedicated Single/Operational Roles:
        return match ($menu->id) {
            'dash-super-admin' => false,
            'dash-yayasan', 'dash-profil-yayasan' => $this->hasAnyNormalizedRole($roleNames, [
                'yayasan', 'ketua yayasan', 'pengurus yayasan', 'sekretaris yayasan', 'bendahara yayasan',
            ]),
            'dash-kepsek' => $this->hasAnyNormalizedRole($roleNames, [
                'kepala sekolah', 'kepsek',
            ]),
            'dash-divisi' => $this->hasAnyNormalizedRole($roleNames, [
                'divisi pendidikan', 'kepala bidang pendidikan', 'divisi kurikulum', 'divisi kesiswaan', 'divisi bahasa', 'divisi program khusus',
            ]),
            'dash-waka-kurikulum' => $this->hasAnyNormalizedRole($roleNames, [
                'waka kurikulum', 'wakil kurikulum', 'wakil kepala sekolah',
            ]),
            'dash-tu' => $this->hasAnyNormalizedRole($roleNames, [
                'tata usaha', 'tu', 'operator',
            ]),
            'dash-tahfizh' => $this->hasAnyNormalizedRole($roleNames, [
                'guru tahfizh',
            ]),
            'portal-guru-workspace' => $this->hasAnyNormalizedRole($roleNames, [
                'guru', 'guru mata pelajaran', 'guru pai', 'guru bk', 'wali kelas', 'pembimbing',
            ]),
            'portal-musyrif-workspace' => $this->hasAnyNormalizedRole($roleNames, [
                'musyrif', 'musyrifah', 'musyrif / musyrifah', 'pengasuh', 'wali asrama', 'pembimbing asrama',
            ]),
            'dash-monitoring' => $this->hasAnyNormalizedRole($roleNames, [
                'kepala sekolah', 'kepsek', 'divisi pendidikan', 'kepala bidang pendidikan', 'yayasan', 'ketua yayasan',
            ]),
            'dash-pemantauan' => $this->hasAnyNormalizedRole($roleNames, [
                'divisi pendidikan', 'kepala bidang pendidikan', 'kepala sekolah', 'kepsek',
            ]),
            'portal-sivitas' => $this->hasAnyNormalizedRole($roleNames, [
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
        foreach ($allowedRoles as $allowed) {
            if (in_array(strtolower(trim($allowed)), $userRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
