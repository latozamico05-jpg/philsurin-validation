<?php
require 'config.php';
require_login();
$accessRole = strtolower((string) ($_SESSION['user']['role'] ?? 'viewer'));
if ($accessRole !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}
require 'sidebar.php';

if (empty($_SESSION['varieties_csrf'])) {
    $_SESSION['varieties_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$message = '';
$editVarietyId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['varieties_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('The form expired. Refresh and try again.');
        }
        if (isset($_POST['add_variety'])) {
            $code = trim((string) ($_POST['vcode'] ?? ''));
            $name = trim((string) ($_POST['vname'] ?? ''));
            if ($code === '') {
                throw new InvalidArgumentException('Variety code is required.');
            }
            if (strlen($code) > 20) {
                throw new InvalidArgumentException('Variety code must be 20 characters or fewer.');
            }
            if ($name === '') {
                $name = $code;
            }
            $ins = $db->prepare('INSERT INTO varieties(code,name,active) VALUES(?,?,1)');
            $ins->execute([$code, $name]);
            $message = 'Variety added: ' . $code . ($name !== $code ? ' (' . $name . ')' : '') . '. It is now selectable in Encoding, Report and Audit.';
        } elseif (isset($_POST['edit_variety'])) {
            $vid = (int) ($_POST['variety_id'] ?? 0);
            $code = trim((string) ($_POST['vcode'] ?? ''));
            $name = trim((string) ($_POST['vname'] ?? ''));
            if ($code === '' || strlen($code) > 20) {
                throw new InvalidArgumentException('Variety code is required and must be 20 characters or fewer.');
            }
            if ($name === '') {
                $name = $code;
            }
            $updV = $db->prepare('UPDATE varieties SET code=?, name=?, active=? WHERE id=?');
            $updV->execute([$code, $name, isset($_POST['vactive']) ? 1 : 0, $vid]);
            $message = 'Variety updated: ' . $code . '.';
            $editVarietyId = 0;
        } elseif (isset($_POST['delete_variety'])) {
            $vid = (int) ($_POST['variety_id'] ?? 0);
            $target = $db->prepare('SELECT id, code, name FROM varieties WHERE id=?');
            $target->execute([$vid]);
            $targetRow = $target->fetch();
            if (!$targetRow) {
                throw new InvalidArgumentException('That variety no longer exists.');
            }
            if ((int) ($_POST['confirm_id'] ?? 0) !== $vid || trim((string) ($_POST['confirm_code'] ?? '')) !== $targetRow['code']) {
                throw new InvalidArgumentException('Remove cancelled: the confirmation did not match variety ID ' . $vid . '.');
            }
            $used = (int) $db->query('SELECT COUNT(*) FROM grading_records WHERE variety_id=' . $vid)->fetchColumn();
            if ($used > 0) {
                $db->query('UPDATE varieties SET active=0 WHERE id=' . $vid);
                $message = 'Variety ' . $targetRow['code'] . ' is used by ' . $used . ' encoded record(s), so it was removed from the lists (data kept).';
            } else {
                $db->query('DELETE FROM varieties WHERE id=' . $vid);
                $message = 'Variety ' . $targetRow['code'] . ' deleted.';
            }
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$editVarietyId = (int) ($_GET['evariety'] ?? 0);
$varieties = $db->query('SELECT id, code, name, active FROM varieties WHERE active=1 ORDER BY code')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Variety List</title>
<style>
/* Uniform bold green buttons */
.user-form button,.user-table button,.uniform-btn{border:0!important;border-radius:10px!important;background:linear-gradient(180deg,#0aa06f,#07865f)!important;color:#fff!important;padding:11px 18px!important;font:800 11px/1.2 inherit!important;letter-spacing:.5px!important;text-transform:uppercase;cursor:pointer;box-shadow:0 6px 14px -6px rgba(7,134,95,.55);transition:transform .15s,filter .15s}
.user-form button:hover,.user-table button:hover,.uniform-btn:hover{filter:brightness(1.08);transform:translateY(-1px)}
.user-table button.secondary,.uniform-btn.secondary{background:#eef6f2!important;color:#056e4e!important;box-shadow:none!important;border:1px solid #bfe3d3!important}
.btnrow{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.count{color:#94a3b8;font-weight:700;font-size:12px}.table-wrap{overflow-x:auto}.user-table{width:100%;min-width:640px;border-collapse:collapse}.user-table th,.user-table td{padding:12px 10px;border-bottom:1px solid #e5eaf0;text-align:left;vertical-align:middle}.user-table th{color:#64748b;font-size:10px;letter-spacing:.4px}.user-table td{font-size:12px}.user-table tr:last-child td{border-bottom:0}.pill{display:inline-block;padding:4px 11px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.4px}.pill-on{background:#e6f7ef;color:#087f59}.pill-off{background:#fdeceb;color:#a3231d}.role-sub{display:block;color:#94a3b8;font-size:10px;margin-top:3px;max-width:420px}.note{color:#64748b;font-size:11px}
/* Edit row */
.edit-row td{background:#f7fbf9}
.edit-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end}
.edit-grid label{margin:0}
/* Variety list */
.variety-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:10px 0 14px}.variety-head h2{margin:0;font-size:18px}.variety-head .count{margin-left:8px}.variety-head .note{color:#64748b;font-size:11px;margin-top:4px;display:block}
.variety-add{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px}
.variety-add label{display:flex;flex-direction:column;gap:4px;font-size:12px}
.variety-add input{padding:8px 10px;border:1px solid #d1dbe3;border-radius:8px;font-size:12px}
.variety-add button{padding:10px 20px}
</style>
</head>
<body>
<?php if ($error): ?><div class="error" style="background:#fdeceb;color:#a3231d;padding:10px 14px;border-radius:8px;margin:0 16px 14px;font-size:13px"><?=e($error)?></div><?php endif ?>
<?php if ($message): ?><div class="message" style="background:#e6f7ef;color:#087f59;padding:10px 14px;border-radius:8px;margin:0 16px 14px;font-size:13px"><?=e($message)?></div><?php endif ?>
<main style="margin-left:260px;padding:24px;max-width:1200px">
<section class="card" id="variety-list"><div class="variety-head"><h2>Variety List <span class="count">(<?=count($varieties)?>)</span></h2><span class="note">Live from the system data — this exact list is used in Encoding, Report and Audit.</span></div><form method="post" class="variety-add"><input type="hidden" name="csrf" value="<?=e($_SESSION['varieties_csrf'])?>"><label style="flex:1;min-width:170px">Variety code<input name="vcode" required maxlength="20" placeholder="e.g. PSR 17-191"></label><label style="flex:1;min-width:170px">Display name <span class="note">(optional)</span><input name="vname" maxlength="150" placeholder="Defaults to the code"></label><button name="add_variety">Add Variety</button></form><div class="table-wrap"><table class="user-table variety-table"><thead><tr><th style="width:70px">ID</th><th>VARIETY CODE</th><th>DISPLAY NAME</th><th>STATUS</th><th style="width:230px">ACTIONS</th></tr></thead><tbody><?php foreach ($varieties as $v): $vid = (int) $v['id']; $isEditingV = $editVarietyId === $vid; ?><tr<?= $isEditingV ? ' class="edit-row"' : '' ?>><td><?=e($v['id'])?></td><?php if ($isEditingV): ?><td><input name="vcode" value="<?=e($v['code'])?>" required maxlength="20" form="vform<?= $vid ?>" aria-label="Variety code"></td><td><input name="vname" value="<?=e($v['name'])?>" maxlength="150" form="vform<?= $vid ?>" aria-label="Display name"></td><td><span class="pill pill-on">ACTIVE</span></td><td><form id="vform<?= $vid ?>" method="post" class="btnrow"><input type="hidden" name="csrf" value="<?=e($_SESSION['varieties_csrf'])?>"><input type="hidden" name="variety_id" value="<?= $vid ?>"><input type="hidden" name="vactive" value="1"><button name="edit_variety">Save</button><a class="uniform-btn secondary" href="varieties.php" style="text-decoration:none">Cancel</a></form></td><?php else: ?><td><strong><?=e($v['code'])?></strong></td><td><?=e($v['name'])?></td><td><span class="pill pill-on">ACTIVE</span></td><td><div class="btnrow"><a class="uniform-btn" href="varieties.php?evariety=<?= $vid ?>" style="text-decoration:none">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Remove variety ID <?= $vid ?> (<?=e($v['code'])?>) from the list?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['varieties_csrf'])?>"><input type="hidden" name="variety_id" value="<?= $vid ?>"><input type="hidden" name="confirm_id" value="<?= $vid ?>"><input type="hidden" name="confirm_code" value="<?=e($v['code'])?>"><button name="delete_variety" class="secondary">Remove</button></form></div></td><?php endif ?></tr><?php endforeach ?><?php if (!$varieties): ?><tr><td colspan="5">No varieties yet. Add one above.</td></tr><?php endif ?></tbody></table></div></section>
</main></body></html>