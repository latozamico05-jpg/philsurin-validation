<?php
require 'config.php';
require_login();
require_once __DIR__ . '/sidebar.php';
$canManageLocations = in_array(strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')), ['admin', 'validator', 'validator_field'], true);
$canExcel = in_array(strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')), ['admin', 'validator', 'validator_field', 'validator_germination', 'validator_harvest'], true);
$importFlash = $_SESSION['import_flash'] ?? null;
unset($_SESSION['import_flash']);
$location = trim((string) ($_GET['location'] ?? ''));
if ($location === '') {
    $candidates = $db->query('SELECT g.location_id,g.germination,g.es_value,g.gs_value,g.remarks FROM grading_records g WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id) ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
    foreach ($candidates as $candidate) {
        if (row_has_data($candidate)) {
            $location = $candidate['location_id'];
            break;
        }
    }
}
$where = ' WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id) ';
if ($location !== '') {
    $where .= ' AND g.location_id=? ';
}
$q = $db->prepare('SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id'.$where.' ORDER BY v.code,g.replicate,g.created_at');
$q->execute($location !== '' ? [$location] : []);
$rows = $q->fetchAll();
$groups = build_report_groups($rows);
$stages = grading_stage_list($db);
$first = $rows[0] ?? [];
$varietyCount = count($groups);
$recordCount = count(array_filter($rows, 'row_has_data'));
$locationRows = $db->query('SELECT g.* FROM grading_records g WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id) ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
$trialLocations = [];
foreach ($locationRows as $row) {
    if (!row_has_data($row)) {
        continue;
    }
    $id = $row['location_id'];
    if (!isset($trialLocations[$id])) {
        $trialLocations[$id] = [
            'location_id' => $id,
            'project_location' => $row['project_location'],
            'location_size' => $row['location_size'],
            'encoded_rows' => 0,
        ];
    }
    $trialLocations[$id]['encoded_rows']++;
}
$selectedLocation = $location ?: ($first['location_id'] ?? '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Farm Field Report</title>
<style>
@page { size: A4 landscape; margin: 5mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #111; font: 9px Arial, sans-serif; background: #e8efe9; }
.report-page { width: 100%; max-width: 100%; margin: 0 auto; padding: 10px 14px 18px; background: #f4f5f3; border: 1px solid #1d1d1d; overflow-x: hidden; }
.toolbar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px; background: #dfece6; border-bottom: 1px solid #9fb7a9; }
 .excel-import { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
 .excel-import input[type=file] { max-width: 190px; padding: 5px; border: 1px dashed #0a7f5d; border-radius: 4px; background: #fff; color: #111; font-size: 8px; }
 .ok, .error { margin: 8px 0 0; padding: 8px 10px; border-radius: 4px; font-weight: bold; }
 .ok { background: #dff2ea; color: #0a7f5d; border: 1px solid #0a7f5d; }
 .error { background: #fdecea; color: #b42318; border: 1px solid #b42318; }
 @media print { .excel-import { display: none; } .ok, .error { display: none; } }
.toolbar button, .toolbar a { padding: 7px 12px; border: 0; border-radius: 4px; background: #0a7f5d; color: #fff; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
.import-form { display: inline-flex; align-items: center; gap: 6px; }
.import-form input[type="file"] { max-width: 190px; padding: 5px; border: 1px solid #9fb7a9; border-radius: 4px; background: #fff; color: #111; font-weight: normal; }
.flash { margin: 10px 0 0; padding: 9px 12px; border-radius: 4px; font-weight: bold; }
.flash-ok { background: #dff2ea; border: 1px solid #0a7f5d; color: #0a5c42; }
.flash-error { background: #fdecea; border: 1px solid #b42318; color: #8f1d15; }
.size-control { display: inline-flex; align-items: center; gap: 7px; margin-left: auto; font-weight: bold; }
.size-control input { width: 140px; }
h1 { margin: 12px 0 8px; font-size: 15px; text-transform: uppercase; letter-spacing: 0.04em; }
.meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; padding: 6px 8px; border: 1px solid #222; background: #fff; }
.summary-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 8px; margin: 10px 0; }
.summary-card { background: #fff; border: 1px solid #c8d4cc; padding: 8px 10px; border-radius: 4px; }
.summary-label { display: block; font-size: 8px; font-weight: 700; color: #3a5d50; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
.summary-value { display: block; font-size: 13px; font-weight: 700; }
.table-scroll { width: 100%; max-width: 100%; overflow-x: auto; border: 1px solid #444; background: #fff; }
.report { width: 100% !important; min-width: 0; max-width: 100%; border-collapse: collapse; table-layout: fixed; background: #fff; }
.report col.variety-col { width: 8%; }
.report col.rep-col { width: 3%; }
.report col.measure-col { width: 3.1%; }
.report col.remarks-col { width: 5.2%; }
.report col.observation-col { width: 12.4%; }
.report th, .report td { min-width: 0; height: 20px; padding: 2px 3px; border: 1px solid #666; text-align: center; vertical-align: middle; overflow-wrap: break-word; word-break: break-word; white-space: normal; font-size: 8px; line-height: 1.2; }
.report th { background: #f3f5f3; font-weight: 700; }
.report td { background: #fff; }
.report .left { text-align: left; }
.report .stage { color: #0b7b5b; font-weight: 700; font-size: 11px; }
.report .data { height: 26px; }
.report .ave td { background: #eef7f0; font-weight: bold; }
.report .variety { font-weight: 700; font-size: 10px; line-height: 1.15; }
.report .rep-cell { font-weight: 700; }
.small { font-size: 8px; }
.harvest-cell { background: #fffdf5; }
.trial-location-panel { margin: 8px 0 12px; padding: 14px 16px; border: 1px solid #d7e0ea; border-radius: 8px; background: #fff; }
.trial-location-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.trial-location-heading h2 { margin: 0; font-size: 12px; }
.trial-location-heading a, .trial-location-table .report-action { padding: 6px 9px; border-radius: 4px; background: #0a7f5d; color: #fff; font-weight: 700; text-decoration: none; white-space: nowrap; }
.trial-location-scroll { max-height: 150px; overflow: auto; }
.trial-location-table { width: 100%; border-collapse: collapse; }
.trial-location-table th, .trial-location-table td { padding: 7px 9px; border: 1px solid #d7e0ea; text-align: left; }
.trial-location-table th { background: #dff2ea; color: #087f59; font-size: 8px; }
.trial-location-table td { background: #fff; font-size: 9px; }
.trial-location-table tr.selected td { background: #f2faf6; }
.trial-location-table .location-id { color: #5f2da8; }
.trial-location-actions { display: flex; flex-wrap: nowrap; align-items: center; gap: 4px; white-space: nowrap; }
.trial-location-actions > form { margin: 0; }
.trial-location-table .location-action, .trial-location-table button.location-action { display: inline-block; padding: 6px 9px; border: 0; border-radius: 4px; background: #607080; color: #fff; font: inherit; font-size: 8px; font-weight: 700; text-decoration: none; white-space: nowrap; cursor: pointer; }
.trial-location-table .report-action { background: #0a7f5d; }
.trial-location-table .delete-action { background: #b42318; }
.location-edit-dialog { width: min(440px, calc(100% - 32px)); padding: 0; border: 1px solid #cbd5df; border-radius: 8px; box-shadow: 0 18px 55px #10243b44; }
.location-edit-dialog::backdrop { background: #10243b88; }
.location-edit-form { display: grid; gap: 14px; padding: 20px; }
.location-edit-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.location-edit-heading h2 { margin: 0; font-size: 15px; }
.location-edit-close { border: 0; background: transparent; color: #536278; font-size: 20px; cursor: pointer; }
.location-edit-form label { display: grid; gap: 4px; font-size: 9px; font-weight: 700; }
.location-edit-form input { width: 100%; padding: 9px; border: 1px solid #d7e0ea; border-radius: 4px; font: inherit; }
.location-edit-actions { display: flex; justify-content: flex-end; gap: 8px; }
.location-edit-actions button { padding: 8px 12px; border: 0; border-radius: 4px; font: inherit; font-weight: 700; cursor: pointer; }
.location-edit-cancel { background: #e8edf1; color: #334155; }
.location-edit-save { background: #0a7f5d; color: #fff; }
@media print {
    body { font-size: 7px; }
    .report-page { padding: 0; }
    .toolbar { display: none; }
    .trial-location-panel { display: none; }
    .location-edit-dialog { display: none; }
    h1 { margin: 0 0 3px; font-size: 11px; }
    .meta { gap: 3px 8px; padding: 3px; font-size: 6.5px; }
    .table-scroll { overflow: visible; }
    .report { width: 100% !important; min-width: 0; font-size: 6.5px; }
    .report th, .report td { height: auto; padding: 1.5px; border-color: #666; }
    .report .data { height: 5mm; }
    .report .variety { font-size: 7px; }
    .small { font-size: 5.5px; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>
</head>
<body>
<div class="report-page">
    <?php if (!empty($importFlash)): ?>
    <p class="<?= ($importFlash['type'] ?? 'ok') === 'error' ? 'error' : 'ok' ?>"><?= e($importFlash['message'] ?? '') ?></p>
    <?php endif; ?>
    <div class="toolbar">
        <button type="button" onclick="window.print()">PRINT REPORT</button>
        <a href="index.php">BACK TO ENCODING</a>
        <?php if ($canExcel): ?>
        <a href="export.php<?= $selectedLocation !== '' ? '?location=' . urlencode($selectedLocation) : '' ?>">EXPORT EXCEL</a>
        <a href="export.php?template=1">IMPORT TEMPLATE</a>
        <form class="import-form" action="import.php" method="post" enctype="multipart/form-data">
            <input type="file" name="excel_file" accept=".xls,.csv,.xml,.html" required>
            <button type="submit">IMPORT EXCEL</button>
        </form>
        <?php endif; ?>
        <label class="size-control" for="tableSize">Text size
            <input id="tableSize" type="range" min="7" max="12" step="0.5" value="8">
            <output id="tableSizeValue" for="tableSize">8px</output>
        </label>
    </div>
    <section class="trial-location-panel" aria-label="Trial Locations">
        <div class="trial-location-heading"><h2>Trial Locations</h2><a href="locations.php">MANAGE LOCATIONS</a></div>
        <div class="trial-location-scroll"><table class="trial-location-table"><thead><tr><th>LOCATION ID</th><th>LOCATION OF PROJECT</th><th>SIZE OF LOCATION</th><th>ENCODED ROWS</th><th>ACTIONS</th></tr></thead><tbody>
        <?php foreach ($trialLocations as $item): ?>
            <tr class="<?= $item['location_id'] === $selectedLocation ? 'selected' : '' ?>"><td><a class="location-id" href="report.php?location=<?=urlencode($item['location_id'])?>"><?=e($item['location_id'])?></a></td><td><?=e($item['project_location'])?></td><td><?=e($item['location_size'])?></td><td><?=e($item['encoded_rows'])?></td><td><div class="trial-location-actions">
                <a class="location-action report-action" href="report.php?location=<?=urlencode($item['location_id'])?>">REPORT</a>
                <?php if ($canManageLocations): ?>
                    <button type="button" class="location-action edit-location-button" data-location-id="<?=e($item['location_id'])?>" data-project="<?=e($item['project_location'])?>" data-size="<?=e($item['location_size'])?>">EDIT</button>
                    <form method="post" action="locations.php"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="location-action" name="remove_location">REMOVE</button></form>
                    <form method="post" action="locations.php" onsubmit="return confirm('Delete this location and all its encoded rows?')"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="location-action delete-action" name="delete_location">DELETE</button></form>
                <?php endif ?>
            </div></td></tr>
        <?php endforeach ?>
        <?php if (!$trialLocations): ?><tr><td colspan="5">No saved trial locations.</td></tr><?php endif ?>
        </tbody></table></div>
    </section>
    <?php if ($canManageLocations): ?>
    <dialog class="location-edit-dialog" id="locationEditDialog" aria-labelledby="locationEditTitle">
        <form method="post" action="locations.php" class="location-edit-form">
            <div class="location-edit-heading"><h2 id="locationEditTitle">Edit Trial Location</h2><button type="button" class="location-edit-close" id="closeLocationEdit" aria-label="Close">&times;</button></div>
            <input type="hidden" name="location_id">
            <label>Location of Project<input name="project_location" required></label>
            <label>Size of Location<input name="location_size"></label>
            <div class="location-edit-actions"><button type="button" class="location-edit-cancel" id="cancelLocationEdit">CANCEL</button><button class="location-edit-save" name="edit_location">SAVE CHANGES</button></div>
        </form>
    </dialog>
    <?php endif ?>
    <h1>FARM FIELD</h1>
    <div class="meta">
        <span>LOCATION: <?=e($first['location_id']??'')?></span>
        <span>PROJECT: <?=e($first['project_location']??'')?></span>
        <span>DR DATE: <?=e($first['dr_dp']??'')?></span>
        <span>DATE PLANTED: <?=e($first['date_planted']??'')?></span>
        <span>CONTROL: <?=e($first['control_name']??'')?></span>
    </div>
    <div class="summary-strip">
        <div class="summary-card">
            <span class="summary-label">Varieties</span>
            <span class="summary-value"><?=e($varietyCount)?></span>
        </div>
        <div class="summary-card">
            <span class="summary-label">Records</span>
            <span class="summary-value"><?=e($recordCount)?></span>
        </div>
        <div class="summary-card">
            <span class="summary-label">Stage 01</span>
            <span class="summary-value"><?=e(stage_summary($rows, '01 GRADING'))?></span>
        </div>
        <div class="summary-card">
            <span class="summary-label">Stage 02</span>
            <span class="summary-value"><?=e(stage_summary($rows, '02 GRADING'))?></span>
        </div>
        <div class="summary-card">
            <span class="summary-label">Stage 03</span>
            <span class="summary-value"><?=e(stage_summary($rows, '03 GRADING'))?></span>
        </div>
        <div class="summary-card">
            <span class="summary-label">Stage 04</span>
            <span class="summary-value"><?=e(stage_summary($rows, '04 GRADING'))?></span>
        </div>
    </div>
    <div class="table-scroll">
        <table class="report" aria-label="Farm field grading report">
            <colgroup>
                <col class="variety-col"><col class="rep-col">
                <?php foreach ($stages as $s): foreach (stage_columns($s) as $column): $isRemarks = ($column['json'] === 'remarks'); $isHarvest = ($s === 'HARVEST'); ?>
                    <col class="<?= $isHarvest ? 'observation-col' : ($isRemarks ? 'remarks-col' : 'measure-col') ?>">
                <?php endforeach; endforeach; ?>
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="left">Variety</th>
                    <th rowspan="2">Rep</th>
                    <?php $totalCols = 0; foreach ($stages as $s): $cols = stage_columns($s); $totalCols += count($cols); ?>
                        <th colspan="<?= count($cols) ?>" class="stage" data-stage="<?= e($s) ?>"><?= e($s) ?><br><span class="small"><?= ($s === 'HARVEST') ? 'OBSERVATIONS' : 'FIELD DATA' ?></span></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($stages as $s): foreach (stage_columns($s) as $column): ?>
                        <th><?=e($column['label'])?></th>
                    <?php endforeach; endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): for ($i = 1; $i <= 4; $i++): ?>
                    <tr class="data">
                        <?php if ($i === 1): ?><td rowspan="5" class="variety"><?=e($g['code'])?><br><?=e($g['name'])?></td><?php endif; ?>
                        <td class="rep-cell"><?=$i?></td>
                        <?php foreach ($stages as $s): foreach (stage_values($g['rows'][$s][$i] ?? null, $s) as $value): ?>
                            <td><?= $value ?></td>
                        <?php endforeach; endforeach; ?>
                    </tr>
                <?php endfor; ?>
                    <tr class="ave">
                        <td class="rep-cell">Ave.</td>
                        <?php foreach ($stages as $s): foreach (stage_columns($s) as $columnIndex => $column): ?><td><?=e(stage_average($g['rows'][$s] ?? [], $s, $columnIndex))?></td><?php endforeach; endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
const tableSize = document.getElementById('tableSize');
const tableSizeValue = document.getElementById('tableSizeValue');
const reportTable = document.querySelector('.report');
const setTableSize = (value) => {
    const size = Number(value) || 8;
    reportTable.style.fontSize = size + 'px';
    reportTable.querySelectorAll('th, td').forEach(function (cell) { cell.style.fontSize = size + 'px'; });
    tableSizeValue.value = size + 'px';
    tableSizeValue.textContent = size + 'px';
};
tableSize.addEventListener('input', function () {
    setTableSize(this.value);
});
setTableSize(tableSize.value);
const locationEditDialog = document.getElementById('locationEditDialog');
if (locationEditDialog) {
    const editForm = locationEditDialog.querySelector('form');
    document.querySelectorAll('.edit-location-button').forEach(function (button) {
        button.addEventListener('click', function () {
            editForm.elements.namedItem('location_id').value = button.dataset.locationId;
            editForm.elements.namedItem('project_location').value = button.dataset.project;
            editForm.elements.namedItem('location_size').value = button.dataset.size;
            locationEditDialog.showModal();
        });
    });
    document.getElementById('closeLocationEdit').addEventListener('click', function () { locationEditDialog.close(); });
    document.getElementById('cancelLocationEdit').addEventListener('click', function () { locationEditDialog.close(); });
}
</script>
</body>
</html>
