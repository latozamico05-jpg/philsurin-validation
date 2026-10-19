<?php
// db-offline.php — STANDALONE OFFLINE DATABASE (SQLite file).
// Works WITHOUT MySQL and WITHOUT Apache. Run via PHP CLI:
//   php db-offline.php        (init / status)
//   php db-offline.php export   (export all offline records to JSON)
//   php db-offline.php import <file.json>   (import queued records)
// Stores to: data/philsurin-offline.sqlite  (a real .sqlite file on disk — survives PC off)
declare(strict_types=1);
require_once __DIR__ . '/config.php';

define('OFFLINE_DB_PATH', __DIR__ . '/data/philsurin-offline.sqlite');
define('OFFLINE_STATE_PATH', __DIR__ . '/data/offline-state.json');

if (!function_exists('philsurin_offline_open')) {
    function philsurin_offline_open(): PDO {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $db = new PDO('sqlite:' . OFFLINE_DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        philsurin_offline_schema($db);
        return $db;
    }
}

if (!function_exists('philsurin_offline_schema')) {
    function philsurin_offline_schema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS offline_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id TEXT NOT NULL UNIQUE,
            user_id INTEGER NULL,
            username TEXT NULL,
            stage TEXT NOT NULL,
            project_location TEXT NOT NULL,
            location_id TEXT NULL,
            variety_id INTEGER NULL,
            location_size TEXT NULL,
            date_planted TEXT NULL,
            dr_dp TEXT NULL,
            control_name TEXT NULL,
            grading_date TEXT NOT NULL,
            replicate INTEGER NOT NULL DEFAULT 1,
            field_a TEXT NULL,
            field_b TEXT NULL,
            field_c TEXT NULL,
            field_d TEXT NULL,
            field_e TEXT NULL,
            germination REAL NULL,
            es_value REAL NULL,
            gs_value REAL NULL,
            uniformity REAL NULL,
            remarks TEXT NULL,
            synced INTEGER NOT NULL DEFAULT 0,
            sync_error TEXT NULL,
            queued_at TEXT NOT NULL,
            synced_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_offline_client ON offline_records(client_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_offline_synced ON offline_records(synced)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_offline_created ON offline_records(created_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS offline_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS offline_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id VARCHAR(100) NULL,
            old_data TEXT NULL,
            new_data TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_offline_audit_created ON offline_audit_logs(created_at)");
        /* Also create extended dashboard tables in the offline SQLite database */
        philsurin_offline_schema_extended($db);
    }
}

if (!function_exists('philsurin_offline_schema_extended')) {
    function philsurin_offline_schema_extended(PDO $db): void {
        /* Extended dashboard tables — same logical schema as database/schema.sql, adapted for SQLite */
        $db->exec("CREATE TABLE IF NOT EXISTS roles (
            role_id INTEGER PRIMARY KEY AUTOINCREMENT,
            role_code TEXT NOT NULL UNIQUE,
            role_name TEXT NOT NULL,
            description TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY(user_id, role_id)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS locations (
            location_id INTEGER PRIMARY KEY AUTOINCREMENT,
            location_code TEXT NOT NULL UNIQUE,
            project_name TEXT NOT NULL,
            farm_field_address TEXT,
            city_municipality TEXT,
            province TEXT,
            latitude REAL,
            longitude REAL,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS user_locations (
            user_id INTEGER NOT NULL,
            location_id INTEGER NOT NULL,
            assigned_by INTEGER NULL,
            assigned_at TEXT NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY(user_id, location_id)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS system_config (
            config_key TEXT PRIMARY KEY,
            config_value TEXT,
            description TEXT,
            updated_by INTEGER NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS measurement_definitions (
            code TEXT PRIMARY KEY,
            stage TEXT NOT NULL,
            label TEXT NOT NULL,
            data_type TEXT NOT NULL,
            required INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS masterlist (
            masterlist_id INTEGER PRIMARY KEY AUTOINCREMENT,
            record_id TEXT NOT NULL UNIQUE,
            project_name TEXT NOT NULL,
            location_id INTEGER NULL,
            variety_id INTEGER NULL,
            dr_number TEXT,
            date_gathered TEXT,
            grading_35mar_date TEXT,
            grading_4mar_date TEXT,
            grading_6mar_date TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS validation_sessions (
            validation_session_id INTEGER PRIMARY KEY AUTOINCREMENT,
            masterlist_id INTEGER NOT NULL,
            validator_id INTEGER NULL,
            status TEXT NOT NULL DEFAULT 'PENDING',
            notes TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY(masterlist_id) REFERENCES masterlist(masterlist_id),
            FOREIGN KEY(validator_id) REFERENCES users(id)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS dashboard_daily_metrics (
            metric_date TEXT PRIMARY KEY,
            total_masterlist INTEGER NOT NULL DEFAULT 0,
            validated_records INTEGER NOT NULL DEFAULT 0,
            pending_records INTEGER NOT NULL DEFAULT 0,
            recommendations INTEGER NOT NULL DEFAULT 0,
            grading_01_completed INTEGER NOT NULL DEFAULT 0,
            grading_02_completed INTEGER NOT NULL DEFAULT 0,
            grading_03_completed INTEGER NOT NULL DEFAULT 0,
            grading_04_completed INTEGER NOT NULL DEFAULT 0,
            harvest_completed INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS dashboard_monthly_metrics (
            year_month TEXT PRIMARY KEY,
            total_records INTEGER NOT NULL DEFAULT 0,
            validated_records INTEGER NOT NULL DEFAULT 0,
            pending_records INTEGER NOT NULL DEFAULT 0,
            recommendations INTEGER NOT NULL DEFAULT 0,
            completion_percent REAL NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_masterlist_record_id ON masterlist(record_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_masterlist_location ON masterlist(location_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_masterlist_variety ON masterlist(variety_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_validation_sessions_masterlist ON validation_sessions(masterlist_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_validation_sessions_status ON validation_sessions(status)");
    }
}

if (!function_exists('philsurin_offline_count')) {
    function philsurin_offline_count(): array {
        $db = philsurin_offline_open();
        $total = (int)$db->query('SELECT COUNT(*) FROM offline_records')->fetchColumn();
        $synced = (int)$db->query("SELECT COUNT(*) FROM offline_records WHERE synced=1")->fetchColumn();
        $pending = (int)$db->query("SELECT COUNT(*) FROM offline_records WHERE synced=0")->fetchColumn();
        return ['total' => $total, 'synced' => $synced, 'pending' => $pending];
    }
}

if (!function_exists('philsurin_offline_insert')) {
    function philsurin_offline_insert(array $rec): array {
        $db = philsurin_offline_open();
        $clientId = $rec['client_id'] ?? ('c' . date('YmdHis') . '-' . bin2hex(random_bytes(4)));
        $stage = $rec['stage'] ?? '';
        $projectLocation = $rec['project_location'] ?? '';
        $locationId = $rec['location_id'] ?? null;
        $varietyId = $rec['variety_id'] ?? null;
        $locationSize = $rec['location_size'] ?? null;
        $datePlanted = $rec['date_planted'] ?? null;
        $drDp = $rec['dr_dp'] ?? null;
        $controlName = $rec['control_name'] ?? null;
        $gradingDate = $rec['grading_date'] ?? date('Y-m-d');
        $replicate = (int)($rec['replicate'] ?? 1);
        $fieldA = isset($rec['field_a']) ? json_encode($rec['field_a']) : null;
        $fieldB = isset($rec['field_b']) ? json_encode($rec['field_b']) : null;
        $fieldC = isset($rec['field_c']) ? json_encode($rec['field_c']) : null;
        $fieldD = isset($rec['field_d']) ? json_encode($rec['field_d']) : null;
        $fieldE = isset($rec['field_e']) ? json_encode($rec['field_e']) : null;
        $germination = $rec['germination'] ?? null;
        $esValue = $rec['es_value'] ?? null;
        $gsValue = $rec['gs_value'] ?? null;
        $uniformity = $rec['uniformity'] ?? null;
        $remarks = $rec['remarks'] ?? null;
        $queuedAt = $rec['queued_at'] ?? date('c');
        $userId = $rec['user_id'] ?? null;
        $username = $rec['username'] ?? null;

        $q = $db->prepare("INSERT OR IGNORE INTO offline_records
            (client_id, user_id, username, stage, project_location, location_id, variety_id,
             location_size, date_planted, dr_dp, control_name, grading_date, replicate,
             field_a, field_b, field_c, field_d, field_e,
             germination, es_value, gs_value, uniformity, remarks, queued_at, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $q->execute([
            $clientId, $userId, $username, $stage, $projectLocation, $locationId, $varietyId,
            $locationSize, $datePlanted, $drDp, $controlName, $gradingDate, $replicate,
            $fieldA, $fieldB, $fieldC, $fieldD, $fieldE,
            $germination, $esValue, $gsValue, $uniformity, $remarks, $queuedAt, date('c'),
        ]);
        return ['ok' => true, 'client_id' => $clientId, 'message' => 'Stored in offline database.'];
    }
}

if (!function_exists('philsurin_offline_pending')) {
    function philsurin_offline_pending(int $limit = 500): array {
        $db = philsurin_offline_open();
        $q = $db->prepare('SELECT * FROM offline_records WHERE synced=0 ORDER BY created_at ASC LIMIT ?');
        $q->execute([$limit]);
        return $q->fetchAll();
    }
}

if (!function_exists('philsurin_offline_mark_synced')) {
    function philsurin_offline_mark_synced(string $clientId, bool $ok = true, string $error = null): bool {
        $db = philsurin_offline_open();
        $q = $db->prepare("UPDATE offline_records SET synced=1, sync_error=?, synced_at=? WHERE client_id=? AND synced=0");
        return (bool)$q->execute([$error, date('c'), $clientId]);
    }
}

if (!function_exists('philsurin_offline_export')) {
    function philsurin_offline_export(): array {
        $db = philsurin_offline_open();
        $rows = $db->query('SELECT * FROM offline_records ORDER BY created_at DESC')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'client_id' => $r['client_id'],
                'user_id' => $r['user_id'],
                'username' => $r['username'],
                'stage' => $r['stage'],
                'project_location' => $r['project_location'],
                'location_id' => $r['location_id'],
                'variety_id' => $r['variety_id'],
                'location_size' => $r['location_size'],
                'date_planted' => $r['date_planted'],
                'dr_dp' => $r['dr_dp'],
                'control_name' => $r['control_name'],
                'grading_date' => $r['grading_date'],
                'replicate' => $r['replicate'],
                'field_a' => $r['field_a'] ? json_decode((string)$r['field_a'], true) : null,
                'field_b' => $r['field_b'] ? json_decode((string)$r['field_b'], true) : null,
                'field_c' => $r['field_c'] ? json_decode((string)$r['field_c'], true) : null,
                'field_d' => $r['field_d'] ? json_decode((string)$r['field_d'], true) : null,
                'field_e' => $r['field_e'] ? json_decode((string)$r['field_e'], true) : null,
                'germination' => $r['germination'],
                'es_value' => $r['es_value'],
                'gs_value' => $r['gs_value'],
                'uniformity' => $r['uniformity'],
                'remarks' => $r['remarks'],
                'synced' => (int)$r['synced'],
                'sync_error' => $r['sync_error'],
                'queued_at' => $r['queued_at'],
                'synced_at' => $r['synced_at'],
                'created_at' => $r['created_at'],
            ];
        }
        return $out;
    }
}

if (!function_exists('philsurin_offline_pending')) {
    function philsurin_offline_pending(): array {
        $db = philsurin_offline_open();
        $rows = $db->query("SELECT * FROM offline_records WHERE synced=0 ORDER BY created_at ASC")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'client_id' => $r['client_id'],
                'user_id' => $r['user_id'] ? (int)$r['user_id'] : null,
                'username' => $r['username'],
                'stage' => $r['stage'],
                'project_location' => $r['project_location'],
                'location_id' => $r['location_id'],
                'variety_id' => $r['variety_id'] ? (int)$r['variety_id'] : null,
                'location_size' => $r['location_size'],
                'date_planted' => $r['date_planted'],
                'dr_dp' => $r['dr_dp'],
                'control_name' => $r['control_name'],
                'grading_date' => $r['grading_date'],
                'replicate' => (int)$r['replicate'],
                'field_a' => $r['field_a'] ? json_decode((string)$r['field_a'], true) : null,
                'field_b' => $r['field_b'] ? json_decode((string)$r['field_b'], true) : null,
                'field_c' => $r['field_c'] ? json_decode((string)$r['field_c'], true) : null,
                'field_d' => $r['field_d'] ? json_decode((string)$r['field_d'], true) : null,
                'field_e' => $r['field_e'] ? json_decode((string)$r['field_e'], true) : null,
                'germination' => $r['germination'],
                'es_value' => $r['es_value'],
                'gs_value' => $r['gs_value'],
                'uniformity' => $r['uniformity'],
                'remarks' => $r['remarks'],
                'synced' => (int)$r['synced'],
                'sync_error' => $r['sync_error'],
                'queued_at' => $r['queued_at'],
                'synced_at' => $r['synced_at'],
                'created_at' => $r['created_at'],
            ];
        }
        return $out;
    }
}
if (!function_exists('philsurin_offline_mark_synced')) {
    function philsurin_offline_mark_synced(int $id, string $sync_error = null): bool {
        $db = philsurin_offline_open();
        $stmt = $db->prepare("UPDATE offline_records SET synced=1, synced_at=datetime('now'), sync_error=? WHERE id=?");
        return (bool)$stmt->execute([$sync_error, $id]);
    }
}

if (!function_exists('philsurin_offline_audit_log')) {
    function philsurin_offline_audit_log(int $userId = null, string $action, string $entityType = null, string $entityId = null, ?array $oldData = null, ?array $newData = null): void {
        $db = philsurin_offline_open();
        $q = $db->prepare("INSERT INTO offline_audit_logs(user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)");
        $q->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        ]);
    }
}

if (!function_exists('philsurin_offline_is_primary_down')) {
    function philsurin_offline_is_primary_down(): bool {
        $driver = strtolower((string)($GLOBALS['DB_DRIVER'] ?? 'mysql'));
        return $driver === 'sqlite';
    }
}

// CLI entry point -----------------------------------------------------------
if (PHP_SAPI === 'cli') {
    $cmd = $argv[1] ?? 'status';
    try {
        switch ($cmd) {
            case 'init':
            case 'status':
                $st = philsurin_offline_count();
                echo "Offline DB: " . OFFLINE_DB_PATH . "\n";
                echo "Total records: {$st['total']} | Synced: {$st['synced']} | Pending: {$st['pending']}\n";
                break;
            case 'export':
                $data = philsurin_offline_export();
                $file = __DIR__ . '/data/offline-export-' . date('Ymd-His') . '.json';
                @file_put_contents($file, json_encode(['exported_at' => date('c'), 'records' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo "Exported " . count($data) . " records to $file\n";
                break;
            case 'import':
                $file = $argv[2] ?? '';
                if (!$file || !is_file($file)) { echo "Usage: php db-offline.php import <file.json>\n"; exit(1); }
                $payload = json_decode((string)@file_get_contents($file), true);
                $records = $payload['records'] ?? $payload ?? [];
                $n = 0;
                foreach ($records as $rec) {
                    if (!is_array($rec)) continue;
                    philsurin_offline_insert($rec);
                    $n++;
                }
                echo "Imported $n records.\n";
                break;
            case 'sync':
                $pending = philsurin_offline_pending();
                echo "Pending offline records: " . count($pending) . "\n";
                foreach ($pending as $rec) {
                    echo "  - {$rec['client_id']} ({$rec['stage']}) {$rec['project_location']}\n";
                }
                break;
            default:
                echo "Usage: php db-offline.php {status|export|import <file>|sync}\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    exit;
}