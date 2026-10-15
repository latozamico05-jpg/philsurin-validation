<?php
require 'config.php';
require_login();
require 'sidebar.php';
require_once __DIR__ . '/location_filters.php';

$role = strtolower((string) ($_SESSION['user']['role'] ?? 'viewer'));
$canManage = in_array($role, ['admin', 'validator', 'validator_field'], true);
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit('You do not have permission to edit trial locations.');
    }
    $locationId = trim((string) ($_POST['location_id'] ?? ''));
    try {
        if ($locationId === '') {
            throw new InvalidArgumentException('A Location ID is required.');
        }
        if (isset($_POST['edit_location'])) {
            $project = trim((string) ($_POST['project_location'] ?? ''));
            if ($project === '') {
                throw new InvalidArgumentException('Location of Project cannot be blank.');
            }
            $stmt = $db->prepare('UPDATE grading_records SET project_location=?,location_size=? WHERE location_id=?');
            $stmt->execute([$project, trim((string) ($_POST['location_size'] ?? '')), $locationId]);
            $message = 'Trial location updated.';
        } elseif (isset($_POST['remove_location'])) {
            $stmt = $db->prepare('INSERT INTO hidden_trial_locations(location_id,hidden_by) VALUES(?,?) ON DUPLICATE KEY UPDATE hidden_by=VALUES(hidden_by),hidden_at=CURRENT_TIMESTAMP');
            $stmt->execute([$locationId, (int) $_SESSION['user']['id']]);
            $message = 'Location hidden from reports. Encoded data was kept.';
        } elseif (isset($_POST['restore_location'])) {
            $stmt = $db->prepare('DELETE FROM hidden_trial_locations WHERE location_id=?');
            $stmt->execute([$locationId]);
            $message = 'Location restored to reports.';
        } elseif (isset($_POST['delete_location'])) {
            $db->beginTransaction();
            $stmt = $db->prepare('DELETE FROM grading_records WHERE location_id=?');
            $stmt->execute([$locationId]);
            $stmt = $db->prepare('DELETE FROM hidden_trial_locations WHERE location_id=?');
            $stmt->execute([$locationId]);
            $db->commit();
            $message = 'Location and its encoded rows were deleted.';
        }
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$allRows = $db->query('SELECT g.*,v.code,v.name FROM grading_records g JOIN varieties v ON v.id=g.variety_id ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
$locations = [];
foreach ($allRows as $row) {
    if (!row_has_data($row)) {
        continue;
    }
    $id = $row['location_id'];
    if (!isset($locations[$id])) {
        $locations[$id] = [
            'location_id' => $id,
            'project_location' => $row['project_location'],
            'location_size' => $row['location_size'],
            'date_planted' => $row['date_planted'],
            'dr_dp' => $row['dr_dp'],
            'encoded_rows' => 0,
            'created_at' => $row['created_at'],
        ];
    }
    $locations[$id]['encoded_rows']++;
}
$hiddenIds = $db->query('SELECT location_id FROM hidden_trial_locations')->fetchAll(PDO::FETCH_COLUMN);
$hidden = array_fill_keys($hiddenIds, true);
foreach ($locations as $lid => $item) {
    $locations[$lid]['is_hidden'] = isset($hidden[$item['location_id']]);
}
$filterSpec = location_filter_input();
$filterOptions = location_filter_options($locations);
$filteredLocations = location_apply_filter($locations, $filterSpec);
$visibleLocations = array_filter($locations, function ($item) { return !$item['is_hidden']; });
$hiddenLocations = array_filter($locations, function ($item) { return (bool)$item['is_hidden']; });
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trial Locations</title>
<link rel="stylesheet" href="style.css">
<style>
.locations{width:100%;border-collapse:collapse}.locations th,.locations td{border:1px solid #d7e0ea;padding:10px;text-align:left}.locations th{background:#dff2ea;color:#087f59;font-size:10px}.locations td{font-size:12px}.smallbtn,.action-button{display:inline-block;border:0;border-radius:5px;padding:7px 9px;background:#087f59;color:#fff;text-decoration:none;font-size:10px;font-weight:bold;margin:2px;cursor:pointer}.danger{background:#b42318}.secondary{background:#607080}.inline{display:inline}.edit-panel{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:end;margin-top:8px}.empty{padding:16px;color:#748196}
.location-filter{margin:10px 0 4px;padding:12px;border:1px solid #dbe6ee;border-radius:8px;background:#fbfdff}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end}
.filter-grid label{display:flex;flex-direction:column;gap:4px;font-size:9px;font-weight:bold;letter-spacing:.4px;color:#087f59}
.filter-grid input,.filter-grid select{width:100%;box-sizing:border-box;padding:7px 8px;border:1px solid #cfd9e2;border-radius:6px;font-size:12px;background:#fff;color:#15263d}
.filter-buttons{display:flex;gap:6px;align-items:flex-end}
.filter-summary{margin:8px 0 6px;font-size:11px;color:#536278}
.sort-link{color:inherit;text-decoration:none;display:inline-block}
.sort-link:hover{text-decoration:underline}
.status-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:bold;letter-spacing:.3px}
.status-badge.active{background:#dff2ea;color:#087f59}
.status-badge.removed{background:#fde3e1;color:#b42318}
tr.row-removed td{opacity:.62}
@media(max-width:760px){.locations{min-width:760px}.table-wrap{overflow:auto}.edit-panel{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<header><h1>PHILSURIN</h1><span><?=e($_SESSION['user']['username'])?> | <a href="index.php">ENCODING</a></span></header>
<?php if ($message): ?><p class="ok"><?=e($message)?></p><?php endif ?>
<?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section class="card">
<h2>Trial Locations</h2>
<p class="hint">Each Location ID appears once. Removing hides it from reports; deleting erases its encoded records.</p>
<form class="location-filter" method="get" action="locations_management.php">
  <div class="filter-grid">
    <label>SEARCH<input type="search" name="q" value="<?=e($filterSpec['filters']['q'])?>" placeholder="Location ID, project or size"></label>
    <label>LOCATION OF PROJECT<select name="project"><option value="">All projects</option><?php foreach ($filterOptions['projects'] as $opt): ?><option value="<?=e($opt)?>"<?=$filterSpec['filters']['project']===$opt?' selected':''?>><?=e($opt)?></option><?php endforeach ?></select></label>
    <label>SIZE OF LOCATION<select name="size"><option value="">All sizes</option><?php foreach ($filterOptions['sizes'] as $opt): ?><option value="<?=e($opt)?>"<?=$filterSpec['filters']['size']===$opt?' selected':''?>><?=e($opt)?></option><?php endforeach ?></select></label>
    <label>STATUS<select name="status"><?php foreach (['active'=>'Active only','removed'=>'Removed only','all'=>'Active + removed'] as $val=>$lab): ?><option value="<?=e($val)?>"<?=$filterSpec['filters']['status']===$val?' selected':''?>><?=e($lab)?></option><?php endforeach ?></select></label>
    <label>MIN ENCODED ROWS<input type="number" min="0" step="1" name="min_rows" value="<?=e($filterSpec['filters']['min_rows'])?>" placeholder="e.g. 5"></label>
    <div class="filter-buttons"><button class="action-button" type="submit">FILTER</button><a class="action-button secondary" href="locations_management.php">CLEAR</a></div>
  </div>
  <input type="hidden" name="sort" value="<?=e($filterSpec['sortKey'])?>"><input type="hidden" name="dir" value="<?=e(strtolower($filterSpec['sortDir']))?>">
</form>
<p class="filter-summary"><?php $shown = count($filteredLocations); $total = count($locations); ?>
  Showing <strong><?= $shown ?></strong> of <strong><?= $total ?></strong> location<?= $total === 1 ? '' : 's' ?><?= $filterSpec['hasFilters'] ? ' (filtered)' : '' ?>.
</p>
<div class="table-wrap"><table class="locations"><thead><tr><th><a class="sort-link" href="<?=e(location_filter_query($filterSpec['filters'],$filterSpec['sortKey'],$filterSpec['sortDir'],['sort'=>'location_id','dir'=>($filterSpec['sortKey']==='location_id'&&$filterSpec['sortDir']==='ASC')?'DESC':'ASC']))?>">LOCATION ID<?= $filterSpec['sortKey']==='location_id' ? ($filterSpec['sortDir']==='ASC'?' &uarr;':' &darr;') : '' ?></a></th><th><a class="sort-link" href="<?=e(location_filter_query($filterSpec['filters'],$filterSpec['sortKey'],$filterSpec['sortDir'],['sort'=>'project_location','dir'=>($filterSpec['sortKey']==='project_location'&&$filterSpec['sortDir']==='ASC')?'DESC':'ASC']))?>">LOCATION OF PROJECT<?= $filterSpec['sortKey']==='project_location' ? ($filterSpec['sortDir']==='ASC'?' &uarr;':' &darr;') : '' ?></a></th><th><a class="sort-link" href="<?=e(location_filter_query($filterSpec['filters'],$filterSpec['sortKey'],$filterSpec['sortDir'],['sort'=>'location_size','dir'=>($filterSpec['sortKey']==='location_size'&&$filterSpec['sortDir']==='ASC')?'DESC':'ASC']))?>">SIZE OF LOCATION<?= $filterSpec['sortKey']==='location_size' ? ($filterSpec['sortDir']==='ASC'?' &uarr;':' &darr;') : '' ?></a></th><th><a class="sort-link" href="<?=e(location_filter_query($filterSpec['filters'],$filterSpec['sortKey'],$filterSpec['sortDir'],['sort'=>'encoded_rows','dir'=>($filterSpec['sortKey']==='encoded_rows'&&$filterSpec['sortDir']==='ASC')?'DESC':'ASC']))?>">ENCODED ROWS<?= $filterSpec['sortKey']==='encoded_rows' ? ($filterSpec['sortDir']==='ASC'?' &uarr;':' &darr;') : '' ?></a></th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>
<?php foreach ($filteredLocations as $item): ?>
<tr<?= $item['is_hidden'] ? ' class="row-removed"' : '' ?>>
<td><a href="report.php?location=<?=urlencode($item['location_id'])?>"><?=e($item['location_id'])?></a></td>
<td><?=e($item['project_location'])?></td><td><?=e($item['location_size'])?></td><td><?=e($item['encoded_rows'])?></td>
<td><span class="status-badge <?= $item['is_hidden'] ? 'removed' : 'active' ?>"><?= $item['is_hidden'] ? 'REMOVED' : 'ACTIVE' ?></span></td>
<td><div class="row-actions">
<a class="smallbtn" href="report.php?location=<?=urlencode($item['location_id'])?>">REPORT</a>
<?php if ($canManage): ?>
<details class="row-actions-edit"><summary class="smallbtn secondary">EDIT</summary><form method="post" class="edit-panel"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><label>Location of Project<input name="project_location" value="<?=e($item['project_location'])?>" required></label><label>Size of Location<input name="location_size" value="<?=e($item['location_size'])?>"></label><button class="action-button" name="edit_location">SAVE EDIT</button></form></details>
<?php if ($item['is_hidden']): ?><form method="post" class="inline"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="action-button" name="restore_location">RESTORE</button></form>
<?php else: ?><form method="post" class="inline"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="action-button secondary" name="remove_location">REMOVE</button></form>
<?php endif ?>
<form method="post" class="inline" onsubmit="return confirm('Delete this location and all its encoded rows?')"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="action-button danger" name="delete_location">DELETE</button></form>
<?php endif ?></div></td></tr>
<?php endforeach ?>
<?php if (!$filteredLocations): ?><tr><td class="empty" colspan="6">No trial locations match the current filter.</td></tr><?php endif ?>
</tbody></table></div>
<div class="location-actions"><a class="smallbtn" href="export_locations.php<?=e(location_filter_query($filterSpec['filters'],$filterSpec['sortKey'],$filterSpec['sortDir']))?>">EXPORT FILTERED TO EXCEL<?= $filterSpec['hasFilters'] ? ' (' . count($filteredLocations) . ')' : '' ?></a> <a class="smallbtn secondary" href="export.php">EXPORT FULL DATA WORKBOOK</a><?php if ($filterSpec['hasFilters']): ?> <a class="smallbtn secondary" href="locations_management.php">RESET FILTER</a><?php endif ?></div>
</section>
<?php if ($canManage && $hiddenLocations): ?><section class="card"><h2>Removed Locations</h2><div class="table-wrap"><table class="locations"><thead><tr><th>LOCATION ID</th><th>LOCATION OF PROJECT</th><th>ENCODED ROWS</th><th>ACTION</th></tr></thead><tbody><?php foreach ($hiddenLocations as $item): ?><tr><td><?=e($item['location_id'])?></td><td><?=e($item['project_location'])?></td><td><?=e($item['encoded_rows'])?></td><td><form method="post"><input type="hidden" name="location_id" value="<?=e($item['location_id'])?>"><button class="action-button" name="restore_location">RESTORE</button></form></td></tr><?php endforeach ?></tbody></table></div></section><?php endif ?>
</main>
</body>
</html>
