<?php
require 'config.php';
require_login();
$accessRole = strtolower((string) ($_SESSION['user']['role'] ?? 'viewer'));
if ($accessRole !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}
require 'sidebar.php';

// Flexible access catalogue: plain-language description of what each role can open.
$ROLE_ACCESS = [
    'admin' => 'Full access — Manage Users, Encoding, Audit Trail, Report (desktop + mobile)',
    'validator' => 'All-stage encoding + Audit Trail + Report (desktop + mobile)',
    'validator_germination' => '01 GRADING encoding + Audit Trail (desktop + mobile)',
    'validator_field' => '02-04 GRADING encoding + Audit Trail (desktop + mobile)',
    'validator_harvest' => 'HARVEST encoding + Audit Trail (desktop + mobile)',
    'viewer' => 'Read-only — Dashboard + Report, no encoding',
];
$ROLE_LABELS = [
    'admin' => 'Administrator',
    'validator' => 'Validator',
    'validator_germination' => 'Validator (Germination)',
    'validator_field' => 'Validator (Field)',
    'validator_harvest' => 'Validator (Harvest)',
    'viewer' => 'Viewer',
];
// Flexible account creation: administrators may grant every role (including
// Administrator); validators may grant the validator variants and viewer only.
$ALLOWED_CREATE_ROLES = $accessRole === 'admin'
    ? ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest', 'viewer']
    : ['validator', 'validator_germination', 'validator_field', 'validator_harvest', 'viewer'];

$error = '';
$message = '';
if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['users_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('The form expired. Refresh and try again.');
        }
        if (isset($_POST['create_validator'])) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = strtolower(trim((string) ($_POST['role'] ?? 'validator')));
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                throw new InvalidArgumentException('Use a 3-50 character username with letters, numbers, dots, dashes, or underscores.');
            }
            if (strlen($password) < 10) {
                throw new InvalidArgumentException('Account passwords must be at least 10 characters.');
            }
            if (!in_array($role, $ALLOWED_CREATE_ROLES, true)) {
                throw new InvalidArgumentException('Your account cannot grant that access role.');
            }
            $stmt = $db->prepare('INSERT INTO users(username,password_hash,role,active) VALUES(?,?,?,1)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            $label = $ROLE_LABELS[$role] ?? strtoupper($role);
            $message = 'Account created: ' . $username . ' (' . $label . '). This login works on desktop and on the mobile app (mobile.php).';
        } elseif (isset($_POST['edit_user'])) {
            // Edit an existing account. Administrators may edit every account
            // (including other administrators); validators may edit non-admin accounts.
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newUsername = trim((string) ($_POST['username'] ?? ''));
            $newPassword = (string) ($_POST['password'] ?? '');
            $newRole = strtolower(trim((string) ($_POST['role'] ?? '')));
            $newActive = isset($_POST['active']) ? 1 : 0;

            $targetStmt = $db->prepare('SELECT id, username, role, active FROM users WHERE id=?');
            $targetStmt->execute([$userId]);
            $target = $targetStmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('That account no longer exists.');
            }
            $isSelf = $userId === (int) $_SESSION['user']['id'];
            $targetRole = strtolower((string) $target['role']);
            if ($targetRole === 'admin' && $accessRole !== 'admin') {
                throw new InvalidArgumentException('Only an administrator can edit an administrator account.');
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $newUsername)) {
                throw new InvalidArgumentException('Use a 3-50 character username with letters, numbers, dots, dashes, or underscores.');
            }
            if (!in_array($newRole, $ALLOWED_CREATE_ROLES, true)) {
                throw new InvalidArgumentException('Your account cannot grant that access role.');
            }
            if ($newPassword !== '' && strlen($newPassword) < 10) {
                throw new InvalidArgumentException('Passwords must be at least 10 characters.');
            }
            // Never let the last active administrator lock everyone out.
            $losingAdmin = ($targetRole === 'admin' && ($newRole !== 'admin' || !$newActive));
            if ($losingAdmin) {
                $adminCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(role)='admin' AND active=1")->fetchColumn();
                if ($adminCount <= 1) {
                    throw new InvalidArgumentException('At least one active administrator must remain.');
                }
            }
            $dup = $db->prepare('SELECT id FROM users WHERE username=? AND id<>?');
            $dup->execute([$newUsername, $userId]);
            if ($dup->fetch()) {
                throw new InvalidArgumentException('That username is already taken.');
            }
            if ($newPassword !== '') {
                $upd = $db->prepare('UPDATE users SET username=?, role=?, active=?, password_hash=? WHERE id=?');
                $upd->execute([$newUsername, $newRole, $newActive, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $upd = $db->prepare('UPDATE users SET username=?, role=?, active=? WHERE id=?');
                $upd->execute([$newUsername, $newRole, $newActive, $userId]);
            }
            $who = $isSelf ? 'Your account' : 'Account ' . $target['username'];
            $message = $who . ' updated — username: ' . $newUsername . ', role: ' . ($ROLE_LABELS[$newRole] ?? $newRole) . ', status: ' . ($newActive ? 'ACTIVE' : 'INACTIVE') . ($newPassword !== '' ? ', password changed.' : ', password unchanged.');
        } elseif (isset($_POST['toggle_user'])) {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) $_SESSION['user']['id']) {
                throw new InvalidArgumentException('You cannot deactivate your own account.');
            }
            $targetStmt = $db->prepare('SELECT LOWER(role) FROM users WHERE id=?');
            $targetStmt->execute([$userId]);
            $targetRole = (string) ($targetStmt->fetchColumn() ?: '');
            if ($targetRole === 'admin' && $accessRole !== 'admin') {
                throw new InvalidArgumentException('Only an administrator can change admin accounts.');
            }
            // Never deactivate the last remaining active administrator.
            if ($targetRole === 'admin') {
                $active = (int) $db->query('SELECT active FROM users WHERE id=?')->fetchColumn();
                $adminCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(role)='admin' AND active=1")->fetchColumn();
                if ($active === 1 && $adminCount <= 1) {
                    throw new InvalidArgumentException('At least one active administrator must remain.');
                }
            }
            $stmt = $db->prepare('UPDATE users SET active=IF(active=1,0,1) WHERE id=?');
            $stmt->execute([$userId]);
            $message = 'Account status updated.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$users = $db->query('SELECT id,username,role,active FROM users ORDER BY username')->fetchAll();
// Which account is currently being edited (?id=… shows the inline edit row).
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId) {
    foreach ($users as $candidate) {
        if ((int) $candidate['id'] === $editId) {
            $editId = (int) $candidate['id'];
            break;
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users</title>
<script src="password-eye.js"></script>
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
.variety-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.variety-head h2{margin:0}
.variety-table td{vertical-align:middle}
.variety-table input{margin:0;padding:8px 10px;font-size:12px;min-width:150px}
.variety-table tr.edit-row td{background:#f4fbf7}
.variety-table strong{color:#056e4e;font-weight:800}
.variety-table{min-width:780px}
.variety-table th,.variety-table td{white-space:nowrap}
.variety-table td:nth-child(3){white-space:normal;min-width:180px}
.variety-table td:last-child{white-space:nowrap}
.variety-table .btnrow{flex-wrap:nowrap;gap:8px}
.variety-table .btnrow form{display:inline-flex;margin:0}
.variety-table .btnrow button{padding:9px 15px!important;font-size:10px!important}
.variety-add{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:16px;padding:14px;background:#f4fbf7;border:1px solid #d9ebe2;border-radius:12px}
.variety-add label{margin:0}
.variety-add button{padding:12px 20px!important}
@media(max-width:640px){.grid.two{grid-template-columns:1fr}.edit-grid{grid-template-columns:1fr}.variety-add{flex-direction:column;align-items:stretch}}
</style>
</head>
<body><main>
<header><h1>PHILSURIN</h1><span><?=e($_SESSION['user']['username'])?> | <a href="dashboard.php">DASHBOARD</a></span></header>
<?php if ($message): ?><p class="ok"><?=e($message)?></p><?php endif ?>
<?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section class="card"><h2>Create Account</h2><form method="post" class="user-form"><input type="hidden" name="csrf" value="<?=e($_SESSION['users_csrf'])?>"><div class="grid two"><label>Username<input name="username" required autocomplete="off" placeholder="e.g. juan.delacruz"></label><label>Temporary password<input name="password" type="password" minlength="10" required autocomplete="new-password" placeholder="At least 10 characters"></label></div><label>Access role<select name="role" required><?php foreach ($ALLOWED_CREATE_ROLES as $roleKey): ?><option value="<?=e($roleKey)?>"><?=e(($ROLE_LABELS[$roleKey] ?? strtoupper($roleKey)) . ' — ' . ($ROLE_ACCESS[$roleKey] ?? ''))?></option><?php endforeach ?></select></label><p class="hint">Pick the access this account should get. Validator roles can open Encoding + Audit Trail on desktop and on the mobile app; viewers stay read-only<?php if ($accessRole === 'admin'): ?> — as an administrator you can also create Administrator accounts<?php endif ?>.</p><button name="create_validator">CREATE ACCOUNT</button></form></section>
<section class="card"><h2>Accounts <span class="count">(<?=count($users)?>)</span></h2><div class="table-wrap"><table class="user-table"><thead><tr><th>USERNAME</th><th>ACCESS ROLE</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody><?php foreach ($users as $user):
    $roleKey = strtolower((string) $user['role']);
    $uid = (int) $user['id'];
    $isSelf = $uid === (int) $_SESSION['user']['id'];
    // Admins can manage every account; validators only non-admin accounts.
    $canManage = $accessRole === 'admin' ? true : ($roleKey !== 'admin');
    $canToggle = !$isSelf && $canManage;
    $isEditing = $editId === $uid; ?>
    <tr<?= $isEditing ? ' class="edit-row"' : '' ?>>
      <td><?=e($user['username'])?><?php if ($isSelf): ?><span class="role-sub">This is the account you are signed in with.</span><?php endif ?></td>
      <td><?=e($ROLE_LABELS[$roleKey] ?? strtoupper($user['role']))?><span class="role-sub"><?=e($ROLE_ACCESS[$roleKey] ?? '')?></span></td>
      <td><span class="pill <?=$user['active'] ? 'pill-on' : 'pill-off'?>"><?=$user['active'] ? 'ACTIVE' : 'INACTIVE'?></span></td>
      <td>
        <?php if (!$canManage): ?>
          <span class="note">Administrator account — admin only</span>
        <?php elseif ($isEditing): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['users_csrf'])?>">
            <input type="hidden" name="user_id" value="<?=e($user['id'])?>">
            <div class="edit-grid">
              <label>Username<input name="username" value="<?=e($user['username'])?>" required autocomplete="off" pattern="[A-Za-z0-9_.\-]{3,50}"></label>
              <label>New password<input name="password" type="password" minlength="10" autocomplete="new-password" placeholder="Leave blank to keep"></label>
              <label>Access role<select name="role"><?php foreach ($ALLOWED_CREATE_ROLES as $roleKey2): ?><option value="<?=e($roleKey2)?>"<?=$roleKey2 === $roleKey ? ' selected' : ''?>><?=e($ROLE_LABELS[$roleKey2] ?? strtoupper($roleKey2))?></option><?php endforeach ?></select></label>
              <label>Status<select name="active"><option value="1"<?=$user['active'] ? ' selected' : ''?>>ACTIVE</option><option value="0"<?=$user['active'] ? '' : ' selected'?>>INACTIVE</option></select></label>
            </div>
            <div class="btnrow" style="margin-top:10px">
              <button name="edit_user">Save Changes</button>
              <a class="uniform-btn secondary" href="users.php" style="text-decoration:none">Cancel</a>
            </div>
          </form>
        <?php else: ?>
          <div class="btnrow">
            <a class="uniform-btn" href="users.php?edit=<?=e($user['id'])?>" style="text-decoration:none">Edit<?= $isSelf ? ' My Account' : '' ?></a>
            <?php if ($canToggle): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?=e($_SESSION['users_csrf'])?>">
                <input type="hidden" name="user_id" value="<?=e($user['id'])?>">
                <button name="toggle_user" class="secondary"><?=$user['active'] ? 'Deactivate' : 'Activate'?></button>
              </form>
            <?php endif ?>
          </div>
        <?php endif ?>
      </td>
    </tr>
<?php endforeach; ?>
  <?php if (!$users): ?><tr><td colspan="4">No accounts yet.</td></tr><?php endif ?></tbody></table></div></section>
</main></body></html>
