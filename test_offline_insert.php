<?php
// Test script to insert a record into the offline SQLite DB (CLI only, no Apache needed)
require_once __DIR__ . '/db-offline.php';

$rec = [
    'client_id' => 'TEST-001',
    'user_id' => 1,
    'username' => 'testuser',
    'stage' => 'Germination',
    'project_location' => 'Test Farm',
    'location_id' => 'LOC-001',
    'variety_id' => 1,
    'location_size' => '1.0',
    'date_planted' => '2026-01-01',
    'dr_dp' => null,
    'control_name' => 'Control A',
    'grading_date' => '2026-06-01',
    'replicate' => 1,
    'field_a' => null,
    'field_b' => null,
    'field_c' => null,
    'field_d' => null,
    'field_e' => null,
    'germination' => 85.5,
    'es_value' => 10.2,
    'gs_value' => 12.1,
    'uniformity' => 90.0,
    'remarks' => 'Test record inserted via CLI',
    'synced' => 0,
    'sync_error' => null,
    'queued_at' => date('c'),
    'synced_at' => null,
    'created_at' => date('c'),
];

try {
    $result = philsurin_offline_insert($rec);
    echo "Insert result: " . json_encode($result) . "\n";
    
    $st = philsurin_offline_count();
    echo "Total: {$st['total']} | Synced: {$st['synced']} | Pending: {$st['pending']}\n";
    
    $pending = philsurin_offline_pending();
    echo "Pending records: " . count($pending) . "\n";
    foreach ($pending as $p) {
        echo "  - {$p['client_id']} ({$p['stage']}) - {$p['project_location']}\n";
    }
    
    echo "\nOffline DB is WORKING without Apache/MySQL!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}