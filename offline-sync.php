<?php
// offline-sync.php — Push pending offline SQLite records to sync.php (MySQL).
// Call via POST or run via CLI: php offline-sync.php
// Reads pending records from data/philsurin-offline.sqlite and POSTs them to sync.php.
// Marks each as synced after success.
require 'db-offline.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pushPending(): array {
    $pending = philsurin_offline_pending(500);
    $results = [];
    $ok = 0;
    $fail = 0;

    foreach ($pending as $rec) {
        $clientId = $rec['client_id'];
        $payload = [
            'records' => [[
                'client_id' => $clientId,
                'stage' => $rec['stage'],
                'project_location' => $rec['project_location'],
                'location_id' => $rec['location_id'],
                'variety_id' => $rec['variety_id'],
                'location_size' => $rec['location_size'],
                'date_planted' => $rec['date_planted'],
                'dr_dp' => $rec['dr_dp'],
                'control_name' => $rec['control_name'],
                'grading_date' => $rec['grading_date'],
                'replicate' => $rec['replicate'],
                'field_a' => $rec['field_a'] ? json_decode((string)$rec['field_a'], true) : null,
                'field_b' => $rec['field_b'] ? json_decode((string)$rec['field_b'], true) : null,
                'field_c' => $rec['field_c'] ? json_decode((string)$rec['field_c'], true) : null,
                'field_d' => $rec['field_d'] ? json_decode((string)$rec['field_d'], true) : null,
                'field_e' => $rec['field_e'] ? json_decode((string)$rec['field_e'], true) : null,
                'germination' => $rec['germination'],
                'es_value' => $rec['es_value'],
                'gs_value' => $rec['gs_value'],
                'uniformity' => $rec['uniformity'],
                'remarks' => $rec['remarks'],
                'queued_at' => $rec['queued_at'],
            ]],
        ];

        try {
            $ch = curl_init('sync.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $resp = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($resp === false || $httpCode !== 200) {
                philsurin_offline_mark_synced($clientId, false, 'HTTP ' . $httpCode . ($err ? ': ' . $err : ''));
                $results[] = ['client_id' => $clientId, 'ok' => false, 'error' => 'HTTP ' . $httpCode];
                $fail++;
                continue;
            }

            $dec = json_decode($resp, true);
            if ($dec && ($dec['ok'] ?? false)) {
                philsurin_offline_mark_synced($clientId, true, null);
                $results[] = ['client_id' => $clientId, 'ok' => true];
                $ok++;
            } else {
                $msg = $dec['error'] ?? 'Unknown sync error';
                philsurin_offline_mark_synced($clientId, false, $msg);
                $results[] = ['client_id' => $clientId, 'ok' => false, 'error' => $msg];
                $fail++;
            }
        } catch (Throwable $e) {
            philsurin_offline_mark_synced($clientId, false, $e->getMessage());
            $results[] = ['client_id' => $clientId, 'ok' => false, 'error' => $e->getMessage()];
            $fail++;
        }
    }

    return ['ok' => $ok, 'fail' => $fail, 'results' => $results];
}

if (PHP_SAPI === 'cli') {
    $r = pushPending();
    echo "Synced: {$r['ok']} | Failed: {$r['fail']}\n";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$result = pushPending();
echo json_encode(['ok' => true, 'synced' => $result['ok'], 'failed' => $result['fail'], 'results' => $result['results']], JSON_UNESCAPED_UNICODE);