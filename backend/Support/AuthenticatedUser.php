<?php
declare(strict_types=1);

namespace App\Support;

/**
 * The signed-in user, with both the legacy role value and the normalised set
 * of role codes resolved from `user_roles`.
 *
 * Authorization always asks this object rather than reading the session, so a
 * role granted through `user_roles` is honoured even when the legacy
 * `users.role` column still says "viewer".
 */
final class AuthenticatedUser
{
    /** @var array<int, string> */
    private array $roles;

    /**
     * @param array<int, string> $roleSources Raw role values from the database.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $displayRole,
        array $roleSources
    ) {
        $normalised = [];

        foreach ($roleSources as $source) {
            foreach (explode(',', (string) $source) as $value) {
                $code = Roles::normalize($value);

                if ($code !== '') {
                    $normalised[$code] = $code;
                }
            }
        }

        $this->roles = array_values($normalised);
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function hasRole(string ...$codes): bool
    {
        $wanted = [];

        foreach ($codes as $code) {
            $wanted[Roles::normalize($code)] = true;
        }

        foreach ($this->roles as $role) {
            if (isset($wanted[$role])) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Roles::ADMIN);
    }

    public function canEditRecords(): bool
    {
        return $this->hasRole(...Roles::EDITORS);
    }

    /**
     * Shape returned to the client. Never includes password material.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->id,
            'name' => $this->username,
            'role' => $this->roles[0] ?? Roles::VIEWER,
            'roles' => $this->roles,
        ];
    }
}
