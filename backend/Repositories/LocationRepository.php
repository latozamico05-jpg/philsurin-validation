<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the normalised `locations` table.
 *
 * This is the new trial-location registry. The legacy `grading_records` table
 * keeps its free-text location data untouched; new masterlist rows reference
 * these integer location ids.
 */
final class LocationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        $sql = 'SELECT l.*, (SELECT COUNT(*) FROM masterlist m WHERE m.location_id = l.location_id) AS masterlist_count
                FROM locations l';
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= ' WHERE l.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY l.location_code ASC';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $locationId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM locations WHERE location_id = ? LIMIT 1');
        $statement->execute([$locationId]);
        $location = $statement->fetch();

        return $location === false ? null : $location;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $locationCode): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM locations WHERE location_code = ? LIMIT 1');
        $statement->execute([$locationCode]);
        $location = $statement->fetch();

        return $location === false ? null : $location;
    }

    public function codeExists(string $locationCode, ?int $exceptLocationId = null): bool
    {
        $sql = 'SELECT 1 FROM locations WHERE location_code = ?';
        $params = [$locationCode];

        if ($exceptLocationId !== null) {
            $sql .= ' AND location_id <> ?';
            $params[] = $exceptLocationId;
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
            'INSERT INTO locations
                (location_code, project_name, farm_field_address, city_municipality, province, latitude, longitude, status)
             VALUES
                (:location_code, :project_name, :farm_field_address, :city_municipality, :province, :latitude, :longitude, :status)'
        );

        $statement->execute([
            'location_code' => $attributes['location_code'],
            'project_name' => $attributes['project_name'],
            'farm_field_address' => $attributes['farm_field_address'] ?? null,
            'city_municipality' => $attributes['city_municipality'] ?? null,
            'province' => $attributes['province'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'status' => $attributes['status'] ?? 'ACTIVE',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $attributes Only supplied keys are written.
     */
    public function update(int $locationId, array $attributes): void
    {
        $writable = [
            'location_code',
            'project_name',
            'farm_field_address',
            'city_municipality',
            'province',
            'latitude',
            'longitude',
            'status',
        ];

        $assignments = [];
        $params = ['location_id' => $locationId];

        foreach ($writable as $column) {
            if (array_key_exists($column, $attributes)) {
                $assignments[] = $column . ' = :' . $column;
                $params[$column] = $attributes[$column];
            }
        }

        if ($assignments === []) {
            return;
        }

        $statement = $this->db->prepare(
            'UPDATE locations SET ' . implode(', ', $assignments) . ' WHERE location_id = :location_id'
        );
        $statement->execute($params);
    }

    public function delete(int $locationId): void
    {
        $statement = $this->db->prepare('DELETE FROM locations WHERE location_id = ?');
        $statement->execute([$locationId]);
    }

    public function masterlistCount(int $locationId): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM masterlist WHERE location_id = ?');
        $statement->execute([$locationId]);

        return (int) $statement->fetchColumn();
    }
}
