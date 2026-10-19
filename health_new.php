<?php
// PHILSURIN health endpoint v2
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = ['ok' => true, 'app' => 'philsurin', 'time' => gmdate('c'), 'db' => 'down'];
require_once __DIR__ . '/db-fallback.php';
try {
    $db = philsurin_open_db();
    $out['db'] = 'up';
    $out['driver'] = function_exists('philsurin_db_driver') ? philsurin_db_driver($db) : 'mysql';
    try { $out['grading_rows'] = (int) $db->query('SELECT COUNT(*) FROM grading_records')->fetchColumn(); } catch (Throwable $t) {}
} catch (Throwable $e) {
    http_response_code(503);
    $out['ok'] = false;
    $out['error'] = 'database unreachable';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
