<?php
// PHILSURIN offline sync endpoint.
// Mobile/desktop PWA queues encodings in IndexedDB while offline, then POSTs them here as JSON.
// Accepts: { "records": [ {project_location, stage, variety_id, location_size, grading_date, date_planted, dr_dp, control_name, location_id?, field_a:{1..4}, ...}, ... ] }
// Returns per-record results so the client can drop only synced items.
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'POST JSON to this endpoint.']]);
    exit;
}
require_login();
if (function_exists('philsurin_require_encode')) { philsurin_require_encode(); }

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
$records = [];
if (isset($data['records']) && is_array($data['records'])) $records = $data['records'];
elseif (!empty($data)) $records = [$data]; // allow single record POST

if (!$records) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => ['code' => 'EMPTY', 'message' => 'No records to sync.']]);
    exit;
}
if (count($records) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => ['code' => 'TOO_MANY', 'message' => 'Sync max 100 records per request.']]);
    exit;
}

$results = [];
$ok = 0;
foreach ($records as $i => $rec) {
    if (!is_array($rec)) { $results[] = ['index' => $i, 'ok' => false, 'error' => 'Invalid record']; continue; }
    $stage = function_exists('normalize_stage_name') ? normalize_stage_name($rec['stage'] ?? '') : strtoupper(trim((string)($rec['stage'] ?? '')));
    if ($stage !== '' && function_exists('philsurin_can_encode_stage') && !philsurin_can_encode_stage($stage)) {
        $results[] = ['index' => $i, 'ok' => false, 'error' => 'Stage not allowed for your account: ' . $stage];
        continue;
    }
    try {
        $r = save_validation_rows($db, (int) $_SESSION['user']['id'], $rec);
        $ok++;
        $results[] = ['index' => $i, 'ok' => true, 'location_id' => $r['location_id'] ?? '', 'saved' => $r['saved'] ?? 0, 'client_id' => $rec['client_id'] ?? null];
        try {
            if ($db->query("SHOW TABLES LIKE 'audit_logs'")->fetchColumn()) {
                $q = $db->prepare("INSERT INTO audit_logs(user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)");
                $q->execute([(int)$_SESSION['user']['id'], 'OFFLINE_SYNC', 'VALIDATION', (string)($r['location_id'] ?? ''), null, json_encode(['client_id' => $rec['client_id'] ?? null, 'stage' => $stage]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)]);
            }
        } catch (Throwable $ignore) {}
    } catch (Throwable $e) {
        $results[] = ['index' => $i, 'ok' => false, 'error' => $e->getMessage(), 'client_id' => $rec['client_id'] ?? null];
    }
}
echo json_encode(['success' => true, 'data' => ['synced' => $ok, 'total' => count($records), 'results' => $results]], JSON_UNESCAPED_UNICODE);
