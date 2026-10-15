<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the `user_locations` assignment table.
 *
 * A location assignment scopes a validator to specific trials. Administrators
 * bypass this check entirely (see the authorization service).
 */
final class UserLocationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT l.location_id, l.location_code, l.project_name, l.status, ul.assigned_at, ul.assigned_by
             FROM user_locations ul
             JOIN locations l ON l.location_id = ul.location_id
             WHERE ul.user_id = ?
             ORDER BY l.location_code'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, int>
     */
    public function locationIdsForUser(int $userId): array
    {
        $statement = $this->db->prepare('SELECT location_id FROM user_locations WHERE user_id = ?');
        $statement->execute([$userId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function isAssigned(int $userId, int $locationId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM user_locations WHERE user_id = ? AND location_id = ? LIMIT 1'
        );
        $statement->execute([$userId, $locationId]);

        return (bool) $statement->fetchColumn();
    }

    public function assign(int $userId, int $locationId, ?int $assignedBy = null): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO user_locations (user_id, location_id, assigned_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([$userId, $locationId, $assignedBy]);
    }

    public function remove(int $userId, int $locationId): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM user_locations WHERE user_id = ? AND location_id = ?'
        );
        $statement->execute([$userId, $locationId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Replace a user's assignments with the supplied location ids.
     *
     * @param array<int, int> $locationIds
     */
    public function sync(int $userId, array $locationIds, ?int $assignedBy = null): void
    {
        $this->db->beginTransaction();

        try {
            $delete = $this->db->prepare('DELETE FROM user_locations WHERE user_id = ?');
            $delete->execute([$userId]);

            $insert = $this->db->prepare(
                'INSERT IGNORE INTO user_locations (user_id, location_id, assigned_by) VALUES (?, ?, ?)'
            );

            foreach (array_unique($locationIds) as $locationId) {
                $insert->execute([$userId, (int) $locationId, $assignedBy]);
            }

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }
}
