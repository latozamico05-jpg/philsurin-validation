<?php
require 'config.php';
require_login();
// Admin-only: the activity feed shows who used the system and from where.
philsurin_require_audit();
require 'sidebar.php';

$hiddenIds = $db->query('SELECT location_id FROM hidden_trial_locations')->fetchAll(PDO::FETCH_COLUMN);
$hidden = array_fill_keys($hiddenIds, true);
// System activity feed: WHO did WHAT at WHICH location and WHEN.
// audit_logs is written by audit_log() on LOGIN + SAVE_VALIDATION (web, mobile,
// API). If the table is missing (older DB), fall back to deriving activity
// from grading_records so the page never whitescreens.
$activity = [];
$activityError = '';
try {
    $auditIdCol = philsurin_audit_id_column($db);
    $activity = $db->query("SELECT a.{$auditIdCol} AS id,a.user_id,COALESCE(u.username,'—') AS username,COALESCE(u.role,'—') AS role,a.action,a.entity_type,a.entity_id,a.created_at FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC,a.{$auditIdCol} DESC LIMIT 200")->fetchAll();
} catch (Throwable $t) {
    try {
        $activity = $db->query("SELECT g.id,g.user_id,COALESCE(u.username,'—') AS username,COALESCE(u.role,'—') AS role,CONCAT('ENCODE ',g.grading_stage) AS action,'VALIDATION' AS entity_type,g.location_id AS entity_id,g.created_at FROM grading_records g LEFT JOIN users u ON u.id=g.user_id ORDER BY g.created_at DESC,g.id DESC LIMIT 200")->fetchAll();
        $activityError = 'Live audit table not installed yet — showing encoded rows as activity.';
    } catch (Throwable $t2) {
        $activityError = 'No audit data available.';
    }
}
// Location backtrack matrix (existing behaviour, unchanged).
$rows = $db->query('SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
$locations = [];
$matrix = [];
foreach ($rows as $row) {
    if (!row_has_data($row) || isset($hidden[$row['location_id']])) {
        continue;
    }
    $locationId = $row['location_id'];
    $stage = normalize_stage_name($row['grading_stage']);
    $varietyId = (int) $row['variety_id'];
    if (!isset($locations[$locationId])) {
        $locations[$locationId] = [
            'location_id' => $locationId,
            'project_location' => $row['project_location'],
            'location_size' => $row['location_size'],
            'date_planted' => $row['date_planted'],
            'dr_dp' => $row['dr_dp'],
            'encoded_rows' => 0,
        ];
    }
    $locations[$locationId]['encoded_rows']++;
    $matrix[$locationId][$varietyId]['code'] = $row['code'];
    $matrix[$locationId][$varietyId]['name'] = $row['name'];
    $matrix[$locationId][$varietyId]['stages'][$stage] = ($matrix[$locationId][$varietyId]['stages'][$stage] ?? 0) + 1;
}
$selected = (string) ($_GET['location'] ?? (array_key_first($locations) ?? ''));
if (!isset($locations[$selected])) {
    $selected = (string) (array_key_first($locations) ?? '');
}
// Every variety is listed for the selected location — even ones with no rows yet —
// so each stage from 01 GRADING to HARVEST can be backtracked or newly input.
$encodedVarieties = $matrix[$selected] ?? [];
$varieties = [];
foreach ($db->query('SELECT id, code, name FROM varieties WHERE active=1 ORDER BY code')->fetchAll() as $varietyRow) {
    $varieties[(int) $varietyRow['id']] = ['code' => $varietyRow['code'], 'name' => $varietyRow['name'], 'stages' => []];
}
foreach ($encodedVarieties as $varietyId => $varietyData) {
    if (!isset($varieties[$varietyId])) {
        $varieties[$varietyId] = ['code' => $varietyData['code'], 'name' => $varietyData['name'], 'stages' => []];
    }
    $varieties[$varietyId]['stages'] = $varietyData['stages'] ?? [];
}
$stages = grading_stage_list($db);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit Trail</title>
<link rel="stylesheet" href="style.css">
<style>
.audit,.matrix{width:100%;border-collapse:collapse}.audit th,.audit td,.matrix th,.matrix td{border:1px solid #d7e0ea;padding:9px;text-align:left}.audit th,.matrix th{background:#e4f1eb;font-size:10px}.audit tr.selected{background:#f0f8f4}.audit tr.pick{cursor:pointer}.matrix th,.matrix td{text-align:center}.matrix th:first-child,.matrix td:first-child{text-align:left}.check-link{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #0b805d;border-radius:3px;color:#087f59;text-decoration:none;font-weight:800;line-height:1}.check-link small{font-size:8px;font-weight:700;margin-top:1px}.empty-box{display:inline-grid;place-items:center;width:30px;height:30px;border:1px solid #b8c4ce;color:#8793a0;text-decoration:none}.table-wrap{overflow:auto}.act-user{font-weight:800}.act-role{color:#748196;font-size:10px;margin-left:6px}.act-loc{font-family:Consolas,monospace;font-size:11px}@media(max-width:760px){.audit{min-width:900px}.matrix{min-width:700px}}
</style>
</head>
<body>
<main>
<header><h1>PHILSURIN</h1><span><a href="index.php">ENCODING</a> <a href="report.php">REPORT</a></span></header>
<section class="card"><h2>System activity</h2><p class="hint">Who used the system, what they did, at which location, and when — latest 200 events.</p>
<?php if($activityError):?><p class="hint"><?=e($activityError)?></p><?php endif?>
<div class="table-wrap"><table class="audit"><thead><tr><th>WHEN</th><th>USER</th><th>ACTION</th><th>LOCATION</th></tr></thead><tbody>
<?php foreach($activity as $a):?><tr><td><?=e($a['created_at'])?></td><td><span class="act-user"><?=e($a['username'])?></span><span class="act-role"><?=e(strtoupper($a['role']))?></span></td><td><?=e($a['action'])?><?php if(!empty($a['entity_type'])&&$a['entity_type']!=='VALIDATION'):?> <span class="act-role"><?=e($a['entity_type'])?></span><?php endif?></td><td><?php if(!empty($a['entity_id'])):?><a class="act-loc" href="audit.php?location=<?=urlencode($a['entity_id'])?>"><?=e($a['entity_id'])?></a><?php else:?>—<?php endif?></td></tr><?php endforeach?>
<?php if(!$activity):?><tr><td colspan="4">No system activity yet. Logins and saved validations will appear here.</td></tr><?php endif?>
</tbody></table></div></section>
<section class="card"><h2>Locations</h2><p class="hint">Select a saved Location ID to backtrack every variety across all gradings to harvest for that location. Click ✓ to reopen encoded values, or □ to input data.</p>
<div class="table-wrap"><table class="audit"><thead><tr><th>LOCATION ID</th><th>LOCATION OF PROJECT</th><th>SIZE OF LOCATION</th><th>DATE PLANTED</th><th>DR / DP</th><th>ENCODED ROWS</th></tr></thead><tbody>
<?php foreach ($locations as $item): ?><tr class="pick <?=($item['location_id'] === $selected ? 'selected' : '')?>" onclick="window.location='audit.php?location=<?=urlencode($item['location_id'])?>'"><td><?=e($item['location_id'])?></td><td><?=e($item['project_location'])?></td><td><?=e($item['location_size'])?></td><td><?=e($item['date_planted'] ?: '—')?></td><td><?=e($item['dr_dp'] ?: '—')?></td><td><?=e($item['encoded_rows'])?></td></tr><?php endforeach ?>
<?php if (!$locations): ?><tr><td colspan="6">No saved validation data.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php if ($selected !== '' && isset($locations[$selected])): ?>
<section class="card"><h2><?=e($selected)?></h2><p class="hint"><?=e($locations[$selected]['project_location'])?> · Size <?=e($locations[$selected]['location_size'])?> · Planted <?=e($locations[$selected]['date_planted'] ?: '—')?></p><a class="smallbtn" href="report.php?location=<?=urlencode($selected)?>">OPEN LOCATION REPORT</a>
<div class="table-wrap"><table class="matrix"><thead><tr><th>VARIETY</th><?php foreach ($stages as $stage): ?><th><?=e($stage)?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($varieties as $varietyId => $variety): ?><tr><td><?=e($variety['code'] . ' ' . $variety['name'])?></td><?php foreach ($stages as $stage): ?><td><?php $encodedCount = (int) ($variety['stages'][$stage] ?? 0); if ($encodedCount > 0): ?><a class="check-link" title="<?=e($encodedCount)?> replicate row(s) — open <?=e($stage)?> for <?=e($variety['code'])?> at <?=e($selected)?>" href="index.php?location=<?=urlencode($selected)?>&stage=<?=urlencode($stage)?>&variety_id=<?=$varietyId?>">✓<small><?=e($encodedCount)?></small></a><?php else: ?><a class="empty-box" title="No <?=e($stage)?> data for <?=e($variety['code'])?> at <?=e($selected)?> — input now" href="index.php?location=<?=urlencode($selected)?>&stage=<?=urlencode($stage)?>&variety_id=<?=$varietyId?>">□</a><?php endif ?></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div></section>
<?php endif ?>
</main>
</body>
</html>
