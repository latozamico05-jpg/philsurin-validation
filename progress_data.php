<?php
require 'config.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$locationFilter = trim((string) ($_GET['location'] ?? ''));
$sql = 'SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id)';
$params = [];
if ($locationFilter !== '') {
    $sql .= ' AND g.location_id=?';
    $params[] = $locationFilter;
}
$sql .= ' ORDER BY g.location_id,g.grading_stage,v.code';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = array_values(array_filter($stmt->fetchAll(), 'row_has_data'));
$locations = [];
$completed = [];
foreach ($rows as $row) {
    $locationId = $row['location_id'];
    $locations[$locationId] = $row['project_location'];
    $stage = normalize_stage_name($row['grading_stage']);
    $completed[$stage][(int) $row['variety_id']] = true;
}
$varietyTotal = (int) $db->query('SELECT COUNT(*) FROM varieties')->fetchColumn();
$stages = [];
foreach (grading_stage_list($db) as $stage) {
    $count = count($completed[$stage] ?? []);
    $stages[] = [
        'name' => $stage,
        'completed' => $count,
        'total' => $varietyTotal,
        'percent' => $varietyTotal ? min(100, round($count / $varietyTotal * 100)) : 0,
    ];
}
echo json_encode(['locations' => $locations, 'stages' => $stages], JSON_UNESCAPED_UNICODE);
