<?php
// Public-but-login-protected data bundle cached by the PWA for offline encoding.
// GET offline-data.php -> {varieties:[{id,code,name}], stages:[...]}
require 'config.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
try {
    $varieties = $db->query('SELECT id, code, name FROM varieties WHERE active=1 ORDER BY code')->fetchAll();
} catch (Throwable $e) { $varieties = []; }
$stages = function_exists('grading_stage_list') ? grading_stage_list($db) : ['01 GRADING','02 GRADING','03 GRADING','04 GRADING','HARVEST'];
$allowed = function_exists('philsurin_allowed_stages') ? philsurin_allowed_stages() : $stages;
echo json_encode(['varieties' => $varieties, 'stages' => array_values($stages), 'allowedStages' => array_values($allowed), 'cached_at' => date('c')], JSON_UNESCAPED_UNICODE);
