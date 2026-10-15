<?php
// PHILSURIN <-> Google Apps Script bridge (two-way sync).
// Your live Apps Script web app URL + the SAME SYNC_KEY as src/Code.gs.
if (!defined('APPSCRIPT_URL')) define('APPSCRIPT_URL', 'https://script.google.com/macros/s/AKfycbxuIVwo5RLZMTLr_sx0_YGB2KcjWUCPCmhpoSX9nKvyZNh3o6wD_YfKIXxsZEN_SVM-/exec');
if (!defined('APPSCRIPT_SYNC_KEY')) define('APPSCRIPT_SYNC_KEY', 'philsurin-local-2026');

function appscript_call(string $method, string $action, array $params = [], array $body = []): array
{
    $url = rtrim(APPSCRIPT_URL, '/') . '?action=' . urlencode($action) . '&key=' . urlencode(APPSCRIPT_SYNC_KEY);
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode((string)$k) . '=' . urlencode((string)$v);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_merge(['action' => $action, 'key' => APPSCRIPT_SYNC_KEY], $body), JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $raw === '') {
        return ['ok' => false, 'message' => 'Could not reach Apps Script' . ($err ? ': ' . $err : '.')];
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'Apps Script returned non-JSON (HTTP ' . $code . ').'];
    }
    $data['_http'] = $code;
    if (!isset($data['ok'])) $data['ok'] = ($code >= 200 && $code < 300);
    return $data;
}

function appscript_ping(): array
{
    return appscript_call('GET', 'ping');
}

// Push one localhost save to the Sheet Validations tab.
function appscript_push_validation(string $username, string $variety, string $result, string $notes = ''): array
{
    return appscript_call('POST', 'save', [], [
        'username' => $username,
        'variety' => $variety,
        'result' => $result,
        'notes' => $notes,
    ]);
}

// Push many rows at once (used by Sync now button).
function appscript_push_batch(string $username, array $rows): array
{
    return appscript_call('POST', 'save_batch', [], ['username' => $username, 'rows' => array_values($rows)]);
}

// Pull Sheet rows: varieties list + recent validations.
function appscript_pull(int $limit = 100): array
{
    $v = appscript_call('GET', 'varieties');
    $x = appscript_call('GET', 'validations', ['limit' => $limit]);
    return ['ok' => (bool)($v['ok'] ?? false) && (bool)($x['ok'] ?? false), 'varieties' => $v, 'validations' => $x];
}
