<?php
// offline-store.php — Accepts encoding data and stores it in the offline SQLite DB
// so data is saved even when MySQL/Apache is down. Call via fetch POST (JSON).
// Returns: { ok: true, client_id: "..." } or { ok: false, error: "..." }
require 'db-offline.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Allow single record or batch
$records = isset($data['records']) && is_array($data['records']) ? $data['records'] : [$data];

$results = [];
foreach ($records as $i => $rec) {
    try {
        $r = philsurin_offline_insert($rec);
        $results[] = ['index' => $i, 'ok' => true, 'client_id' => $r['client_id']];
    } catch (Throwable $e) {
        $results[] = ['index' => $i, 'ok' => false, 'error' => $e->getMessage()];
    }
}

$allOk = !array_filter($results, fn($x) => !$x['ok']);
echo json_encode([
    'ok' => $allOk,
    'message' => $allOk ? 'Stored in offline database.' : 'Some records failed.',
    'results' => $results,
], JSON_UNESCAPED_UNICODE);