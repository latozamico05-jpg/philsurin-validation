<?php
// cloud-sync.php — 2-WAY SYNC BRIDGE (Render cloud <-> PC/XAMPP).
// PC OFF => cloud keeps collecting. PC back => pull cloud rows down.
// Push local -> cloud: POST ?action=push (JSON). Pull: GET ?action=pull&since=.
// Auth: header X-SYNC-KEY or ?key= equals env SYNC_KEY (cloud) or sync.key (local).
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cs_key(): string {
    $k = getenv('SYNC_KEY');
    if ($k !== false && $k !== '') return (string)$k;
    $f = __DIR__ . '/sync.key';
    if (is_file($f)) return trim((string)@file_get_contents($f));
    return '';
}
function cs_cols(PDO $db, string $t): array {
    try { $c = []; foreach ($db->query('SHOW COLUMNS FROM `'.$t.'`') as $col) $c[] = (string)$col['Field']; return $c; }
    catch (Throwable $e) { return []; }
}
function cs_upsert(PDO $db, string $t, array $keys, array $row): string {
    $cols = cs_cols($db, $t);
    if (!$cols) return 'skipped';
    $row = array_intersect_key($row, array_flip($cols));
    foreach ($keys as $k) if (!isset($row[$k])) return 'skipped';
    $w = implode(' AND ', array_map(fn($k) => "`$k`=?", $keys));
    $q = $db->prepare("SELECT id FROM `$t` WHERE $w LIMIT 1");
    $q->execute(array_map(fn($k) => $row[$k], $keys));
    $id = $q->fetchColumn();
    if ($id) {
        $set = array_diff(array_keys($row), array_merge(['id'], $keys));
        if ($set) {
            $u = $db->prepare('UPDATE `'.$t.'` SET '.implode(',', array_map(fn($c)=>"`$c`=?", $set)).' WHERE id=?');
            $v = array_map(fn($c)=>$row[$c], $set); $v[] = $id; $u->execute($v);
        }
        return 'updated';
    }
    $q = $db->prepare('INSERT INTO `'.$t.'`(`'.implode('`,`', array_keys($row)).'`) VALUES('.implode(',', array_fill(0, count($row), '?')).')');
    $q->execute(array_values($row));
    return 'inserted';
}

$action = strtolower((string)($_GET['action'] ?? 'ping'));
if ($action === 'ping') {
    $st = 'up';
    try { $db->query('SELECT 1'); } catch (Throwable $t) { $st = 'down'; }
    echo json_encode(['success'=>true,'data'=>['app'=>'philsurin-cloud-sync','time'=>gmdate('c'),'db'=>$st,'pc_required'=>false]]);
    exit;
}
$want = cs_key();
if ($want === '') { http_response_code(500); echo json_encode(['success'=>false,'error'=>['code'=>'NO_SYNC_KEY','message'=>'SYNC_KEY missing.']]); exit; }
$got = (string)($_SERVER['HTTP_X_SYNC_KEY'] ?? $_GET['key'] ?? '');
if (!hash_equals($want, $got)) { http_response_code(401); echo json_encode(['success'=>false,'error'=>['code'=>'UNAUTHORIZED','message'=>'Bad sync key.']]); exit; }

if ($action === 'pull') {
    $since = trim((string)($_GET['since'] ?? ''));
    $has = ($since !== '' && strtotime($since) !== false);
    $out = [];
    foreach (['users','varieties','roles','system_config'] as $t) {
        try { $out[$t] = $db->query('SELECT * FROM `'.$t.'`')->fetchAll(); }
        catch (Throwable $e) { $out[$t] = []; }
    }

    try {
        if ($has) { $q = $db->prepare('SELECT * FROM grading_records WHERE created_at >= ? ORDER BY created_at ASC, id ASC LIMIT 5000'); $q->execute([$since]); }
        else { $q = $db->query('SELECT * FROM grading_records ORDER BY created_at ASC, id ASC LIMIT 5000'); }
        $out['grading_records'] = $q->fetchAll();
    } catch (Throwable $e) { $out['grading_records'] = []; }
    try {
        if ($has) { $q = $db->prepare('SELECT * FROM audit_logs WHERE created_at >= ? ORDER BY created_at ASC LIMIT 2000'); $q->execute([$since]); }
        else { $q = $db->query('SELECT * FROM audit_logs ORDER BY created_at ASC LIMIT 2000'); }
        $out['audit_logs'] = $q->fetchAll();
    } catch (Throwable $e) { $out['audit_logs'] = []; }
    echo json_encode(['success'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'push') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>['code'=>'METHOD_NOT_ALLOWED','message'=>'POST JSON.']]); exit; }
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>['code'=>'BAD_JSON','message'=>'Invalid JSON.']]); exit; }
    $rules = ['users'=>['username'],'varieties'=>['code'],'roles'=>['role_code'],'system_config'=>['config_key'],'grading_records'=>['location_id','grading_stage','variety_id','replicate']];
    $stats = [];
    foreach ($rules as $t => $keys) {
        $rows = $data[$t] ?? [];
        if (!is_array($rows)) continue;
        $stats[$t] = ['inserted'=>0,'updated'=>0,'skipped'=>0];
        if (count($rows) > 5000) { $stats[$t]['error'] = 'max 5000 per push'; continue; }
        foreach ($rows as $row) {
            if (!is_array($row)) { $stats[$t]['skipped']++; continue; }
            unset($row['id']);
            try { $r = cs_upsert($db, $t, $keys, $row); $stats[$t][$r]++; }
            catch (Throwable $e) { $stats[$t]['skipped']++; }
        }
    }
    try { audit_log((int)($_SESSION['user']['id'] ?? 0), 'CLOUD_SYNC_PUSH', 'SYNC', (string)($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable $e) {}
    echo json_encode(['success'=>true,'data'=>['stats'=>$stats]], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'error'=>['code'=>'BAD_ACTION','message'=>'Use action=ping|pull|push.']]);
