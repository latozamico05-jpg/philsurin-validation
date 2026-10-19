<?php
$dir = __DIR__ . '/data';
$db = new PDO('sqlite:' . $dir . '/philsurin-offline.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Check if table exists
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";

// Check record count
$total = (int)$db->query('SELECT COUNT(*) FROM offline_records')->fetchColumn();
echo "Total records: $total\n";

// Try a fresh insert with unique ID
$cid = 'DIAG-' . uniqid();
$stmt = $db->prepare("INSERT INTO offline_records (client_id, user_id, username, stage, project_location, location_id, variety_id, location_size, date_planted, dr_dp, control_name, grading_date, replicate, germination, es_value, gs_value, uniformity, remarks, synced, queued_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,datetime('now'),datetime('now'))");
$result = $stmt->execute([$cid, 1, 'diag', 'Germination', 'Diag Farm', 'LOC-D', 1, '1.0', '2026-01-01', null, 'Control', '2026-06-01', 1, 85.5, 10.2, 12.1, 90.0, 'Diagnostic insert']);
echo "Insert $cid: " . ($result ? "OK" : "FAIL") . "\n";

// Check rowcount
echo "Rows affected: " . $stmt->rowCount() . "\n";

// Count again
$total2 = (int)$db->query('SELECT COUNT(*) FROM offline_records')->fetchColumn();
echo "Total records after insert: $total2\n";

// Show all records
$rows = $db->query("SELECT id, client_id, stage, synced FROM offline_records")->fetchAll();
foreach ($rows as $r) {
    echo "  id={$r['id']} client_id={$r['client_id']} stage={$r['stage']} synced={$r['synced']}\n";
}