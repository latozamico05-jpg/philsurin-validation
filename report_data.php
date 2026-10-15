<?php
require 'config.php';
require_login();
header('Content-Type: application/json');

$location = $_GET['location'] ?? '';
if ($location) {
    $q = $db->prepare('SELECT grading_stage, location_size, grading_date FROM grading_records WHERE location_id=? AND NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=grading_records.location_id) ORDER BY id DESC');
    $q->execute([$location]);
} else {
    $q = $db->query('SELECT grading_stage, location_size, grading_date FROM grading_records WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=grading_records.location_id) ORDER BY id DESC');
}

$out = [];
foreach ($q as $r) {
    $key = normalize_stage_name($r['grading_stage'] ?? '');
    if ($key === '') continue;
    if (!isset($out[$key])) {
        $out[$key] = ['size' => $r['location_size'] ?? '', 'date' => $r['grading_date'] ?? ''];
    }
}
echo json_encode($out);

