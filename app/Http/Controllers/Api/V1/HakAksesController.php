<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\NavigationService;
use App\Support\RolePolicyDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class HakAksesController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    // ─────────────────────────────────────────────────
    // ROLE CRUD & DINAMIS POLICY OPTIONS
    // ─────────────────────────────────────────────────

    /**
     * Daftar opsi konfigurasi dinamis Role (Menu Sidebar, Scope, Portal Group, Default Route).
     */
    public function navigationOptions(Request $request): JsonResponse
    {
        $this->assertCanViewGlobalRoleAccess($request);

        $modules = AppModule::query()
            ->active()
            ->forPlatform('web')
            ->with(['menus' => fn ($q) => $q->active()->forPlatform('web')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'icon' => $m->icon,
                'menus' => $m->menus->map(fn ($menu) => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'path' => $menu->path,
                    'required_permissions' => $menu->required_permissions ?? [],
                ])->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'modules' => $modules,
                'scope_options' => [
                    ['value' => 'global', 'label' => 'Global (Semua Unit Pendidikan)', 'description' => 'Mengakses seluruh unit pendidikan & data lintas unit.'],
                    ['value' => 'cross_unit', 'label' => 'Lintas Unit Terpilih (Divisi)', 'description' => 'Mengakses unit pendidikan binaan sesuai divisi/penugasan.'],
                    ['value' => 'education_unit', 'label' => 'Unit Pendidikan Sendiri', 'description' => 'Terkunci hanya pada unit pendidikan pegawai terkait.'],
                    ['value' => 'own_assignments', 'label' => 'Penugasan / Kelas Sendiri', 'description' => 'Hanya kelas, mapel, halaqah, atau kamar yang diampu.'],
                    ['value' => 'linked_children', 'label' => 'Data Anak Terhubung (Wali)', 'description' => 'Hanya data anak/siswa yang terhubung sebagai wali.'],
                    ['value' => 'self', 'label' => 'Data Pribadi Sendiri', 'description' => 'Hanya data akun milik sendiri (Siswa/Alumni).'],
                ],
                'portal_group_options' => [
                    ['value' => 'staff', 'label' => 'Staf / Peran Kustom Internal'],
                    ['value' => 'super_admin', 'label' => 'Super Admin Sistem'],
                    ['value' => 'admin', 'label' => 'Admin Sistem'],
                    ['value' => 'foundation', 'label' => 'Yayasan (Eksekutif / Monitoring)'],
                    ['value' => 'education', 'label' => 'Divisi Pendidikan'],
                    ['value' => 'principal', 'label' => 'Kepala Sekolah'],
                    ['value' => 'waka', 'label' => 'Wakil Kepala Sekolah (Waka)'],
                    ['value' => 'tu', 'label' => 'Tata Usaha (TU)'],
                    ['value' => 'operator', 'label' => 'Operator Unit'],
                    ['value' => 'teacher', 'label' => 'Guru / Tenaga Pendidik'],
                    ['value' => 'musyrif', 'label' => 'Musyrif / Kepengasuhan Asrama'],
                    ['value' => 'parent', 'label' => 'Orang Tua / Wali Murid'],
                    ['value' => 'student', 'label' => 'Siswa / Santri'],
                    ['value' => 'alumni', 'label' => 'Alumni'],
                ],
                'route_options' => RolePolicyDefaults::ROUTE_OPTIONS,
            ],
        ]);
    }

    /**
     * Daftar semua Role beserta jumlah permission, user, dan kebijakan dinamis.
     */
    public function indexRoles(Request $request): JsonResponse
    {
        $this->assertCanViewGlobalRoleAccess($request);
        $search = $request->get('search', '');

        $query = Role::withCount(['permissions', 'users'])
            ->orderBy('level')
            ->orderBy('name');

        if ($search) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('name', $likeOp, "%{$search}%");
        }

        $roles = $query->with(['permissions', 'menuAccesses'])->get()
            ->map(fn ($r) => $this->formatRolePayload($r, true));

        return response()->json([
            'success' => true,
            'data' => $roles,
            'total' => $roles->count(),
        ]);
    }

    /**
     * Simpan Role baru beserta kebijakan akses dinamis & akses menu sidebar.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $validated = $this->validateRolePayload($request);

        $user = $request->user();
        $isSuperAdmin = $user && ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->hasRole('Superadmin'));

        $level = (int) ($validated['level'] ?? 5);
        $defaults = RolePolicyDefaults::forRoleName($validated['name'], $level);
        $scopeType = $validated['scope_type'] ?? $defaults['scope_type'];
        $portalGroup = $validated['portal_group'] ?? $defaults['portal_group'];
        $canManageGlobalAccess = array_key_exists('can_manage_global_access', $validated)
            ? (bool) $validated['can_manage_global_access']
            : (bool) $defaults['can_manage_global_access'];

        if (($level <= 2 || $scopeType === 'global' || $canManageGlobalAccess || $portalGroup === 'super_admin') && ! $isSuperAdmin) {
            abort(403, 'Hanya Super Admin yang berhak membuat peran dengan tingkat tata kelola tinggi atau kewenangan global.');
        }

        try {
            $role = DB::transaction(function () use ($validated, $level, $defaults, $scopeType, $portalGroup, $canManageGlobalAccess) {
                $defaultRoute = array_key_exists('default_route', $validated)
                    ? RolePolicyDefaults::sanitizeRoute($validated['default_route'])
                    : ($defaults['default_route'] ?? '/dashboard');

                $role = Role::create([
                    'name' => $validated['name'],
                    'guard_name' => $validated['guard_name'] ?? 'web',
                    'level' => $level,
                    'description' => $validated['description'] ?? $defaults['description'] ?? null,
                    'scope_type' => $scopeType,
                    'portal_group' => $portalGroup,
                    'default_route' => $defaultRoute ?: '/dashboard',
                    'is_read_only' => (bool) ($validated['is_read_only'] ?? $defaults['is_read_only'] ?? false),
                    'can_switch_unit' => (bool) ($validated['can_switch_unit'] ?? $defaults['can_switch_unit'] ?? false),
                    'is_above_tu' => (bool) ($validated['is_above_tu'] ?? $defaults['is_above_tu'] ?? false),
                    'can_manage_global_access' => $canManageGlobalAccess,
                    'use_custom_menu_access' => (bool) ($validated['use_custom_menu_access'] ?? false),
                ]);

                if (! empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }

                $this->syncRoleMenuAccess(
                    $role,
                    $validated['allowed_menu_ids'] ?? [],
                    $validated['denied_menu_ids'] ?? []
                );

                return $role;
            });

            $this->flushAccessCaches();

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil ditambahkan.",
                'data' => $this->formatRolePayload($role->fresh(['permissions', 'menuAccesses'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail Role beserta permissions & akses menu dinamis.
     */
    public function showRole(Request $request, string $id): JsonResponse
    {
        $this->assertCanViewGlobalRoleAccess($request);
        $role = Role::with(['permissions', 'menuAccesses'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRolePayload($role),
        ]);
    }

    /**
     * Perbarui data Role beserta kebijakan akses dinamis & akses menu sidebar.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $role = Role::findOrFail($id);

        $validated = $this->validateRolePayload($request, $role->id);

        $user = $request->user();
        $isSuperAdmin = $user && ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->hasRole('Superadmin'));

        $protectedNames = ['Super Admin', 'Admin'];
        if ((in_array($role->name, $protectedNames, true) || $role->is_system_protected) && $role->name !== $validated['name']) {
            return response()->json([
                'success' => false,
                'message' => "Nama role sistem '{$role->name}' dilindungi dan tidak dapat diubah.",
            ], 422);
        }

        if ($role->name === 'Super Admin' && isset($validated['level']) && (int) $validated['level'] !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Level untuk peran Super Admin harus tetap tingkat 1.',
            ], 422);
        }

        $targetLevel = isset($validated['level']) ? (int) $validated['level'] : (int) ($role->level ?? 5);
        $targetScope = $validated['scope_type'] ?? $role->scope_type;
        $targetPortalGroup = $validated['portal_group'] ?? $role->portal_group;
        $targetGlobalMgr = array_key_exists('can_manage_global_access', $validated)
            ? (bool) $validated['can_manage_global_access']
            : (bool) $role->can_manage_global_access;

        if (
            ! $isSuperAdmin
            && (
                $targetLevel <= 2
                || $targetScope === 'global'
                || $targetGlobalMgr
                || $targetPortalGroup === 'super_admin'
            )
        ) {
            abort(403, 'Hanya Super Admin yang berhak menetapkan tingkatan hierarki atau kewenangan global tersebut.');
        }

        try {
            DB::transaction(function () use ($role, $validated) {
                $role->name = $validated['name'];
                if (isset($validated['guard_name'])) {
                    $role->guard_name = $validated['guard_name'];
                }
                if (isset($validated['level'])) {
                    $role->level = (int) $validated['level'];
                }
                if (array_key_exists('description', $validated)) {
                    $role->description = $validated['description'];
                }
                if (isset($validated['scope_type'])) {
                    $role->scope_type = $validated['scope_type'];
                }
                if (isset($validated['portal_group'])) {
                    $role->portal_group = $validated['portal_group'];
                }
                if (array_key_exists('default_route', $validated)) {
                    $sanitizedRoute = RolePolicyDefaults::sanitizeRoute($validated['default_route']);
                    $role->default_route = $sanitizedRoute ?: $role->default_route;
                }
                if (array_key_exists('is_read_only', $validated)) {
                    $role->is_read_only = $role->name === 'Super Admin' ? false : (bool) $validated['is_read_only'];
                }
                if (array_key_exists('can_switch_unit', $validated)) {
                    $role->can_switch_unit = (bool) $validated['can_switch_unit'];
                }
                if (array_key_exists('is_above_tu', $validated)) {
                    $role->is_above_tu = (bool) $validated['is_above_tu'];
                }
                if (array_key_exists('can_manage_global_access', $validated)) {
                    $role->can_manage_global_access = $role->name === 'Super Admin' ? true : (bool) $validated['can_manage_global_access'];
                }
                if (array_key_exists('use_custom_menu_access', $validated)) {
                    $role->use_custom_menu_access = (bool) $validated['use_custom_menu_access'];
                }
                $role->save();

                $role->syncPermissions($validated['permissions'] ?? []);

                if (array_key_exists('allowed_menu_ids', $validated) || array_key_exists('denied_menu_ids', $validated)) {
                    $this->syncRoleMenuAccess(
                        $role,
                        $validated['allowed_menu_ids'] ?? [],
                        $validated['denied_menu_ids'] ?? []
                    );
                }
            });

            $this->flushAccessCaches();

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil diperbarui.",
                'data' => $this->formatRolePayload($role->fresh(['permissions', 'menuAccesses'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus Role.
     */
    public function destroyRole(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $role = Role::findOrFail($id);

        $protectedCanonicalRoles = [
            'Super Admin',
            'Admin',
            'Ketua Yayasan',
            'Pengurus Yayasan',
            'Sekretaris Yayasan',
            'Bendahara Yayasan',
            'Operator',
            'Tata Usaha',
            'Kepala Sekolah',
            'Guru',
            'Musyrif',
            'Orang Tua',
            'Siswa',
            'Alumni',
        ];

        if ((bool) $role->is_system_protected || in_array($role->name, $protectedCanonicalRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => "Role sistem '{$role->name}' dilindungi dan tidak dapat dihapus.",
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => "Role '{$role->name}' tidak dapat dihapus karena masih digunakan oleh {$role->users()->count()} pengguna.",
            ], 422);
        }

        $name = $role->name;
        $role->delete();
        $this->flushAccessCaches();

        return response()->json([
            'success' => true,
            'message' => "Role '{$name}' berhasil dihapus.",
        ]);
    }

    private function validateRolePayload(Request $request, ?int $ignoreRoleId = null): array
    {
        $uniqueRule = Rule::unique('roles', 'name');
        if ($ignoreRoleId !== null) {
            $uniqueRule->ignore($ignoreRoleId);
        }

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', $uniqueRule],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'level' => ['nullable', 'integer', 'between:1,9'],
            'description' => ['nullable', 'string', 'max:500'],
            'scope_type' => ['nullable', 'string', Rule::in(RolePolicyDefaults::VALID_SCOPES)],
            'portal_group' => ['nullable', 'string', Rule::in(RolePolicyDefaults::VALID_PORTAL_GROUPS)],
            'default_route' => ['nullable', 'string', 'max:120'],
            'is_read_only' => ['nullable', 'boolean'],
            'can_switch_unit' => ['nullable', 'boolean'],
            'is_above_tu' => ['nullable', 'boolean'],
            'can_manage_global_access' => ['nullable', 'boolean'],
            'use_custom_menu_access' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'allowed_menu_ids' => ['nullable', 'array'],
            'allowed_menu_ids.*' => ['string', 'exists:app_menus,id'],
            'denied_menu_ids' => ['nullable', 'array'],
            'denied_menu_ids.*' => ['string', 'exists:app_menus,id'],
        ]);
    }

    private function syncRoleMenuAccess(Role $role, array $allowedMenuIds, array $deniedMenuIds): void
    {
        RoleMenuAccess::query()->where('role_id', $role->id)->delete();

        $rows = [];
        $now = now();
        $allowedSet = [];

        foreach (array_unique($allowedMenuIds) as $menuId) {
            if (! is_string($menuId) || $menuId === '') {
                continue;
            }
            $allowedSet[$menuId] = true;
            $rows[] = [
                'role_id' => $role->id,
                'app_menu_id' => $menuId,
                'access_mode' => 'allow',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_unique($deniedMenuIds) as $menuId) {
            if (! is_string($menuId) || $menuId === '' || isset($allowedSet[$menuId])) {
                continue;
            }
            $rows[] = [
                'role_id' => $role->id,
                'app_menu_id' => $menuId,
                'access_mode' => 'deny',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            RoleMenuAccess::query()->insert($rows);
        }
    }

    private function formatRolePayload(Role $role, bool $includeCounts = false): array
    {
        $policy = RolePolicyDefaults::extractFromRole($role);
        $menuAccesses = $role->relationLoaded('menuAccesses') ? $role->menuAccesses : $role->menuAccesses()->get();

        $payload = [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'level' => $policy['level'],
            'description' => $policy['description'],
            'scope_type' => $policy['scope_type'],
            'portal_group' => $policy['portal_group'],
            'default_route' => $policy['default_route'],
            'is_read_only' => $policy['is_read_only'],
            'can_switch_unit' => $policy['can_switch_unit'],
            'is_above_tu' => $policy['is_above_tu'],
            'can_manage_global_access' => $policy['can_manage_global_access'],
            'is_system_protected' => $policy['is_system_protected'],
            'use_custom_menu_access' => $policy['use_custom_menu_access'],
            'permissions' => $role->permissions->pluck('name')->values(),
            'allowed_menu_ids' => $menuAccesses->where('access_mode', 'allow')->pluck('app_menu_id')->values(),
            'denied_menu_ids' => $menuAccesses->where('access_mode', 'deny')->pluck('app_menu_id')->values(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];

        if ($includeCounts) {
            $payload['jumlah_izin'] = $role->permissions_count ?? $role->permissions->count();
            $payload['jumlah_pengguna'] = $role->users_count ?? 0;
        }

        return $payload;
    }

    private function flushAccessCaches(): void
    {
        NavigationService::bumpCacheVersion();
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            // Ignore cache driver edge cases
        }
    }

    // ─────────────────────────────────────────────────
    // PERMISSION CRUD
    // ─────────────────────────────────────────────────

    /**
     * Daftar semua Permission, dikelompokkan berdasarkan modul.
     */
    public function indexPermissions(Request $request): JsonResponse
    {
        $this->assertCanViewGlobalRoleAccess($request);
        $search = $request->get('search', '');

        $query = Permission::orderBy('name');

        if ($search) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('name', $likeOp, "%{$search}%");
        }

        $permissions = $query->get();

        // Kelompokkan berdasarkan prefix modul (sebelum titik)
        $grouped = $permissions->groupBy(fn ($p) => explode('.', $p->name)[0] ?? 'lainnya')
            ->map(fn ($items, $modul) => [
                'modul' => $modul,
                'total' => $items->count(),
                'izin' => $items->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'guard_name' => $p->guard_name,
                ]),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $grouped,
            'total' => $permissions->count(),
            'flat_list' => $permissions->pluck('name'),
        ]);
    }

    /**
     * Tambah Permission baru.
     */
    public function storePermission(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $permission = Permission::create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'] ?? 'web',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Izin akses '{$permission->name}' berhasil ditambahkan.",
                'data' => $permission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan permission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus Permission.
     */
    public function destroyPermission(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $permission = Permission::findOrFail($id);
        $name = $permission->name;
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => "Izin akses '{$name}' berhasil dihapus.",
        ]);
    }

    /**
     * Ringkasan statistik hak akses.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->assertCanViewAccessManagement($request);
        $scopedEmployeesCount = $this->accessScope->accessibleEmployees($request->user())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_role' => Role::count(),
                'total_permission' => Permission::count(),
                'total_modul' => Permission::get()->groupBy(fn ($p) => explode('.', $p->name)[0])->count(),
                'role_tanpa_user' => Role::withCount('users')->get()->filter(fn ($r) => $r->users_count === 0)->count(),
                'total_pegawai_unit' => $scopedEmployeesCount,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────
    // PEGAWAI HAK AKSES (MENARIK DATA PEGAWAI BERDASARKAN UNIT)
    // ─────────────────────────────────────────────────

    /**
     * Daftar pegawai beserta role & permission yang dimiliki (menarik data pegawai).
     */
    public function indexPegawaiHakAkses(Request $request): JsonResponse
    {
        $this->assertCanViewAccessManagement($request);
        $search = $request->get('search', '');
        $unitId = $request->get('unit_id', '');
        $jabatanId = $request->get('jabatan_id', '');
        $status = $request->get('status', '');

        if ($unitId) {
            $this->accessScope->assertEducationUnitAccess($request->user(), (string) $unitId);
        }

        $query = $this->accessScope->accessibleEmployees($request->user())
            ->with(['unit', 'position', 'user', 'user.roles', 'user.permissions', 'role'])
            ->orderBy('nama_lengkap');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('nama_lengkap', $likeOp, "%{$search}%")
                    ->orWhere('niy', $likeOp, "%{$search}%")
                    ->orWhere('nik', $likeOp, "%{$search}%")
                    ->orWhere('email', $likeOp, "%{$search}%");
            });
        }

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        if ($jabatanId) {
            $query->where('jabatan_id', $jabatanId);
        }

        if ($status === 'has_user') {
            $query->whereNotNull('user_id');
        } elseif ($status === 'no_user') {
            $query->whereNull('user_id');
        }

        $role = $request->get('role', '');
        $roles = $request->get('roles', []);
        if (is_string($roles) && !empty($roles)) {
            $roles = array_filter(array_map('trim', explode(',', $roles)));
        }

        if ($role) {
            $query->where(function ($q) use ($role) {
                $q->whereHas('role', function ($rq) use ($role) {
                    $rq->where('name', $role);
                })->orWhereHas('user.roles', function ($urq) use ($role) {
                    $urq->where('name', $role);
                });
            });
        } elseif (!empty($roles)) {
            $query->where(function ($q) use ($roles) {
                $q->whereHas('role', function ($rq) use ($roles) {
                    $rq->whereIn('name', $roles);
                })->orWhereHas('user.roles', function ($urq) use ($roles) {
                    $urq->whereIn('name', $roles);
                });
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $pegawai = $query->paginate($perPage);

        $mappedData = collect($pegawai->items())->map(function ($emp) {
            $user = $emp->user;
            $roles = $user ? $user->roles->pluck('name') : collect([]);
            if ($roles->isEmpty() && $emp->role) {
                $roles = collect([$emp->role->name]);
            }

            $directPermissions = $user ? $user->permissions->pluck('name') : collect([]);
            $allPermissions = $user ? $user->getAllPermissions()->pluck('name') : collect([]);

            return [
                'id' => $emp->id,
                'niy' => $emp->niy,
                'nik' => $emp->nik,
                'nama_lengkap' => $emp->nama_lengkap,
                'email' => $emp->email,
                'no_hp' => $emp->no_hp,
                'unit' => $emp->unit ? [
                    'id' => $emp->unit->id,
                    'nama' => $emp->unit->nama_unit ?? $emp->unit->name ?? '-',
                ] : null,
                'position' => $emp->position ? [
                    'id' => $emp->position->id,
                    'nama' => $emp->position->name ?? $emp->position->nama_jabatan ?? '-',
                ] : null,
                'user_id' => $emp->user_id,
                'has_user' => (bool) $user,
                'user_email' => $user ? $user->email : null,
                'roles' => $roles,
                'primary_role' => $roles->first() ?? 'Belum Ada Role',
                'direct_permissions' => $directPermissions,
                'all_permissions' => $allPermissions,
                'status_pegawai' => $emp->status_pegawai,
                'status' => $emp->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mappedData,
            'meta' => [
                'current_page' => $pegawai->currentPage(),
                'last_page' => $pegawai->lastPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
            ],
        ]);
    }

    /**
     * Penetapan / Ubah Role & Permission Pegawai (serta auto-create akun user jika belum ada).
     */
    public function assignPegawaiRole(Request $request, string $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'role_name' => ['required', 'string', 'exists:roles,name'],
            'additional_roles' => ['nullable', 'array'],
            'additional_roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $employee = $this->accessScope->accessibleEmployees($request->user())->whereKey($employeeId)->first();
        abort_unless($employee, 403, 'Pegawai berada di luar cakupan unit akun.');
        $this->accessScope->assertRoleAssignmentAllowed(
            $request->user(),
            $validated['role_name'],
            $validated['permissions'] ?? []
        );

        $additionalRoles = collect($validated['additional_roles'] ?? [])
            ->filter(fn ($r) => ! empty($r) && $r !== $validated['role_name'])
            ->values()
            ->all();

        foreach ($additionalRoles as $addRole) {
            $this->accessScope->assertRoleAssignmentAllowed($request->user(), $addRole);
        }

        try {
            DB::transaction(function () use ($employee, $validated, $additionalRoles) {
                // Tentukan atau buat akun User jika belum terhubung
                $user = $employee->user;
                if (! $user) {
                    $email = $employee->email ?: strtolower($employee->niy ?: $employee->id).'@sims.local';
                    $user = User::create([
                        'name' => $employee->nama_lengkap,
                        'email' => $email,
                        'password' => $validated['password'] ?? 'AkunBaru@2026!',
                        'phone' => $employee->no_hp,
                        'is_active' => true,
                        'metadata' => ['must_change_password' => true, 'created_by' => 'employee_access_module'],
                    ]);

                    $employee->user_id = $user->id;
                } elseif (! empty($validated['password'])) {
                    $user->password = $validated['password'];
                    $metadata = $user->metadata ?? [];
                    $metadata['must_change_password'] = false;
                    $metadata['password_reset_at'] = now()->toISOString();
                    $user->metadata = $metadata;
                    $user->save();
                }

                $role = Role::where('name', $validated['role_name'])->first();
                if ($role) {
                    $employee->role_id = $role->id;
                }
                $employee->save();

                // Sync Spatie primary role & additional roles di User
                $allRolesToSync = array_values(array_unique(array_merge([$validated['role_name']], $additionalRoles)));
                $user->syncRoles($allRolesToSync);

                if (isset($validated['permissions'])) {
                    $user->syncPermissions($validated['permissions']);
                }
            });

            $this->flushAccessCaches();

            return response()->json([
                'success' => true,
                'message' => "Hak akses pegawai '{$employee->nama_lengkap}' berhasil diperbarui.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui hak akses pegawai: '.$e->getMessage(),
            ], 500);
        }
    }

    private function assertCanViewGlobalRoleAccess(Request $request): void
    {
        $user = $request->user();
        $policy = $user ? RolePolicyDefaults::resolveUserPolicy($user) : [];
        $isAllowed = $user && (
            (bool) ($policy['can_manage_global_access'] ?? false)
            || ($policy['scope_type'] ?? '') === 'global'
            || $user->hasAnyRole([
                'Super Admin', 'super_admin', 'Superadmin', 'Admin',
                'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
                'Pengurus Yayasan', 'pengurus_yayasan',
                'Sekretaris Yayasan', 'sekretaris_yayasan',
                'Bendahara Yayasan', 'bendahara_yayasan',
            ])
            || $user->hasAnyPermission(['sistem.hak_akses', 'role.manage', 'permission.manage'])
        );

        abort_unless(
            $isAllowed,
            403,
            'Akses ditolak. Role unit hanya berhak mengelola hak akses pegawai di unitnya.'
        );
    }

    private function assertSuperAdminRoleAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->hasRole('Superadmin')),
            403,
            'Akses ditolak. Hanya Super Admin yang berhak mengakses data peran (roles).'
        );
    }

    private function assertCanViewAccessManagement(Request $request): void
    {
        $this->accessScope->assertAccessManagement($request->user());
    }
}
