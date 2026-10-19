<?php
// offline-dashboard.php — View and manage offline-stored records.
// Works even when MySQL is down (uses SQLite fallback). Requires login.
require 'config.php';
require_login();
require 'sidebar.php';

$st = philsurin_offline_count();
$pending = philsurin_offline_pending(100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offline Data — PHILSURIN</title>
<link rel="stylesheet" href="ios-theme.css?v=<?= @filemtime(__DIR__ . '/ios-theme.css') ?: time() ?>">
<style>
  .off-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
  .stat-card { background:#f5f7fa; border:1px solid #e2e5ea; border-radius:10px; padding:16px; margin-bottom:16px; display:inline-block; min-width:200px; }
  .stat-num { font-size:24px; font-weight:bold; color:#1a73e8; }
  .stat-lbl { font-size:12px; color:#5f6368; text-transform:uppercase; letter-spacing:.5px; }
  table { width:100%; border-collapse:collapse; margin-top:16px; background:#fff; }
  th, td { border:1px solid #e2e5ea; padding:10px; text-align:left; font-size:13px; }
  th { background:#f5f7fa; font-weight:600; color:#3c4043; }
  tr:nth-child(even) td { background:#fafafa; }
  .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:bold; }
  .badge-ok { background:#e6f4ea; color:#137333; }
  .badge-pend { background:#fff9c4; color:#9a7d0a; }
  .btn { background:#1a73e8; color:#fff; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; }
  .btn:hover { background:#1557b0; }
  .btn-secondary { background:#5f6368; }
  .btn-secondary:hover { background:#3c4043; }
  .btn-danger { background:#d93025; }
  .btn-danger:hover { background:#a21f18; }
  .hint { color:#5f6368; font-size:12px; margin-top:8px; }
  .toast { position:fixed; top:16px; right:16px; background:#1a73e8; color:#fff; padding:10px 16px; border-radius:6px; display:none; z-index:9999; }
</style>
</head>
<body>
<?php if (!empty($_SESSION['user'])): ?>
<nav style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #e2e5ea; background:#fff;">
  <strong>Offline Data Dashboard</strong>
  <div style="display:flex; gap:8px;">
    <a href="index.php" class="btn btn-secondary" style="text-decoration:none;">Back to Encoding</a>
    <a href="logout.php" class="btn btn-danger" style="text-decoration:none;">Logout</a>
  </div>
</nav>

<div class="off-wrap">
  <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
    <div class="stat-card">
      <div class="stat-num"><?= $st['total'] ?></div>
      <div class="stat-lbl">Total Offline Records</div>
    </div>
    <div class="stat-card">
      <div class="stat-num badge-pend"><?= $st['pending'] ?></div>
      <div class="stat-lbl">Pending Sync</div>
    </div>
    <div class="stat-card">
      <div class="stat-num badge-ok"><?= $st['synced'] ?></div>
      <div class="stat-lbl">Already Synced</div>
    </div>
  </div>

  <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
    <button class="btn" onclick="syncNow()">Sync Pending to Server</button>
    <button class="btn btn-secondary" onclick="exportOffline()">Export Offline DB (JSON)</button>
    <span class="hint">Offline DB file: data/philsurin-offline.sqlite</span>
  </div>

  <div id="toast" class="toast"></div>

  <?php if ($st['total'] === 0): ?>
    <div style="text-align:center; padding:40px; color:#5f6368;">
      <p>No offline records stored yet.</p>
      <p class="hint">When encoding offline, data is saved to this SQLite database automatically.</p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Client ID</th><th>Stage</th><th>Project Location</th><th>Variety</th><th>Grading Date</th>
          <th>Replicate</th><th>Synced</th><th>Queued At</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $r): ?>
        <tr>
          <td><?= e($r['client_id']) ?></td>
          <td><?= e($r['stage']) ?></td>
          <td><?= e($r['project_location']) ?></td>
          <td><?= e($r['variety_id'] ?? '-') ?></td>
          <td><?= e($r['grading_date']) ?></td>
          <td><?= e($r['replicate']) ?></td>
          <td><span class="badge badge-pend">PENDING</span></td>
          <td><?= e($r['queued_at']) ?></td>
          <td>
            <button class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" onclick="deleteRecord('<?= e($r['client_id']) ?>')">Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($st['pending'] > 100): ?>
      <p class="hint">Showing first 100 pending records. Total pending: <?= $st['pending'] ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
function showToast(msg, type='ok') {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = type === 'ok' ? '#137333' : '#d93025';
  t.style.display = 'block';
  setTimeout(function(){ t.style.display = 'none'; }, 3000);
}
function syncNow() {
  fetch('offline-sync.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) })
    .then(function(r){ return r.json(); })
    .then(function(j) {
      showToast('Synced: ' + j.synced + ' | Failed: ' + j.failed);
      setTimeout(function(){ location.reload(); }, 1000);
    })
    .catch(function(){ showToast('Sync failed - server may be down.', 'err'); });
}
function exportOffline() {
  window.location.href = 'offline-export.php';
}
function deleteRecord(clientId) {
  if (!confirm('Delete this offline record?')) return;
  var form = new FormData();
  form.append('client_id', clientId);
  fetch('offline-delete.php', { method: 'POST', credentials: 'same-origin', body: form })
    .then(function(){ location.reload(); });
}
</script>
</body>
</html>
<?php endif; ?>