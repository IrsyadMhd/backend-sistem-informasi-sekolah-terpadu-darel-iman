<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AuthLoginException;
use App\Models\LoginEvent;
use App\Models\QrCredential;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function __construct(
        private readonly AuthIdentifierResolver $resolver,
        private readonly PortalResolver $portalResolver
    ) {}

    /**
     * Portal 1: Superadmin & Admin System Login (email / No. HP + password).
     * Source of truth: users.email / users.phone (PostgreSQL).
     */
    public function loginAdminSystem(string $username, string $password, string $deviceName = 'web-dashboard', ?string $ipAddress = null): array
    {
        $identifier = trim($username);

        $user = $this->resolver->resolveAdminUser($identifier);

        if (! $user) {
            $this->fail(
                AuthLoginException::IDENTIFIER_NOT_FOUND,
                'Username/Email/No. HP atau password tidak valid.',
                null, 'admin', $identifier, $ipAddress
            );
        }

        if (! $user->hasAnyRole(['Super Admin', 'Admin', 'Superadmin', 'super_admin'])) {
            $this->fail(
                AuthLoginException::ROLE_NOT_ASSIGNED,
                'Kredensial atau password tidak valid.',
                $user, 'admin', $identifier, $ipAddress
            );
        }

        $this->verifyPasswordOrFail($user, $password, 'admin', $identifier, $ipAddress);
        $this->assertActiveOrFail($user, 'admin', $identifier, $ipAddress);

        $token = $user->createToken($deviceName)->plainTextToken;
        $loginEvent = $this->logLoginEvent($user, 'admin', $identifier, 'username_password', 'success', null, $ipAddress);

        return $this->authResult($user, $token, $loginEvent, 'admin');
    }

    /**
     * Portal 2: Pegawai & Guru Login (No. HP / NIY / Email + password).
     *
     * Login hanya memvalidasi identitas. Presensi pegawai dan teaching
     * attendance harus dibuat melalui flow attendance eksplisit.
     */
    public function loginEmployeeGuru(string $identifier, string $password, string $deviceName = 'web-dashboard', ?string $ipAddress = null): array
    {
        $input = trim($identifier);
        $resolved = $this->resolver->resolveEmployee($input);

        $actor = $resolved['employee'] ?? $resolved['teacher'];
        $user = $resolved['user'];

        if (! $actor) {
            $this->fail(
                AuthLoginException::IDENTIFIER_NOT_FOUND,
                'Kredensial pegawai/guru atau password tidak valid.',
                null, 'employee', $input, $ipAddress
            );
        }

        if (! $user) {
            $this->fail(
                AuthLoginException::ACCOUNT_NOT_LINKED,
                'Kredensial pegawai/guru atau password tidak valid.',
                null, 'employee', $input, $ipAddress
            );
        }

        if (! $this->hasStaffRole($user)) {
            $this->fail(
                AuthLoginException::ROLE_NOT_ASSIGNED,
                'Kredensial pegawai/guru atau password tidak valid.',
                $user, 'employee', $input, $ipAddress
            );
        }

        $this->verifyPasswordOrFail($user, $password, 'employee', $input, $ipAddress);
        $this->assertEmployeeActiveOrFail($actor, $user, $input, $ipAddress);
        $this->assertActiveOrFail($user, 'employee', $input, $ipAddress);

        $token = $user->createToken($deviceName)->plainTextToken;
        $loginEvent = $this->logLoginEvent($user, 'employee', $input, 'identifier_password', 'success', null, $ipAddress);

        return $this->authResult($user, $token, $loginEvent, 'employee');
    }

    /**
     * Portal 2 Alternative: Scan QR ID Card Pegawai.
     */
    public function loginEmployeeQr(string $qrToken, string $deviceName = 'mobile-app', ?string $ipAddress = null): array
    {
        $rawToken = trim($qrToken);
        $tokenHash = hash('sha256', $rawToken);

        $qrCredential = QrCredential::query()
            ->where(function ($q) use ($rawToken, $tokenHash) {
                $q->where('token_hash', $tokenHash)
                    ->orWhere('token_hash', $rawToken);
            })
            ->where('card_type', 'employee_card')
            ->active()
            ->first();

        if (! $qrCredential) {
            $this->logLoginEvent(null, 'employee', 'QR_SCAN', 'qr_code', 'failed', AuthLoginException::IDENTIFIER_NOT_FOUND, $ipAddress);
            throw new UnauthorizedHttpException('Bearer', 'Kredensial tidak valid.');
        }

        $employee = $qrCredential->employee;
        $user = $qrCredential->user ?? ($employee?->user_id ? User::query()->find($employee->user_id) : null);

        if (! $employee || ! $user || ! $this->hasStaffRole($user)) {
            $this->logLoginEvent($user, 'employee', 'QR_SCAN', 'qr_code', 'failed', AuthLoginException::ACCOUNT_NOT_LINKED, $ipAddress);
            throw new UnauthorizedHttpException('Bearer', 'Kredensial tidak valid.');
        }

        if (! $user->is_active || ! $this->isEmployeeActive($employee)) {
            $this->logLoginEvent($user, 'employee', 'QR_SCAN', 'qr_code', 'failed', AuthLoginException::ACCOUNT_INACTIVE, $ipAddress);
            throw new UnauthorizedHttpException('Bearer', 'Kredensial tidak valid.');
        }

        $qrCredential->update(['last_used_at' => now()]);

        $token = $user->createToken($deviceName)->plainTextToken;
        $loginEvent = $this->logLoginEvent($user, 'employee', 'QR_SCAN', 'qr_code', 'success', null, $ipAddress);

        return $this->authResult($user, $token, $loginEvent, 'employee');
    }

    /**
     * Portal 3: Orang Tua & Siswa Login (No. HP / NIK / NIS + password/PIN).
     *
     * Parent and student identifiers remain explicit for backward compatible
     * callers; the unified login below tries both candidates after password
     * validation and never trusts a client-selected role.
     */
    public function loginParentStudent(string $portalType, string $identifier, string $password, string $deviceName = 'web-dashboard', ?string $ipAddress = null): array
    {
        $input = trim($identifier);
        $isStudent = strtolower($portalType) === 'student';

        if ($isStudent) {
            $resolved = $this->resolver->resolveStudent($input);
            $student = $resolved['student'];
            $user = $resolved['user'];

            if (! $student) {
                $this->fail(
                    AuthLoginException::IDENTIFIER_NOT_FOUND,
                    'Kredensial atau password/PIN tidak valid.',
                    null, 'student', $input, $ipAddress
                );
            }

            if (! $user) {
                $this->fail(
                    AuthLoginException::STUDENT_NOT_LINKED,
                    'Kredensial atau password/PIN tidak valid.',
                    null, 'student', $input, $ipAddress
                );
            }

            if (! $this->hasStudentRole($user)) {
                $this->fail(
                    AuthLoginException::ROLE_NOT_ASSIGNED,
                    'Kredensial atau password/PIN tidak valid.',
                    $user, 'student', $input, $ipAddress
                );
            }

            if (! $student->is_active) {
                $this->fail(
                    AuthLoginException::STUDENT_NOT_ACTIVE,
                    'Kredensial atau password/PIN tidak valid.',
                    $user, 'student', $input, $ipAddress
                );
            }

            $this->verifyPasswordOrFail($user, $password, 'student', $input, $ipAddress);
            $this->assertActiveOrFail($user, 'student', $input, $ipAddress);

            $token = $user->createToken($deviceName)->plainTextToken;
            $loginEvent = $this->logLoginEvent($user, 'student', $input, 'identifier_password', 'success', null, $ipAddress);

            return $this->authResult($user, $token, $loginEvent, $user->hasAnyRole(['Alumni', 'alumni']) ? 'alumni' : 'student') + [
                'student' => $student,
                'children' => null,
            ];
        }

        $resolved = $this->resolver->resolveParent($input);
        $parent = $resolved['parent'];
        $user = $resolved['user'];

        if (! $parent) {
            $reason = $resolved['child_matched']
                ? AuthLoginException::PARENT_NOT_LINKED
                : AuthLoginException::IDENTIFIER_NOT_FOUND;

            $this->fail(
                $reason,
                'Kredensial atau password/PIN tidak valid.',
                null, 'parent', $input, $ipAddress
            );
        }

        if (! $user || ! $this->hasParentRole($user)) {
            $this->fail(
                AuthLoginException::PARENT_NOT_LINKED,
                'Kredensial atau password/PIN tidak valid.',
                $user, 'parent', $input, $ipAddress
            );
        }

        $this->verifyPasswordOrFail($user, $password, 'parent', $input, $ipAddress);
        $this->assertActiveOrFail($user, 'parent', $input, $ipAddress);

        $children = $this->resolver->childrenForParent($parent);
        if ($children->isEmpty()) {
            $this->fail(
                AuthLoginException::PARENT_NOT_LINKED,
                'Kredensial atau password/PIN tidak valid.',
                $user, 'parent', $input, $ipAddress
            );
        }

        $token = $user->createToken($deviceName)->plainTextToken;
        $loginEvent = $this->logLoginEvent($user, 'parent', $input, 'identifier_password', 'success', null, $ipAddress);

        return $this->authResult($user, $token, $loginEvent, 'parent') + [
            'parent' => $parent,
            'children' => $children,
        ];
    }

    /**
     * Unified login. Candidate profiles are resolved from PostgreSQL first;
     * a role is selected only after the password has been verified.
     */
    public function login(string $identifier, string $password, string $deviceName = 'web-client', ?string $ipAddress = null): array
    {
        $input = trim($identifier);
        $candidates = [];

        $admin = $this->resolver->resolveAdminUser($input);
        if ($admin) {
            $candidates[] = ['user' => $admin, 'profile' => null, 'portal' => 'admin', 'valid' => fn (User $user) => $user->hasAnyRole(['Super Admin', 'Admin', 'Superadmin', 'super_admin'])];
        }

        $employee = $this->resolver->resolveEmployee($input);
        $employeeProfile = $employee['employee'] ?? $employee['teacher'];
        if ($employeeProfile && $employee['user']) {
            $candidates[] = ['user' => $employee['user'], 'profile' => $employeeProfile, 'portal' => 'employee', 'valid' => fn (User $user) => $this->hasStaffRole($user) && $this->isEmployeeActive($employeeProfile)];
        }

        $student = $this->resolver->resolveStudent($input);
        if ($student['student'] && $student['user']) {
            $candidates[] = ['user' => $student['user'], 'profile' => $student['student'], 'portal' => 'student', 'valid' => fn (User $user) => $this->hasStudentRole($user) && $student['student']->is_active];
        }

        $parent = $this->resolver->resolveParent($input);
        if ($parent['parent'] && $parent['user']) {
            $candidates[] = ['user' => $parent['user'], 'profile' => $parent['parent'], 'portal' => 'parent', 'valid' => fn (User $user) => $this->hasParentRole($user) && $this->resolver->childrenForParent($parent['parent'])->isNotEmpty()];
        }

        $candidates = collect($candidates)
            ->unique(fn (array $candidate) => $candidate['user']->id.'|'.$candidate['portal'])
            ->values()
            ->all();

        $validCandidates = collect($candidates)
            ->filter(fn (array $candidate) => $this->verifyPassword($candidate['user'], $password) && $candidate['user']->is_active && ($candidate['valid'])($candidate['user']))
            ->values();

        if ($validCandidates->count() > 1) {
            $this->logLoginEvent(null, 'unified', $input, 'identifier_password', 'failed', 'IDENTIFIER_AMBIGUOUS', $ipAddress);

            return [
                'ambiguous' => true,
                    'workspaces' => $validCandidates
                    ->map(fn (array $candidate) => [
                        'portal_type' => $candidate['portal'],
                        'label' => match ($candidate['portal']) {
                            'admin' => 'Admin',
                            'parent' => 'Orang Tua',
                            'student' => 'Siswa',
                            default => 'Pegawai',
                        },
                    ])
                    ->unique('portal_type')
                    ->values()
                    ->all(),
            ];
        }

        $candidate = $validCandidates->first();
        if (! $candidate) {
            $reason = $candidates !== [] ? AuthLoginException::PASSWORD_INVALID : AuthLoginException::IDENTIFIER_NOT_FOUND;
            $this->fail($reason, 'Kredensial atau password/PIN tidak valid.', null, 'unified', $input, $ipAddress);
        }

        $token = $candidate['user']->createToken($deviceName)->plainTextToken;
        $loginEvent = $this->logLoginEvent($candidate['user'], 'unified', $input, 'identifier_password', 'success', null, $ipAddress);
        $result = $this->authResult($candidate['user'], $token, $loginEvent, $candidate['portal']);

        if ($candidate['portal'] === 'parent') {
            $result['parent'] = $candidate['profile'];
            $result['children'] = $this->resolver->childrenForParent($candidate['profile']);
        } elseif ($candidate['portal'] === 'student') {
            $result['student'] = $candidate['profile'];
            $result['children'] = null;
        }

        return $result;
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    private function authResult(User $user, string $token, ?LoginEvent $loginEvent, string $portal): array
    {
        return [
            'user' => $user,
            'token' => $token,
            'login_event_id' => $loginEvent?->id,
            'portal' => $portal,
            ...$this->portalResolver->resolve($user),
            'attendance_summary' => null,
        ];
    }

    private function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    private function verifyPasswordOrFail(User $user, string $password, string $portalType, string $identifier, ?string $ipAddress): void
    {
        if (! $this->verifyPassword($user, $password)) {
            $this->fail(
                AuthLoginException::PASSWORD_INVALID,
                'Kredensial atau password/PIN tidak valid.',
                $user, $portalType, $identifier, $ipAddress
            );
        }
    }

    private function assertActiveOrFail(User $user, string $portalType, string $identifier, ?string $ipAddress): void
    {
        if (! $user->is_active) {
            $this->fail(
                AuthLoginException::ACCOUNT_INACTIVE,
                'Kredensial atau password/PIN tidak valid.',
                $user, $portalType, $identifier, $ipAddress
            );
        }
    }

    private function assertEmployeeActiveOrFail(mixed $actor, User $user, string $identifier, ?string $ipAddress): void
    {
        if (! $this->isEmployeeActive($actor)) {
            $this->fail(
                AuthLoginException::ACCOUNT_INACTIVE,
                'Kredensial pegawai/guru atau password tidak valid.',
                $user, 'employee', $identifier, $ipAddress
            );
        }
    }

    private function isEmployeeActive(mixed $actor): bool
    {
        $status = strtolower(trim((string) ($actor?->status ?? '')));

        return $status === '' || in_array($status, ['aktif', 'active'], true);
    }

    private function hasStaffRole(User $user): bool
    {
        return $user->hasAnyRole([
            'Admin', 'Super Admin', 'Superadmin', 'super_admin',
            'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Bidang Pendidikan', 'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan',
            'Divisi Bahasa', 'Divisi Program Khusus', 'Kepala Sekolah', 'Wakil Kepala Sekolah',
            'Wakil Kurikulum', 'Wakil Kesiswaan', 'Tata Usaha', 'TU', 'Operator',
            'Guru', 'Guru Mata Pelajaran', 'Guru PAI', 'Pembimbing', 'Guru Tahfizh',
            'Guru BK', 'Wali Kelas', 'Musyrif', 'Musyrifah', 'Musyrif / Musyrifah',
            'Yayasan', 'pengurus_yayasan', 'ketua_yayasan', 'sekretaris_yayasan', 'bendahara_yayasan',
            'Divisi Pendidikan', 'divisi_pendidikan', 'Waka Kurikulum', 'Waka Kesiswaan',
        ]);
    }

    private function hasParentRole(User $user): bool
    {
        return $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Orangtua', 'Wali Murid', 'parent']);
    }

    private function hasStudentRole(User $user): bool
    {
        return $user->hasAnyRole(['Siswa', 'siswa', 'student', 'Alumni', 'alumni']);
    }

    private function fail(string $reason, string $genericMessage, ?User $user, string $portalType, string $identifier, ?string $ipAddress): never
    {
        $this->logLoginEvent($user, $portalType, $identifier, 'identifier_password', 'failed', $reason, $ipAddress);
        throw new AuthLoginException($reason, $genericMessage);
    }

    private function logLoginEvent(?User $user, string $portalType, ?string $identifier, string $method, string $status, ?string $failureReason = null, ?string $ipAddress = null): ?LoginEvent
    {
        try {
            return LoginEvent::create([
                'user_id' => $user?->id,
                'portal_type' => $portalType,
                'identifier_used' => $identifier,
                'login_method' => $method,
                'status' => $status,
                'failure_reason' => $failureReason,
                'ip_address' => $ipAddress ?? request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Log failure should not block login flow
            return null;
        }
    }
}
