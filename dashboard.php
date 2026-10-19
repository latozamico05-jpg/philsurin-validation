<?php
require 'config.php';
require_login();
require 'sidebar.php';
$stats=['total'=>0,'validated'=>0,'pending'=>0,'target'=>0];
$monthly=[];$error='';
try {
    $stats['total']=(int)$db->query('SELECT COUNT(*) FROM masterlist')->fetchColumn();
    $stats['validated']=(int)$db->query("SELECT COUNT(DISTINCT masterlist_id) FROM validation_sessions WHERE status='VERIFIED'")->fetchColumn();
    $stats['target']=(int)($db->query("SELECT config_value FROM system_config WHERE config_key='VALIDATION_TARGET'")->fetchColumn() ?: 0);
    $monthly=$db->query('SELECT m.* FROM dashboard_monthly_metrics m ORDER BY m.year_month DESC LIMIT 6')->fetchAll();
    if ($stats['total'] === 0) {
        $legacyRows = $db->query('SELECT g.* FROM grading_records g WHERE NOT EXISTS (SELECT 1 FROM hidden_trial_locations h WHERE h.location_id=g.location_id)')->fetchAll();
        $savedPlots = [];
        foreach ($legacyRows as $row) {
            if (row_has_data($row)) {
                $savedPlots[$row['location_id'] . '|' . $row['variety_id']] = true;
            }
        }
        $stats['total'] = count($savedPlots);
        if ($stats['validated'] === 0) {
            $stats['validated'] = count($savedPlots);
        }
    }
} catch (Throwable $x) {
    try { $stats['total']=(int)$db->query('SELECT COUNT(DISTINCT location_id) FROM grading_records')->fetchColumn(); } catch (Throwable $ignore) {}
    $error='The extended dashboard tables are not installed yet. Run database/schema.sql and database/seed.sql to enable live analytics.';
}
$stats['pending']=max(0,$stats['total']-$stats['validated']);
$percent=$stats['target'] ? min(100,round(($stats['validated']/$stats['target'])*100)) : 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHILSURIN Dashboard</title>
<link rel="stylesheet" href="style.css">
<style>
:root{
  --ink:#0f172a;
  --muted:#64748b;
  --line:#e2e8f0;
  --green:#059669;
  --blue:#0ea5e9;
  --amber:#f59e0b;
  --purple:#8b5cf6;
  --pink:#ec4899;
  --shadow:0 20px 40px -10px rgba(15,23,42,0.12);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; padding: 0; height: 100%;
  background: #0f172a;
  color: var(--ink);
  font-family: "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  overflow-x: hidden;
}
body::before {
  content: "";
  position: fixed; z-index: -1;
  top: 0; left: 0; width: 100%; height: 100%;
  background:
    radial-gradient(circle at 15% 20%, rgba(5,150,105,0.18), transparent 35%),
    radial-gradient(circle at 85% 30%, rgba(14,165,233,0.15), transparent 35%),
    radial-gradient(circle at 40% 85%, rgba(139,92,246,0.12), transparent 35%);
  background-size: 200% 200%;
  animation: gradientMove 18s ease-in-out infinite alternate;
}
.bg-blob {
  position: fixed; z-index: -1;
  border-radius: 50%; filter: blur(60px); opacity: 0.35;
  animation: floatBlob 14s ease-in-out infinite;
}
.blob-1 { top: -10%; left: -10%; width: 600px; height: 600px; background: #0ea5e9; animation-delay: 0s; }
.blob-2 { bottom: -10%; right: -10%; width: 700px; height: 700px; background: #059669; animation-delay: -4s; }
.blob-3 { top: 30%; left: 50%; width: 500px; height: 500px; background: #8b5cf6; animation-delay: -8s; }
@keyframes gradientMove { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }
@keyframes floatBlob { 0%, 100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px, -40px) scale(1.05); } 66% { transform: translate(-20px, 30px) scale(0.95); } }
.dashboard-shell { max-width: 1540px; margin: 0 auto; padding: 24px 28px 60px; }
.dashboard-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.brand { display: flex; flex-direction: column; gap: 2px; }
.brand h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff; }
.brand p { margin: 0; font-size: 13px; color: #94a3b8; }
.user-chip { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 8px 14px; color: #e2e8f0; font-weight: 700; font-size: 12px; backdrop-filter: blur(10px); }
.live-pill { display: inline-flex; align-items: center; gap: 7px; background: rgba(5,150,105,0.12); border: 1px solid rgba(5,150,105,0.35); color: #6ee7b7; font-weight: 800; font-size: 11px; padding: 8px 14px; border-radius: 999px; backdrop-filter: blur(10px); }
.dot-live { width: 7px; height: 7px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 8px #22c55e; animation: pulseLive 2s ease-in-out infinite; }
@keyframes pulseLive { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
.card { background: rgba(255,255,255,0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.4); border-radius: 20px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 20px; }
.card h2 { margin: 0 0 16px; font-size: 15px; font-weight: 800; letter-spacing: 0.3px; text-transform: uppercase; color: var(--muted); }
.card .hint { margin: 0 0 20px; font-size: 12px; color: var(--muted); }
.metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.metric { position: relative; overflow: hidden; }
.metric-glow { position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; border-radius: 50%; background: rgba(5,150,105,0.10); filter: blur(30px); pointer-events: none; }
.metric-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted); margin-bottom: 10px; display: block; }
.metric-value { font-size: 36px; font-weight: 900; letter-spacing: -1px; color: var(--ink); line-height: 1; }
.metric-value.metric-accent { color: var(--green); }
.animate-count { animation: countUp 1.2s ease-out forwards; }
@keyframes countUp { 0% { opacity: 0; transform: translateY(12px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<div class="dashboard-shell">
  <div class="dashboard-header">
    <div class="brand">
      <h1>PHILSURIN Dashboard</h1>
      <p>Live summary of field validation activity &bull; Sugarcane Variety Validation System</p>
    </div>
    <div style="display:flex;gap:12px;align-items:center">
      <span class="live-pill"><span class="dot-live"></span> LIVE DATABASE</span>
      <a href="index.php" class="user-chip">&#x1F464; <?=e($_SESSION['user']['username'] ?? 'User')?></a>
    </div>
  </div>

  <?php if($error): ?>
  <div class="notice"><?=e($error)?></div>
  <?php endif; ?>

  <div class="metric-grid">
    <article class="card metric">
      <span class="metric-label">Total Masterlist Records</span>
      <div class="metric-value metric-accent animate-count"><?=number_format($stats['total'])?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Active locations &bull; All varieties</div>
    </article>
    <article class="card metric">
      <span class="metric-label">Validated Plots</span>
      <div class="metric-value metric-accent animate-count"><?=number_format($stats['validated'])?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Verified validation records</div>
    </article>
    <article class="card metric">
      <span class="metric-label">Pending Validation</span>
      <div class="metric-value animate-count"><?=number_format($stats['pending'])?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Remaining to reach target</div>
    </article>
  </div>
<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;margin-bottom:24px">
    <article class="card">
      <h2>Validation Goal</h2>
      <p class="hint">Progress calculated from system configuration and verified validation records.</p>
      <div class="progress-track">
        <div class="progress-fill" style="width: <?=$percent?>%;"></div>
      </div>
      <div class="progress-meta">
        <span><?=number_format($stats['validated'])?> validated</span>
        <span><?=number_format($stats['target'])?> target &bull; <?=$percent?>%</span>
      </div>
    </article>

    <article class="card chart-wrap">
      <h2>Encoding Progress</h2>
      <div style="position:relative;width:100%;max-width:260px;margin:0 auto;">
        <svg class="donut-svg" viewBox="0 0 150 150">
          <circle cx="75" cy="75" r="65" fill="none" stroke="#f1f5f9" stroke-width="14" />
          <?=$donutSegments?>
        </svg>
        <div class="donut-center">
          <div class="big"><?=$totalEncoded?></div>
          <div class="sub">Total Encoded</div>
        </div>
      </div>
      <div class="chart-legend">
        <?php foreach ($stageLabels as $stageKey => $label) {
          $count = $stageData[$stageKey] ?? 0;
          $pct = $totalEncoded ? round(($count / $totalEncoded) * 100) : 0;
          $color = $stageColors[$stageKey] ?? '#94a3b8';
          echo '<div class="chart-legend-item"><span class="chart-legend-dot" style="background:' . $color . '"></span><span class="chart-legend-label">' . e($label) . '</span><span class="chart-legend-value">' . number_format($count) . ' (' . $pct . '%)</span></div>';
        } ?>
      </div>
    </article>
  </div>

  <article class="card">
    <h2>Monthly Analytics</h2>
    <?php if($monthly): ?>
    <div class="monthly-wrap">
      <?php foreach($monthly as $row): ?>
      <div class="month-row">
        <span class="month-name"><?=e($row['year_month'])?></span>
        <span class="month-total"><?=number_format($row['total_records'] ?? 0)?></span>
        <span class="month-val"><?=number_format($row['validated_records'] ?? 0)?></span>
        <span class="month-pct"><?=e($row['completion_percent'] ?? 0)?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">No monthly analytics available yet. Encode validation records to populate trends.</div>
    <?php endif; ?>
  </article>
</div>
</body>
</html>
<style>
.chart-wrap { position: relative; width: 100%; max-width: 340px; margin: 0 auto; }
.donut-svg { width: 100%; max-width: 260px; transform: rotate(-90deg); }
.chart-legend { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
.chart-legend-item { display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--ink); }
.chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.chart-legend-label { flex: 1; }
.chart-legend-value { font-weight: 800; color: var(--muted); }
.donut-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
.donut-center .big { font-size: 28px; font-weight: 900; color: var(--ink); }
.donut-center .sub { font-size: 11px; color: var(--muted); font-weight: 700; }
.monthly-wrap { display: grid; grid-template-columns: 1fr; gap: 12px; }
.month-row { display: grid; grid-template-columns: 140px 1fr 120px 100px; gap: 12px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 12px; }
.month-row:last-child { border-bottom: none; }
.month-name { font-weight: 800; color: var(--ink); }
.month-total { color: var(--muted); }
.month-val { color: var(--green); font-weight: 800; }
.month-pct { font-weight: 800; color: var(--blue); }
.empty-state { color: var(--muted); font-size: 13px; padding: 30px 0; text-align: center; }
@media (max-width: 1000px) {
  .metric-grid { grid-template-columns: 1fr 1fr; }
  .month-row { grid-template-columns: 1fr 1fr 1fr 1fr; font-size: 11px; }
}
@media (max-width: 600px) {
  .metric-grid { grid-template-columns: 1fr; }
}
</style>

