<?php
// offline-export.php — Download all offline records as JSON file.
require 'db-offline.php';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="philsurin-offline-export-' . date('Ymd-His') . '.json"');
header('Cache-Control: no-store');
$data = philsurin_offline_export();
echo json_encode(['exported_at' => date('c'), 'total' => count($data), 'records' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);