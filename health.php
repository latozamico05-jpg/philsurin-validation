<?php
// PHILSURIN health endpoint for 24/7 uptime monitors (UptimeRobot, cron, Docker HEALTHCHECK).
// GET health.php -> {"ok":true,...}  No login required. Never exposes credentials.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = ['ok' => true, 'app' => 'philsurin', 'time' => gmdate('c'), 'db' => 'down', 'pending_sync_hint' => 'POST sync.php when online'];
try {
    $h = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '';
    $n = getenv('DB_NAME') ?: 'philsurin';
    $u = getenv('DB_USER') ?: 'root';
    $p = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $host = $port !== '' ? "{$h};port={$port}" : $h;
    $db = new PDO("mysql:host={$host};dbname={$n};charset=utf8mb4", $u, $p, [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->query('SELECT 1');
    $out['db'] = 'up';
    try { $out['grading_rows'] = (int) $db->query('SELECT COUNT(*) FROM grading_records')->fetchColumn(); } catch (Throwable $t) {}
} catch (Throwable $e) {
    http_response_code(503);
    $out['ok'] = false;
    $out['error'] = 'database unreachable';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
