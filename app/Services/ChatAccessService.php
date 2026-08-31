<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Database\Eloquent\Builder;

class ChatAccessService
{
    /**
     * Resolve capability scope for the given user.
     */
    public function resolveChatScope(User $user): array
    {
        $roleNames = [];
        try {
            if (method_exists($user, 'getRoleNames')) {
                $roleNames = $user->getRoleNames()->toArray();
            }
        } catch (\Throwable $e) {}

        $userRoles = [];
        try {
            if (method_exists($user, 'roles')) {
                $userRoles = $user->roles()->pluck('name')->toArray();
            }
        } catch (\Throwable $e) {}

        $employee = Employee::query()
            ->with(['position:id,name', 'role:id,name', 'unit:id,name'])
            ->where('user_id', $user->id)
            ->first();

        $userUnitId = $employee?->unit_id ?? $user->unit_id ?? null;
        $empPosition = $employee?->position?->name ?? '';
        $empRole = $employee?->role?->name ?? '';

        $roleStr = strtolower(implode(' ', array_merge(
            (array) ($user->role ?? []),
            (array) ($user->role_name ?? []),
            $roleNames,
            $userRoles,
            [$empPosition, $empRole]
        )));

        $hasSpatieSuperAdmin = false;
        try {
            if (method_exists($user, 'hasAnyRole')) {
                $hasSpatieSuperAdmin = $user->hasAnyRole([
                    'Super Admin', 'SuperAdmin', 'super_admin', 'superadmin',
                    'Admin', 'admin',
                ]);
            }
        } catch (\Throwable $e) {}

        $isSuperAdmin = $hasSpatieSuperAdmin || str_contains($roleStr, 'super') || str_contains($roleStr, 'admin');

        $isYayasan = (bool) (
            str_contains($roleStr, 'yayasan') ||
            str_contains($roleStr, 'pengurus') ||
            str_contains($roleStr, 'ketua') ||
            str_contains($roleStr, 'sekretaris') ||
            str_contains($roleStr, 'bendahara')
        );

        $isDivisiPendidikan = (bool) (
            str_contains($roleStr, 'divisi pendidikan') ||
            str_contains($roleStr, 'pendidikan') ||
            str_contains($roleStr, 'kabid')
        );

        $isKepalaSekolah = (bool) (
            str_contains($roleStr, 'kepala sekolah') ||
            str_contains($roleStr, 'kepsek')
        );

        $isGuru = (bool) (
            str_contains($roleStr, 'guru') ||
            str_contains($roleStr, 'walas') ||
            str_contains($roleStr, 'wali kelas') ||
            str_contains($roleStr, 'tahfizh') ||
            str_contains($roleStr, 'musyrif')
        );

        $isParent = (bool) (
            str_contains($roleStr, 'orang tua') ||
            str_contains($roleStr, 'orang_tua') ||
            str_contains($roleStr, 'parent') ||
            str_contains($roleStr, 'wali murid')
        );

        if ($isSuperAdmin) {
            return [
                'category' => 'super_admin',
                'can_view_all_units' => true,
                'user_unit_id' => $userUnitId,
                'allowed_categories' => ['foundation', 'school_principal', 'education_division', 'employee', 'teacher', 'parent'],
            ];
        }

        if ($isYayasan) {
            return [
                'category' => 'foundation',
                'can_view_all_units' => true,
                'user_unit_id' => $userUnitId,
                'allowed_categories' => ['school_principal', 'education_division', 'employee', 'teacher'],
            ];
        }

        if ($isDivisiPendidikan) {
            return [
                'category' => 'education_division',
                'can_view_all_units' => true,
                'user_unit_id' => $userUnitId,
                'allowed_categories' => ['foundation', 'school_principal', 'employee', 'teacher'],
            ];
        }

        if ($isKepalaSekolah) {
            return [
                'category' => 'school_principal',
                'can_view_all_units' => false,
                'user_unit_id' => $userUnitId,
                'cross_unit_categories' => ['foundation', 'education_division'],
                'allowed_categories' => ['foundation', 'education_division', 'employee', 'teacher'],
            ];
        }

        if ($isParent) {
            return [
                'category' => 'parent',
                'can_view_all_units' => false,
                'user_unit_id' => $userUnitId,
                'allowed_categories' => ['teacher'],
            ];
        }

        return [
            'category' => $isGuru ? 'teacher' : 'employee',
            'can_view_all_units' => false,
            'user_unit_id' => $userUnitId,
            'allowed_categories' => ['school_principal', 'education_division', 'employee', 'teacher', 'parent'],
        ];
    }

    /**
     * Get active presence statuses map for given user IDs.
     */
    public function getPresenceMap(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userIdsStr = array_map('strval', $userIds);

        $presences = UserPresence::query()
            ->whereIn('user_id', $userIdsStr)
            ->get()
            ->keyBy('user_id');

        $activeTokenUserIds = \Laravel\Sanctum\PersonalAccessToken::query()
            ->whereIn('tokenable_id', $userIdsStr)
            ->where(function ($q) {
                $q->where('last_used_at', '>=', now()->subMinutes(15))
                  ->orWhere(function ($q2) {
                      $q2->whereNull('last_used_at')->where('created_at', '>=', now()->subMinutes(15));
                  });
            })
            ->pluck('tokenable_id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->toArray();

        $map = [];
        foreach ($userIdsStr as $uid) {
            if (isset($presences[$uid])) {
                $p = $presences[$uid];
                $map[$uid] = [
                    'status' => $p->status,
                    'is_online' => $p->status === 'online',
                    'last_seen_at' => $p->last_seen_at?->toIso8601String(),
                ];
            } else {
                $isOnline = isset($activeTokenUserIds[$uid]);
                $map[$uid] = [
                    'status' => $isOnline ? 'online' : 'offline',
                    'is_online' => $isOnline,
                    'last_seen_at' => null,
                ];
            }
        }

        return $map;
    }

    /**
     * Validate if sender can initiate direct message with recipient.
     */
    public function canStartConversation(User $sender, User $recipient): bool
    {
        if ($sender->id === $recipient->id) {
            return false;
        }

        $scope = $this->resolveChatScope($sender);
        if ($scope['can_view_all_units']) {
            return true;
        }

        return true;
    }
}
