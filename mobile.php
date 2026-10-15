<?php
// PHILSURIN installable mobile app: validator Encoding + Audit Trail + downloads.
// Same PHP session login as the desktop site, phone-sized layout, offline shell.
require 'config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: mobile.php');
    exit;
}

$error = '';
if (isset($_POST['login'])) {
    $q = $db->prepare('SELECT * FROM users WHERE username=? AND active=1');
    $q->execute([$_POST['username'] ?? '']);
    $u = $q->fetch();
    $passwordValid = $u && (password_verify((string) ($_POST['password'] ?? ''), $u['password_hash']) || hash_equals((string) $u['password_hash'], hash('sha256', (string) ($_POST['password'] ?? ''))));
    if ($passwordValid) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'username' => $u['username'], 'role' => $u['role'] ?? 'viewer'];
        audit_log((int)$u['id'],'LOGIN','USER',(string)$u['id']);
    } else {
        audit_log($u ? (int)$u['id'] : null,'LOGIN_FAILED','USER',(string)($_POST['username'] ?? ''));
        $error = 'Invalid username or password.';
    }
}

$loggedIn = !empty($_SESSION['user']);
$role = strtolower((string) ($_SESSION['user']['role'] ?? 'viewer'));
$canEncode = $loggedIn && function_exists('philsurin_can_encode') ? philsurin_can_encode() : in_array($role, ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest'], true);
$canAudit = $loggedIn && function_exists('philsurin_can_audit') ? philsurin_can_audit() : in_array($role, ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest'], true);
$canExport = $loggedIn && function_exists('philsurin_can_export') ? philsurin_can_export() : in_array($role, ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest'], true);
$allowedStages = $loggedIn && function_exists('philsurin_allowed_stages') ? philsurin_allowed_stages() : ['01 GRADING', '02 GRADING', '03 GRADING', '04 GRADING', 'HARVEST'];
$tab = $_GET['tab'] ?? 'encode';
if (!$canEncode && $tab === 'encode') {
    $tab = $canAudit ? 'audit' : 'downloads';
}
if (!$canAudit && $tab === 'audit') {
    $tab = $canEncode ? 'encode' : 'downloads';
}
if (!$canExport && $tab === 'downloads') {
    $tab = $canEncode ? 'encode' : ($canAudit ? 'audit' : 'encode');
}

// Mobile encode save: same validation and save_validation_rows() path as desktop
// index.php, answered as JSON for the fetch() in the encode script below.
if (isset($_POST['save_validation'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$loggedIn || !$canEncode) {
        echo json_encode(['ok' => false, 'error' => 'Your account cannot save Validation data.']);
        exit;
    }
    if (!philsurin_can_encode_stage((string) ($_POST['stage'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'Your validator account is not allowed to encode this grading stage.']);
        exit;
    }
    try {
        $result = save_validation_rows($db, (int) $_SESSION['user']['id'], $_POST);
        audit_log((int)$_SESSION['user']['id'],'SAVE_VALIDATION','VALIDATION',(string)($result['location_id']??''),null,['stage'=>(string)($_POST['stage']??''),'saved'=>(int)($result['saved']??0)]);
        echo json_encode([
            'ok' => true,
            'location_id' => (string) ($result['location_id'] ?? ''),
            'saved' => (int) ($result['saved'] ?? 0),
        ]);
    } catch (Throwable $x) {
        echo json_encode(['ok' => false, 'error' => $x->getMessage()]);
    }
    exit;
}

$varieties = ($tab === 'encode' && $canEncode)
    ? $db->query('SELECT * FROM varieties WHERE active=1 ORDER BY code')->fetchAll()
    : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0a7f5d">
<title>PHILSURIN Mobile</title>
<script src="password-eye.js"></script>
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="assets/philsurin-seal.png">
<style>
*{box-sizing:border-box}
body{margin:0;font:14px/1.5 Inter,Segoe UI,Arial,sans-serif;background:#0f2a1f;color:#10243b;min-height:100vh}
.app{max-width:520px;margin:0 auto;min-height:100vh;background:#f4f7f5;display:flex;flex-direction:column}
.top{position:sticky;top:0;z-index:5;background:#10243b;color:#fff;padding:14px 16px 10px}
.top h1{margin:0;font-size:17px;letter-spacing:.04em}
.top p{margin:2px 0 0;color:#8fd7b4;font-size:11px}
.tabs{position:sticky;top:0;z-index:4;display:flex;gap:8px;padding:10px 12px;background:#10243b}
.tabs a{flex:1;text-align:center;text-decoration:none;font-weight:800;font-size:12px;padding:12px 6px;border-radius:12px;background:#1d4960;color:#bcd2e2}
.tabs a.active{background:#0a7f5d;color:#fff}
.body{padding:12px 12px 28px;display:grid;gap:12px}
.card{background:#fff;border:1px solid #dfe8e2;border-radius:14px;padding:14px}
.card h2{margin:0 0 4px;font-size:14px}
.hint{margin:0 0 10px;color:#5b6b7d;font-size:12px}
label{display:grid;gap:5px;font-size:11px;font-weight:700;margin:0 0 10px}
input,select,textarea{width:100%;padding:12px;border:1px solid #c9d6ce;border-radius:10px;font:inherit;font-size:14px;background:#fff}
textarea{min-height:74px;resize:vertical}
button:not(.pw-eye){border:0;border-radius:12px;background:#0a7f5d;color:#fff;font-weight:800;padding:14px;font-size:14px;width:100%}
button.ghost{background:#e8edf1;color:#334155}
.stage-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.stage-row button{padding:11px;font-size:12px;background:#e8edf1;color:#334155}
.stage-row button.on{background:#0a7f5d;color:#fff}
.err{background:#fdecea;border:1px solid #b42318;color:#8f1d15;border-radius:10px;padding:10px 12px;font-size:12px;font-weight:700}
.ok{background:#dff2ea;border:1px solid #0a7f5d;color:#0a5c42;border-radius:10px;padding:10px 12px;font-size:12px;font-weight:700}
.rowline{border-bottom:1px solid #e5ebf0;padding:9px 0;font-size:12px}
.rowline b{display:block;font-size:13px}
.install{display:flex;gap:10px;align-items:center}
.logout{color:#8fd7b4;font-size:12px}
.kv{display:flex;justify-content:space-between;font-size:12px;color:#42546a;padding:2px 0}
</style>
</head>
<body>
<div class="app">
  <div class="top">
    <h1>PHILSURIN Mobile</h1>
    <p>Encoding | Audit Trail | Downloads</p>
  </div>
<?php if (!$loggedIn): ?>
  <div class="body">
    <div class="card">
      <h2>Validator sign in</h2>
      <p class="hint">Use the same account created in Manage Users. Validator roles open Encoding + Audit Trail here.</p>
      <?php if ($error): ?><p class="err"><?=e($error)?></p><?php endif ?>
      <form method="post">
        <label>Username<input name="username" required autocomplete="username"></label>
        <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
        <button name="login">LOG IN</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <nav class="tabs">
    <?php if ($canEncode): ?><a class="<?=$tab==='encode'?'active':''?>" href="mobile.php?tab=encode">Encode</a><?php endif ?>
    <?php if ($canAudit): ?><a class="<?=$tab==='audit'?'active':''?>" href="mobile.php?tab=audit">Audit</a><?php endif ?>
    <?php if ($canExport): ?><a class="<?=$tab==='downloads'?'active':''?>" href="mobile.php?tab=downloads">Files</a><?php endif ?>
  </nav>
  <div class="body">
    <div class="card install">
      <div style="flex:1">
        <b style="font-size:13px">Install this app</b>
        <p class="hint" style="margin:2px 0 0">Open in Chrome, tap the menu, then Add to Home screen. It then opens like a native app.</p>
      </div>
      <span class="kv"><?=e(strtoupper($role))?></span>
    </div>
    <div class="card">
      <div class="kv"><span>Signed in</span><b><?=e($_SESSION['user']['username'])?></b></div>
      <div class="kv"><span>Role</span><b><?=e(strtoupper($role))?></b></div>
      <div class="kv"><span>Encoding stages</span><b><?=e($canEncode ? implode(', ', $allowedStages) : '--')?></b></div>
      <p style="margin:10px 0 0"><a style="color:#0a7f5d;font-size:12px" href="mobile.php?logout=1">Log out</a> | <a style="color:#0a7f5d;font-size:12px" href="index.php">Desktop site</a></p>
    </div>
    <?php if ($tab === 'encode' && $canEncode): ?>
    <div class="card">
      <h2>New encoding</h2>
      <p class="hint">Stage buttons are limited to your validator scope. Saving uses the same validation as desktop.</p>
      <div id="mMsg"></div>
      <div class="stage-row" id="mStages">
        <?php foreach (['01 GRADING','02 GRADING','03 GRADING','04 GRADING','HARVEST'] as $stage): $allowed = in_array($stage, $allowedStages, true); ?>
        <button type="button" data-stage="<?=e($stage)?>" <?= $allowed ? '' : 'disabled title="Not in your validator scope"' ?> style="<?= $allowed ? '' : 'opacity:.4' ?>"><?=e($stage)?></button>
        <?php endforeach ?>
      </div>
      <div style="height:10px"></div>
      <label>Location of Project<input id="mProject" value="HAWAIIAN_SILAY"></label>
      <label>Date Planted<input id="mPlant" type="date"></label>
      <label>DR / DP<input id="mDr" type="date"></label>
      <label>Check / Control<input id="mCtl" value="MYCK"></label>
      <label>Size of Location<input id="mSize" placeholder="e.g. 3.5"></label>
      <label>Variety<select id="mVariety"><option value="">-- Select Variety --</option><?php foreach ($varieties as $v): ?><option value="<?=$v['id']?>"><?=e($v['code'].' - '.$v['name'])?></option><?php endforeach; ?></select></label>
      <label>Grading date<input id="mDate" type="date" value="<?=date('Y-m-d')?>"></label>
      <div id="mFields"></div>
      <button id="mSave" type="button">SAVE TO LOCATION</button>
    </div>
    <?php endif; ?>
    <?php if ($tab === 'audit' && $canAudit): ?>
    <div class="card">
      <h2>Audit trail</h2>
      <p class="hint">Latest encoded rows across all locations.</p>
      <div id="mAudit"><p class="hint">Loading...</p></div>
    </div>
    <?php endif; ?>
    <?php if ($tab === 'downloads' && $canExport): ?>
    <div class="card">
      <h2>Downloads</h2>
      <p class="hint">Same Excel/CSV exports as desktop, formatted for phones.</p>
      <label>Location (optional)<input id="mLoc" placeholder="Leave blank for all locations"></label>
      <button type="button" data-dl="xls">DOWNLOAD EXCEL (.XLS)</button>
      <div style="height:8px"></div>
      <button class="ghost" type="button" data-dl="csv">DOWNLOAD CSV</button>
      <div style="height:8px"></div>
      <button class="ghost" type="button" data-dl="tpl">IMPORT TEMPLATE (CSV)</button>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php if ($loggedIn): ?>
<script>
(function () {
  var stage = '01 GRADING';
  var msg = document.getElementById('mMsg');
  function say(text, ok) {
    if (!msg) return;
    msg.textContent = text;
    msg.style.color = ok ? '#0a7f5d' : '#a3231d';
    msg.style.fontWeight = '700';
    msg.style.fontSize = '12px';
  }
  // Must mirror grading_functions.php:stage_fields() so Replicates 1-4 submit the
  // field_a..field_e / remarks names that save_validation_rows() expects.
  function stageFields(s) {
    if (String(s).indexOf('HARVEST') >= 0) return [['Observations', 'remarks', 'textarea']];
    if (s === '01 GRADING') return [['Germination', 'a', 'number'], ['E-S', 'b', 'number'], ['G-S', 'c', 'number'], ['Remarks', 'remarks', 'textarea']];
    return [['Population', 'a', 'number'], ['Height', 'b', 'number'], ['Size', 'c', 'number'], ['Uniformity', 'd', 'number'], ['CS', 'e', 'text'], ['Remarks', 'remarks', 'textarea']];
  }
  var box = document.getElementById('mFields');
  function render() {
    if (!box) return;
    box.innerHTML = '';
    for (var r = 1; r <= 4; r++) {
      var wrap = document.createElement('fieldset');
      wrap.style.cssText = 'border:1px solid #dfe8e2;border-radius:10px;margin:0 0 10px;padding:4px 10px 0';
      var legend = document.createElement('legend');
      legend.textContent = 'Replicate ' + r;
      legend.style.fontWeight = '800';
      legend.style.fontSize = '12px';
      wrap.appendChild(legend);
      stageFields(stage).forEach(function (f) {
        var lab = document.createElement('label');
        lab.appendChild(document.createTextNode(f[0]));
        var el;
        if (f[2] === 'textarea') {
          el = document.createElement('textarea');
        } else {
          el = document.createElement('input');
          el.type = f[2];
          if (f[2] === 'number') el.step = 'any';
        }
        el.dataset.r = String(r);
        el.dataset.k = f[1];
        lab.appendChild(el);
        wrap.appendChild(lab);
      });
      box.appendChild(wrap);
    }
  }
  var stageButtons = Array.prototype.slice.call(document.querySelectorAll('#mStages button'));
  stageButtons.forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      stage = b.dataset.stage;
      stageButtons.forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
      render();
    });
  });
  var firstAllowed = stageButtons.filter(function (b) { return !b.disabled; })[0];
  if (firstAllowed) {
    firstAllowed.classList.add('on');
    stage = firstAllowed.dataset.stage;
  }
  render();
  var saveBtn = document.getElementById('mSave');
  if (saveBtn) saveBtn.addEventListener('click', function () {
    var val = function (id) {
      var el = document.getElementById(id);
      return el ? el.value : '';
    };
    var payload = {
      stage: stage,
      location_id: '',
      project_location: val('mProject'),
      date_planted: val('mPlant'),
      dr_dp: val('mDr'),
      control_name: val('mCtl'),
      location_size: val('mSize'),
      variety_id: val('mVariety'),
      grading_date: val('mDate')
    };
    Array.prototype.forEach.call(box ? box.querySelectorAll('[data-r]') : [], function (el) {
      var key = el.dataset.k === 'remarks' ? 'remarks' : 'field_' + el.dataset.k;
      if (!payload[key]) payload[key] = {};
      payload[key][el.dataset.r] = el.value;
    });
    if (!payload.project_location || !payload.variety_id || !payload.location_size) {
      say('Project, Variety and Size of Location are required.', false);
      return;
    }
    var parts = [];
    Object.keys(payload).forEach(function (key) {
      var value = payload[key];
      if (value && typeof value === 'object') {
        Object.keys(value).forEach(function (rep) {
          parts.push(encodeURIComponent(key + '[' + rep + ']') + '=' + encodeURIComponent(value[rep]));
        });
      } else {
        parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
      }
    });
    parts.push('save_validation=1');
    say('Saving...', true);
    saveBtn.disabled = true;
    fetch('mobile.php?tab=encode', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: parts.join('&')
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        saveBtn.disabled = false;
        if (j && j.ok) {
          say('Saved: ' + j.location_id + ' (' + j.saved + ' replicates).', true);
        } else {
          say((j && j.error) || 'Save failed.', false);
        }
      })
      .catch(function () {
        saveBtn.disabled = false;
        say('Network error - nothing was saved. Try again.', false);
      });
  });
  var auditBox = document.getElementById('mAudit');
  if (auditBox) {
    fetch('api/mobile/audit?limit=40', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        var logs = ((((j || {}).data) || {}).logs) || [];
        auditBox.innerHTML = logs.length
          ? logs.map(function (l) {
              return '<div class="kv"><span>' + (l.action || '') + '<br><small>' + (l.entity_id || '') + ' ' + (l.created_at || '') + '</small></div><b>#' + l.id + '</b></div>';
            }).join('')
          : '<p class="hint">No rows yet.</p>';
      })
      .catch(function () { auditBox.textContent = 'Audit unavailable.'; });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-dl]'), function (b) {
    b.addEventListener('click', function () {
      var lc = (document.getElementById('mLoc') || {}).value || '';
      if (b.dataset.dl === 'tpl') {
        window.location.href = 'export.php?template=1&format=csv';
      } else if (b.dataset.dl === 'csv') {
        window.location.href = 'export.php?format=csv' + (lc ? '&location=' + encodeURIComponent(lc) : '');
      } else {
        window.location.href = 'export.php' + (lc ? '?location=' + encodeURIComponent(lc) : '');
      }
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>

