<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Roles;
use PDO;

/**
 * Data access for the `users` table.
 *
 * Only the columns the API needs are read. `password_hash` is never selected
 * outside of `findCredentialsForLogin()` so it cannot leak into a JSON payload
 * by accident.
 */
final class UserRepository
{
    private const PUBLIC_COLUMNS = 'id, username, role, active, email, full_name, status, last_login_at, created_at, updated_at';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $statement = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    /**
     * Includes the stored hash. Only the login service may call this.
     *
     * @return array<string, mixed>|null
     */
    public function findCredentialsForLogin(string $username): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, username, role, active, password_hash FROM users WHERE username = ? LIMIT 1'
        );
        $statement->execute([$username]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->db->query(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM users ORDER BY username ASC'
        );

        return $statement->fetchAll();
    }

    public function usernameExists(string $username, ?int $exceptUserId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = ?';
        $params = [$username];

        if ($exceptUserId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptUserId;
        }

        $statement = $this->db->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, active, email, full_name, status)
             VALUES (:username, :password_hash, :role, :active, :email, :full_name, :status)'
        );

        $statement->execute([
            'username' => $attributes['username'],
            'password_hash' => $attributes['password_hash'],
            'role' => $attributes['role'] ?? Roles::legacyValueFor(Roles::VIEWER),
            'active' => $attributes['active'] ?? 1,
            'email' => $attributes['email'] ?? null,
            'full_name' => $attributes['full_name'] ?? null,
            'status' => $attributes['status'] ?? 'ACTIVE',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update scalar profile columns. Null values are ignored so a partial
     * payload never blanks a field the caller did not send.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(int $userId, array $attributes): void
    {
        $assignments = [];
        $params = ['id' => $userId];

        foreach (['email', 'full_name', 'status'] as $column) {
            if (array_key_exists($column, $attributes)) {
                $assignments[] = $column . ' = :' . $column;
                $params[$column] = $attributes[$column];
            }
        }

        if (isset($attributes['password_hash'])) {
            $assignments[] = 'password_hash = :password_hash';
            $params['password_hash'] = $attributes['password_hash'];
        }

        if ($assignments === []) {
            return;
        }

        $statement = $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = :id'
        );
        $statement->execute($params);
    }

    public function setActive(int $userId, bool $active): void
    {
        $statement = $this->db->prepare('UPDATE users SET active = ?, status = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $active ? 'ACTIVE' : 'INACTIVE', $userId]);
    }

    public function setRole(int $userId, string $roleCode): void
    {
        $statement = $this->db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $statement->execute([Roles::legacyValueFor($roleCode), $userId]);
    }

    public function touchLastLogin(int $userId): void
    {
        $statement = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $statement->execute([$userId]);
    }
}
