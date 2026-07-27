<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HakAksesController extends Controller
{
    // ─────────────────────────────────────────────────
    // ROLE CRUD
    // ─────────────────────────────────────────────────

    /**
     * Daftar semua Role beserta jumlah permission & user.
     */
    public function indexRoles(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $query = Role::withCount(['permissions', 'users'])
            ->orderBy('name');

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $roles = $query->get()->map(fn($r) => [
            'id'               => $r->id,
            'name'             => $r->name,
            'guard_name'       => $r->guard_name,
            'jumlah_izin'      => $r->permissions_count,
            'jumlah_pengguna'  => $r->users_count,
            'permissions'      => $r->permissions->pluck('name'),
            'created_at'       => $r->created_at,
            'updated_at'       => $r->updated_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $roles,
            'total'   => $roles->count(),
        ]);
    }

    /**
     * Simpan Role baru.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'min:2', 'max:100', 'unique:roles,name'],
            'guard_name'     => ['nullable', 'string', 'max:50'],
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['string', 'exists:permissions,name'],
        ]);

        try {
            $role = DB::transaction(function () use ($validated) {
                $role = Role::create([
                    'name'       => $validated['name'],
                    'guard_name' => $validated['guard_name'] ?? 'web',
                ]);

                if (!empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }

                return $role;
            });

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil ditambahkan.",
                'data'    => $role->load('permissions'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan role: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail Role beserta permissions-nya.
     */
    public function showRole(string $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $role->id,
                'name'        => $role->name,
                'guard_name'  => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'created_at'  => $role->created_at,
                'updated_at'  => $role->updated_at,
            ],
        ]);
    }

    /**
     * Perbarui data Role.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'min:2', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'guard_name'     => ['nullable', 'string', 'max:50'],
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['string', 'exists:permissions,name'],
        ]);

        try {
            DB::transaction(function () use ($role, $validated) {
                $role->update([
                    'name'       => $validated['name'],
                    'guard_name' => $validated['guard_name'] ?? $role->guard_name,
                ]);

                $role->syncPermissions($validated['permissions'] ?? []);
            });

            return response()->json([
                'success' => true,
                'message' => "Role '{$role->name}' berhasil diperbarui.",
                'data'    => $role->fresh()->load('permissions'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui role: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus Role.
     */
    public function destroyRole(string $id): JsonResponse
    {
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
        $search = $request->get('search', '');

        $query = Permission::orderBy('name');

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $permissions = $query->get();

        // Kelompokkan berdasarkan prefix modul (sebelum titik)
        $grouped = $permissions->groupBy(fn($p) => explode('.', $p->name)[0] ?? 'lainnya')
            ->map(fn($items, $modul) => [
                'modul'  => $modul,
                'total'  => $items->count(),
                'izin'   => $items->map(fn($p) => [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'guard_name' => $p->guard_name,
                ]),
            ])
            ->values();

        return response()->json([
            'success'    => true,
            'data'       => $grouped,
            'total'      => $permissions->count(),
            'flat_list'  => $permissions->pluck('name'),
        ]);
    }

    /**
     * Tambah Permission baru.
     */
    public function storePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'min:2', 'max:150', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $permission = Permission::create([
                'name'       => $validated['name'],
                'guard_name' => $validated['guard_name'] ?? 'web',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Izin akses '{$permission->name}' berhasil ditambahkan.",
                'data'    => $permission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan permission: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus Permission.
     */
    public function destroyPermission(string $id): JsonResponse
    {
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
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_role'        => Role::count(),
                'total_permission'  => Permission::count(),
                'total_modul'       => Permission::get()->groupBy(fn($p) => explode('.', $p->name)[0])->count(),
                'role_tanpa_user'   => Role::withCount('users')->get()->filter(fn($r) => $r->users_count === 0)->count(),
            ],
        ]);
    }
}
