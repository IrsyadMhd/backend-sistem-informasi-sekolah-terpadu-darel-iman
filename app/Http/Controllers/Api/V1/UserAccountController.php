<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $unitId = trim((string) $request->query('unit_id', ''));

        if ($unitId) {
            $this->accessScope->assertEducationUnitAccess($request->user(), $unitId);
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($request->user())->pluck('id');
        $isGlobalAdmin = $this->isGlobalAdmin($request->user());

        $users = User::query()
            ->with(['roles:id,name', 'employee.unit', 'student.educationUnit'])
            ->when($request->query('status') === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when($request->query('role_status') === 'without_role', fn ($query) => $query->doesntHave('roles'))
            ->when($unitId !== '', function ($query) use ($unitId) {
                $query->where(function ($q) use ($unitId) {
                    $q->whereHas('employee', fn ($e) => $e->where('unit_id', $unitId))
                      ->orWhereHas('student', fn ($s) => $s->where('unit_id', $unitId));
                });
            })
            ->when(! $isGlobalAdmin && $unitId === '', function ($query) use ($accessibleUnitIds, $request) {
                $query->where(function ($q) use ($accessibleUnitIds, $request) {
                    $q->where('id', $request->user()->id)
                      ->orWhereHas('employee', fn ($e) => $e->whereIn('unit_id', $accessibleUnitIds))
                      ->orWhereHas('student', fn ($s) => $s->whereIn('unit_id', $accessibleUnitIds));
                });
            })
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where('name', $operator, "%{$search}%")
                    ->orWhere('email', $operator, "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => collect($users->items())->map(fn (User $user) => $this->serialize($user)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $validated = $request->validate($this->rules());

        $user = DB::transaction(function () use ($validated) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'is_active' => $validated['is_active'] ?? true,
                'metadata' => ['must_change_password' => true, 'created_by' => 'user_account_module'],
            ]);
            $user->syncRoles([$validated['role']]);

            return $user->load(['roles:id,name', 'employee.unit', 'student.educationUnit']);
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil dibuat.",
            'data' => $this->serialize($user),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertUserAccess($request->user(), $user);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($user->load(['roles:id,name', 'employee.unit', 'student.educationUnit'])),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertUserAccess($request->user(), $user);
        $isUnitOnlyManager = $this->accessScope->canManageUnitAccess($request->user())
            && ! $this->accessScope->canManageGlobalAccess($request->user());
        $validated = $request->validate($isUnitOnlyManager
            ? ['role' => ['required', 'string', Rule::exists(Role::class, 'name')]]
            : $this->rules($user, false));
        $this->accessScope->assertRoleAssignmentAllowed($request->user(), $validated['role']);

        $removingOwnAdminAccess = $request->user()->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin'])
            && $request->user()->is($user)
            && ((! ($validated['is_active'] ?? $user->is_active))
                || (($validated['role'] ?? $user->getRoleNames()->first()) !== 'Super Admin'));

        abort_if($removingOwnAdminAccess, 422, 'Super Admin tidak dapat menonaktifkan atau mencabut role dirinya sendiri.');

        DB::transaction(function () use ($user, $validated, $isUnitOnlyManager) {
            if (! $isUnitOnlyManager) {
                $user->fill([
                    'name' => $validated['name'],
                    'email' => strtolower($validated['email']),
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => $validated['is_active'] ?? $user->is_active,
                ])->save();
            }

            $user->syncRoles([$validated['role']]);

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil diperbarui.",
            'data' => $this->serialize($user->fresh(['roles:id,name', 'employee.unit', 'student.educationUnit'])),
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $this->assertUserAccess($request->user(), $user);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        $metadata = $user->metadata ?? [];
        $metadata['must_change_password'] = true;
        $metadata['password_reset_at'] = now()->toIso8601String();
        $metadata['password_reset_by'] = $request->user()->id;

        $user->forceFill([
            'password' => $validated['password'],
            'metadata' => $metadata,
        ])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => "Password {$user->name} berhasil direset. Semua sesi lama telah dikeluarkan.",
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $this->assertUserAccess($request->user(), $user);
        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun yang sedang digunakan.');

        if ($user->hasRole('Super Admin')) {
            $activeAdmins = User::query()
                ->where('is_active', true)
                ->role('Super Admin')
                ->count();
            abort_if($activeAdmins <= 1, 422, 'Super Admin aktif terakhir tidak dapat dihapus.');
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil dihapus.",
        ]);
    }

    private function isGlobalAdmin(User $user): bool
    {
        return $this->accessScope->hasGlobalScope($user);
    }

    private function assertUserAccess(User $authUser, User $targetUser): void
    {
        if ($this->accessScope->canManageGlobalAccess($authUser)) {
            return;
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($authUser)->pluck('id');
        $targetUnitId = $targetUser->employee?->unit_id ?? $targetUser->student?->unit_id;

        abort_unless(
            $this->accessScope->canManageUnitAccess($authUser)
                && $targetUnitId
                && $accessibleUnitIds->contains($targetUnitId),
            403,
            'Akun pengguna berada di luar cakupan unit pendidikan Anda.'
        );
    }

    private function rules(?User $user = null, bool $withPassword = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($withPassword) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)->mixedCase()->letters()->numbers()->symbols()];
        }

        return $rules;
    }

    private function serialize(User $user): array
    {
        $unit = $user->employee?->unit ? [
            'id' => $user->employee->unit->id,
            'nama' => $user->employee->unit->nama_unit ?? $user->employee->unit->name ?? '-',
        ] : ($user->student?->educationUnit ? [
            'id' => $user->student->educationUnit->id,
            'nama' => $user->student->educationUnit->nama_unit ?? $user->student->educationUnit->name ?? '-',
        ] : null);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values(),
            'unit' => $unit,
            'must_change_password' => (bool) data_get($user->metadata, 'must_change_password', false),
            'last_password_reset_at' => data_get($user->metadata, 'password_reset_at'),
            'created_at' => $user->created_at,
        ];
    }
}
