<?php
// Direct SQLite test - bypasses config.php entirely (no Apache/MySQL needed)
$dir = __DIR__ . '/data';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$db = new PDO('sqlite:' . $dir . '/philsurin-offline.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Create table
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

// Insert test record
$stmt = $db->prepare("INSERT OR IGNORE INTO offline_records 
    (client_id, user_id, username, stage, project_location, location_id, variety_id, 
     location_size, date_planted, dr_dp, control_name, grading_date, replicate,
     field_a, field_b, field_c, field_d, field_e, germination, es_value, gs_value, uniformity, remarks,
     synced, sync_error, queued_at, synced_at, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,NULL,datetime('now'))");

$now = date('c');
$result = $stmt->execute([
    'TEST-DIRECT-'.time(), 1, 'testuser', 'Germination', 'Test Farm', 'LOC-001', 1,
    '1.0', '2026-01-01', null, 'Control A', '2026-06-01', 1,
    null, null, null, null, null, 85.5, 10.2, 12.1, 90.0, 'Direct SQLite test',
    $now
]);

echo "Insert result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

// Count records
$total = (int)$db->query('SELECT COUNT(*) FROM offline_records')->fetchColumn();
$pending = (int)$db->query("SELECT COUNT(*) FROM offline_records WHERE synced=0")->fetchColumn();
echo "Total: $total | Pending: $pending\n";

// Show pending records
$rows = $db->query("SELECT * FROM offline_records WHERE synced=0")->fetchAll();
foreach ($rows as $r) {
    echo "  - {$r['client_id']} ({$r['stage']}) - {$r['project_location']} [id={$r['id']}]\n";
}

echo "\nDirect SQLite write WORKS without Apache/MySQL!\n";