<?php

namespace App\Support;

use App\Models\User;

final class RoleName
{
    /**
     * Normalize compatibility separators without changing the stored Spatie role.
     */
    public static function normalize(string $role): string
    {
        $normalized = preg_replace('/[\s_-]+/u', '', trim($role));

        return strtolower($normalized ?? trim($role));
    }

    /**
     * @param  iterable<string>  $actualRoles
     * @param  list<string>  $expectedRoles
     */
    public static function matchesAny(iterable $actualRoles, array $expectedRoles): bool
    {
        $expected = [];

        foreach ($expectedRoles as $role) {
            $expected[self::normalize($role)] = true;
        }

        foreach ($actualRoles as $role) {
            if (isset($expected[self::normalize((string) $role)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $expectedRoles
     */
    public static function userHasAny(object $user, array $expectedRoles): bool
    {
        // Keep middleware/test doubles compatible with the legacy Spatie
        // methods while real application users use the normalized role list.
        if (! $user instanceof User) {
            if (count($expectedRoles) === 1 && is_callable([$user, 'hasRole'])) {
                return (bool) $user->hasRole($expectedRoles[0]);
            }

            if (is_callable([$user, 'hasAnyRole'])) {
                return (bool) $user->hasAnyRole($expectedRoles);
            }

            return false;
        }

        return self::matchesAny($user->getRoleNames(), $expectedRoles);
    }
}
