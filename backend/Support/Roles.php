<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Role codes used for authorization.
 *
 * The legacy `users.role` column stores lowercase values ('admin', 'viewer')
 * while the normalised `roles.role_code` column stores uppercase codes. Both
 * are treated as the same role so existing accounts keep working unchanged.
 */
final class Roles
{
    public const ADMIN = 'ADMIN';
    public const VIEWER = 'VIEWER';
    public const VALIDATOR_GERMINATION = 'VALIDATOR_GERMINATION';
    public const VALIDATOR_FIELD = 'VALIDATOR_FIELD';
    public const VALIDATOR_HARVEST = 'VALIDATOR_HARVEST';

    /** Role codes accepted by the `roles` table. */
    public const ALL = [
        self::ADMIN,
        self::VIEWER,
        self::VALIDATOR_GERMINATION,
        self::VALIDATOR_FIELD,
        self::VALIDATOR_HARVEST,
    ];

    /** Legacy values still present in `users.role`. */
    public const LEGACY_ALIASES = [
        'admin' => self::ADMIN,
        'viewer' => self::VIEWER,
        'validator' => self::VALIDATOR_FIELD,
        'validator_field' => self::VALIDATOR_FIELD,
        'validator_germination' => self::VALIDATOR_GERMINATION,
        'validator_harvest' => self::VALIDATOR_HARVEST,
    ];

    /** Roles that may create or edit field records. */
    public const EDITORS = [
        self::ADMIN,
        self::VALIDATOR_FIELD,
        self::VALIDATOR_GERMINATION,
        self::VALIDATOR_HARVEST,
    ];

    /**
     * Map any accepted spelling to the canonical uppercase role code.
     */
    public static function normalize(?string $role): string
    {
        $role = strtoupper(trim((string) $role));

        if ($role === '') {
            return '';
        }

        if (isset(self::LEGACY_ALIASES[strtolower($role)])) {
            return self::LEGACY_ALIASES[strtolower($role)];
        }

        return $role;
    }

    public static function isKnown(string $roleCode): bool
    {
        return in_array(self::normalize($roleCode), self::ALL, true);
    }

    /**
     * Role codes the legacy `users.role` column may store.
     */
    public static function legacyValueFor(string $roleCode): string
    {
        return match (self::normalize($roleCode)) {
            self::ADMIN => 'admin',
            default => 'viewer',
        };
    }
}
