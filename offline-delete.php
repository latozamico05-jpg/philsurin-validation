<?php
// offline-delete.php — Delete one offline record by client_id.
require 'db-offline.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$clientId = $_POST['client_id'] ?? ($_GET['client_id'] ?? '');
if (!$clientId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'client_id required']);
    exit;
}

try {
    $db = philsurin_offline_open();
    $q = $db->prepare('DELETE FROM offline_records WHERE client_id=?');
    $q->execute([$clientId]);
    echo json_encode(['ok' => true, 'deleted' => (int)$q->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}