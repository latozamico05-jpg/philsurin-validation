<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for `roles` and the `user_roles` join table.
 */
final class RoleRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->db->query(
            'SELECT role_id, role_code, role_name, description FROM roles ORDER BY role_code ASC'
        );

        return $statement->fetchAll();
    }

    public function idForCode(string $roleCode): ?int
    {
        $statement = $this->db->prepare('SELECT role_id FROM roles WHERE role_code = ? LIMIT 1');
        $statement->execute([$roleCode]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Role codes granted through the normalised join table.
     *
     * @return array<int, string>
     */
    public function codesForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT r.role_code
             FROM user_roles ur
             JOIN roles r ON r.role_id = ur.role_id
             WHERE ur.user_id = ?
             ORDER BY r.role_code'
        );
        $statement->execute([$userId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Every role value known for a user: the legacy column plus join rows.
     *
     * @return array<int, string>
     */
    public function allRoleSourcesForUser(int $userId): array
    {
        $sources = $this->codesForUser($userId);

        $statement = $this->db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $legacy = $statement->fetchColumn();

        if (is_string($legacy) && $legacy !== '') {
            array_unshift($sources, $legacy);
        }

        return $sources;
    }

    public function grant(int $userId, string $roleCode, ?int $assignedBy = null): bool
    {
        $roleId = $this->idForCode($roleCode);

        if ($roleId === null) {
            return false;
        }

        $statement = $this->db->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)'
        );
        $statement->execute([$userId, $roleId]);

        unset($assignedBy);

        return true;
    }

    public function revoke(int $userId, string $roleCode): bool
    {
        $roleId = $this->idForCode($roleCode);

        if ($roleId === null) {
            return false;
        }

        $statement = $this->db->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
        $statement->execute([$userId, $roleId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Replace every join row for a user with the given set of codes.
     *
     * @param array<int, string> $roleCodes
     */
    public function sync(int $userId, array $roleCodes): void
    {
        $this->db->beginTransaction();

        try {
            $delete = $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?');
            $delete->execute([$userId]);

            $insert = $this->db->prepare(
                'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, role_id FROM roles WHERE role_code = ?'
            );

            foreach (array_unique($roleCodes) as $code) {
                $insert->execute([$userId, $code]);
            }

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }

    /**
     * Users holding a role code, used by dashboards and notifications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function usersWithRole(string $roleCode): array
    {
        $statement = $this->db->prepare(
            'SELECT u.id, u.username, u.active
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.role_id = ur.role_id
             WHERE r.role_code = ?
             ORDER BY u.username'
        );
        $statement->execute([$roleCode]);

        return $statement->fetchAll();
    }
}
