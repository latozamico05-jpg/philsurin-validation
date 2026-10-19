<?php
// sync-pull.php — PC SIDE. Pull cloud rows DOWN so "all data reflect on the system".
// Run:  php sync-pull.php     (uses CLOUD_URL + SYNC_KEY from env or sync-clock.json)
// Reads last-pull watermark from sync-state.json, GETs cloud /cloud-sync.php?action=pull,
// upserts into LOCAL MySQL on natural keys (no duplicates, safe to re-run).
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

function sp_cfg(): array {
    $state = [];
    $sf = __DIR__ . '/sync-state.json';
    if (is_file($sf)) { $state = json_decode((string)@file_get_contents($sf), true) ?: []; }
    return [
        'url' => getenv('CLOUD_URL') ?: ($state['cloud_url'] ?? ''),
        'key' => getenv('SYNC_KEY') ?: (is_file(__DIR__.'/sync.key') ? trim((string)@file_get_contents(__DIR__.'/sync.key')) : ''),
        'since' => $state['last_pull'] ?? '',
        'state_file' => $sf,
    ];
}
function sp_cols(string $t): array {
    global $db;
    try { $c = []; foreach ($db->query('SHOW COLUMNS FROM `'.$t.'`') as $col) $c[] = (string)$col['Field']; return $c; }
    catch (Throwable $e) { return []; }
}
function sp_upsert(string $t, array $keys, array $row): string {
    global $db;
    $cols = sp_cols($t);
    if (!$cols) return 'skipped';
    $row = array_intersect_key($row, array_flip($cols));
    foreach ($keys as $k) if (!array_key_exists($k, $row)) return 'skipped';
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

$cfg = sp_cfg();
$isCli = (PHP_SAPI === 'cli');
if ($cfg['url'] === '' || $cfg['key'] === '') {
    $msg = 'Set CLOUD_URL + SYNC_KEY first (see CLOUD-SYNC-SETUP.md).';
    if ($isCli) { fwrite(STDERR, $msg."\n"); exit(1); }
    http_response_code(500); echo json_encode(['success'=>false,'error'=>$msg]); exit;
}
$url = rtrim($cfg['url'], '/') . '/cloud-sync.php?action=pull' . ($cfg['since'] !== '' ? '&since='.urlencode($cfg['since']) : '');
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_HTTPHEADER=>['X-SYNC-KEY: '.$cfg['key']]]);
$raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
if ($raw === false || $raw === '') { $m = 'Cloud unreachable'.($err?': '.$err:''); if($isCli){fwrite(STDERR,$m."\n");exit(1);} http_response_code(502); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$dec = json_decode((string)$raw, true);
if (!is_array($dec) || !($dec['success'] ?? false)) { $m = 'Cloud pull failed (HTTP '.$code.').'; if($isCli){fwrite(STDERR,$m.' '.substr((string)$raw,0,300)."\n");exit(1);} http_response_code(502); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$data = $dec['data'] ?? [];
$rules = ['users'=>['username'],'varieties'=>['code'],'roles'=>['role_code'],'system_config'=>['config_key'],'grading_records'=>['location_id','grading_stage','variety_id','replicate']];
$stats = [];
foreach ($rules as $t => $keys) {
    $rows = $data[$t] ?? [];
    $stats[$t] = ['inserted'=>0,'updated'=>0,'skipped'=>0];
    if (!is_array($rows)) continue;
    foreach ($rows as $row) {
        if (!is_array($row)) { $stats[$t]['skipped']++; continue; }
        unset($row['id']);
        try { $r = sp_upsert($t, $keys, $row); $stats[$t][$r]++; }
        catch (Throwable $e) { $stats[$t]['skipped']++; }
    }
}
@file_put_contents($cfg['state_file'], json_encode(['cloud_url'=>$cfg['url'],'last_pull'=>gmdate('Y-m-d H:i:s'),'last_stats'=>$stats], JSON_PRETTY_PRINT));
echo json_encode(['success'=>true,'data'=>['stats'=>$stats,'pulled_at'=>gmdate('c')]], JSON_UNESCAPED_UNICODE);
