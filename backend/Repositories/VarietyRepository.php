<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the `varieties` master table.
 *
 * The legacy table uses `id / code / name`; the API exposes those as
 * varietyId / varietyCode / varietyName so the wire format matches
 * docs/DATABASE.md.
 */
final class VarietyRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT v.id AS variety_id, v.code AS variety_code, v.name AS variety_name,
                       v.description, v.active, v.created_at, v.updated_at,
                       (SELECT COUNT(*) FROM masterlist m WHERE m.variety_id = v.id) AS masterlist_count
                FROM varieties v';

        if ($onlyActive) {
            $sql .= ' WHERE v.active = 1';
        }

        $sql .= ' ORDER BY v.code ASC';

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $varietyId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id AS variety_id, code AS variety_code, name AS variety_name, description, active,
                    created_at, updated_at
             FROM varieties WHERE id = ? LIMIT 1'
        );
        $statement->execute([$varietyId]);
        $variety = $statement->fetch();

        return $variety === false ? null : $variety;
    }

    public function codeExists(string $code, ?int $exceptVarietyId = null): bool
    {
        $sql = 'SELECT 1 FROM varieties WHERE code = ?';
        $params = [$code];

        if ($exceptVarietyId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptVarietyId;
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
            'INSERT INTO varieties (code, name, description, active)
             VALUES (:code, :name, :description, :active)'
        );

        $statement->execute([
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'active' => $attributes['active'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(int $varietyId, array $attributes): void
    {
        $assignments = [];
        $params = ['id' => $varietyId];

        foreach (['code', 'name', 'description', 'active'] as $column) {
            if (array_key_exists($column, $attributes)) {
                $assignments[] = $column . ' = :' . $column;
                $params[$column] = $attributes[$column];
            }
        }

        if ($assignments === []) {
            return;
        }

        $statement = $this->db->prepare(
            'UPDATE varieties SET ' . implode(', ', $assignments) . ' WHERE id = :id'
        );
        $statement->execute($params);
    }

    public function masterlistCount(int $varietyId): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM masterlist WHERE variety_id = ?');
        $statement->execute([$varietyId]);

        return (int) $statement->fetchColumn();
    }
}
