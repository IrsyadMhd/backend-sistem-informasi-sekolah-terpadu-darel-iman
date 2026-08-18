<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Kegagalan autentikasi login multi-identifier.
 *
 * Membawa kode reason INTERNAL (untuk login_events.failure_reason) dan pesan
 * GENERIC untuk pengguna. Detail internal tidak pernah diekspos ke client.
 */
class AuthLoginException extends UnauthorizedHttpException
{
    public const IDENTIFIER_NOT_FOUND = 'IDENTIFIER_NOT_FOUND';
    public const PASSWORD_INVALID = 'PASSWORD_INVALID';
    public const ACCOUNT_NOT_LINKED = 'ACCOUNT_NOT_LINKED';
    public const ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';
    public const ROLE_NOT_ASSIGNED = 'ROLE_NOT_ASSIGNED';
    public const PARENT_NOT_LINKED = 'PARENT_NOT_LINKED';
    public const STUDENT_NOT_FOUND = 'STUDENT_NOT_FOUND';
    public const STUDENT_NOT_LINKED = 'STUDENT_NOT_LINKED';
    public const STUDENT_NOT_ACTIVE = 'STUDENT_NOT_ACTIVE';
    public const PORTAL_ACCESS_DENIED = 'PORTAL_ACCESS_DENIED';

    public function __construct(
        public readonly string $reason,
        string $genericMessage,
    ) {
        parent::__construct('Bearer', $genericMessage);
    }
}
