<?php
// sync-push.php — PC SIDE. Push local rows UP so cloud mirrors your system.
// Run:  php sync-push.php     (uses CLOUD_URL + SYNC_KEY from env)
// Sends users/varieties/roles/system_config + grading_records changed since
// last_push in sync-state.json (or all on first run). Idempotent: safe to re-run.
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$state = [];
$sf = __DIR__ . '/sync-state.json';
if (is_file($sf)) { $state = json_decode((string)@file_get_contents($sf), true) ?: []; }
$url = getenv('CLOUD_URL') ?: ($state['cloud_url'] ?? '');
$key = getenv('SYNC_KEY') ?: (is_file(__DIR__.'/sync.key') ? trim((string)@file_get_contents(__DIR__.'/sync.key')) : '');
$since = $state['last_push'] ?? '';
$isCli = (PHP_SAPI === 'cli');
if ($url === '' || $key === '') {
    $m = 'Set CLOUD_URL + SYNC_KEY first (see CLOUD-SYNC-SETUP.md).';
    if ($isCli) { fwrite(STDERR, $m."\n"); exit(1); }
    http_response_code(500); echo json_encode(['success'=>false,'error'=>$m]); exit;
}
$has = ($since !== '' && strtotime($since) !== false);
$payload = [];
foreach (['users','varieties','roles','system_config'] as $t) {
    try { $payload[$t] = $db->query('SELECT * FROM `'.$t.'`')->fetchAll(); }
    catch (Throwable $e) { $payload[$t] = []; }
}
try {
    if ($has) { $q = $db->prepare('SELECT * FROM grading_records WHERE created_at >= ? ORDER BY created_at ASC, id ASC LIMIT 5000'); $q->execute([$since]); }
    else { $q = $db->query('SELECT * FROM grading_records ORDER BY created_at ASC, id ASC LIMIT 5000'); }
    $payload['grading_records'] = $q->fetchAll();
} catch (Throwable $e) { $payload['grading_records'] = []; }

$ch = curl_init(rtrim($url, '/') . '/cloud-sync.php?action=push');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-SYNC-KEY: '.$key],
    CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE)]);
$raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
if ($raw === false || $raw === '') { $m='Cloud unreachable'.($err?': '.$err:''); if($isCli){fwrite(STDERR,$m."\n");exit(1);} http_response_code(502); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$dec = json_decode((string)$raw, true);
if (!is_array($dec) || !($dec['success'] ?? false)) { $m='Cloud push failed (HTTP '.$code.').'; if($isCli){fwrite(STDERR,$m.' '.substr((string)$raw,0,300)."\n");exit(1);} http_response_code(502); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$state['cloud_url'] = $url; $state['last_push'] = gmdate('Y-m-d H:i:s'); $state['last_push_stats'] = $dec['data']['stats'] ?? [];
@file_put_contents($sf, json_encode($state, JSON_PRETTY_PRINT));
echo json_encode(['success'=>true,'data'=>$dec['data']], JSON_UNESCAPED_UNICODE);
