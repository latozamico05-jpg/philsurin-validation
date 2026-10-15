<?php
require 'config.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['stages'])) {
    echo json_encode(grading_stage_list($db), JSON_UNESCAPED_UNICODE);
    exit;
}

$locationId = trim((string) ($_GET['location'] ?? ''));
$stage = normalize_stage_name($_GET['stage'] ?? '');
$varietyId = (int) ($_GET['variety_id'] ?? 0);
if ($locationId === '' || $stage === '' || $varietyId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Location, stage, and variety are required.']);
    exit;
}

$stmt = $db->prepare('SELECT * FROM grading_records WHERE location_id=? AND UPPER(REPLACE(grading_stage, "_", " "))=? AND variety_id=? ORDER BY created_at DESC, id DESC');
$stmt->execute([$locationId, $stage, $varietyId]);
$rows = [];
$meta = null;
foreach ($stmt->fetchAll() as $row) {
    $replicate = (int) $row['replicate'];
    if (isset($rows[$replicate])) {
        continue;
    }
    $details = parse_remarks($row['remarks'] ?? '');
    $rows[$replicate] = [
        'a' => $details['a'] ?? $row['germination'] ?? '',
        'b' => $details['b'] ?? $row['es_value'] ?? '',
        'c' => $details['c'] ?? $row['gs_value'] ?? '',
        'd' => $details['d'] ?? '',
        'e' => $details['e'] ?? '',
        'remarks' => $details['remarks'] ?? '',
    ];
    $meta ??= [
        'location_id' => $row['location_id'],
        'project_location' => $row['project_location'],
        'date_planted' => $row['date_planted'],
        'dr_dp' => $row['dr_dp'],
        'control_name' => $row['control_name'],
        'location_size' => $row['location_size'],
        'grading_date' => $row['grading_date'],
    ];
}

if ($meta === null) {
    $locationStmt = $db->prepare('SELECT location_id,project_location,date_planted,dr_dp,control_name FROM grading_records WHERE location_id=? ORDER BY created_at DESC,id DESC LIMIT 1');
    $locationStmt->execute([$locationId]);
    $location = $locationStmt->fetch();
    if ($location) {
        $meta = array_merge($location, ['location_size' => '', 'grading_date' => date('Y-m-d')]);
    }
}

echo json_encode(['meta' => $meta, 'replicates' => $rows], JSON_UNESCAPED_UNICODE);
