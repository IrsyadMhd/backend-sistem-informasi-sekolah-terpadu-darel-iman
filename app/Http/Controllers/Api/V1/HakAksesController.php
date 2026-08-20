<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class HakAksesController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    // ─────────────────────────────────────────────────
    // ROLE CRUD
    // ─────────────────────────────────────────────────

    /**
     * Daftar semua Role beserta jumlah permission & user.
     */
    public function indexRoles(Request $request): JsonResponse
    {
        $this->assertCanViewAccessManagement($request);
        $search = $request->get('search', '');

        $query = Role::withCount(['permissions', 'users'])
            ->orderBy('name');

        if ($search) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('name', $likeOp, "%{$search}%");
        }

        $roles = $query->with('permissions')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'guard_name' => $r->guard_name,
            'jumlah_izin' => $r->permissions_count,
            'jumlah_pengguna' => $r->users_count,
            'permissions' => $r->permissions->pluck('name'),
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $roles,
            'total' => $roles->count(),
        ]);
    }

    /**
     * Simpan Role baru.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        try {
            $role = DB::transaction(function () use ($validated) {
                $role = Role::create([
                    'name' => $validated['name'],
                    'guard_name' => $validated['guard_name'] ?? 'web',
                ]);

                if (! empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }

                return $role;
            });

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil ditambahkan.",
                'data' => $role->load('permissions'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail Role beserta permissions-nya.
     */
    public function showRole(Request $request, string $id): JsonResponse
    {
        $this->assertCanViewAccessManagement($request);
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Perbarui data Role.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        try {
            DB::transaction(function () use ($role, $validated) {
                $role->update([
                    'name' => $validated['name'],
                    'guard_name' => $validated['guard_name'] ?? $role->guard_name,
                ]);

                $role->syncPermissions($validated['permissions'] ?? []);
            });

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil diperbarui.",
                'data' => $role->fresh()->load('permissions'),
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

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => "Role '{$role->name}' tidak dapat dihapus karena masih digunakan oleh {$role->users()->count()} pengguna.",
            ], 422);
        }

        $name = $role->name;
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => "Role '{$name}' berhasil dihapus.",
        ]);
    }

    // ─────────────────────────────────────────────────
    // PERMISSION CRUD
    // ─────────────────────────────────────────────────

    /**
     * Daftar semua Permission, dikelompokkan berdasarkan modul.
     */
    public function indexPermissions(Request $request): JsonResponse
    {
        $this->assertCanViewAccessManagement($request);
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
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        $employee = $this->accessScope->accessibleEmployees($request->user())->whereKey($employeeId)->first();
        abort_unless($employee, 403, 'Pegawai berada di luar cakupan unit akun.');
        $this->accessScope->assertRoleAssignmentAllowed(
            $request->user(),
            $validated['role_name'],
            $validated['permissions'] ?? []
        );

        try {
            DB::transaction(function () use ($employee, $validated) {
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
                }

                $role = Role::where('name', $validated['role_name'])->first();
                if ($role) {
                    $employee->role_id = $role->id;
                }
                $employee->save();

                // Sync Spatie role & direct permissions di User
                $user->syncRoles([$validated['role_name']]);

                if (isset($validated['permissions'])) {
                    $user->syncPermissions($validated['permissions']);
                }
            });

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

    private function assertCanViewAccessManagement(Request $request): void
    {
        $this->accessScope->assertAccessManagement($request->user());
    }
}
