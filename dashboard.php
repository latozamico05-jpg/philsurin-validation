<?php
require 'config.php';
require_login();
require 'sidebar.php';
$stats=['total'=>0,'validated'=>0,'pending'=>0,'target'=>0];
$monthly=[];$error='';
/* Encoding progress: LIVE counts per grading stage from grading_records */
$stageData=[];$totalEncoded=0;
$stageLabels=[
  '01 GRADING'=>'01 Germination',
  '02 GRADING'=>'02 Uniformity',
  '03 GRADING'=>'03 Population',
  '04 GRADING'=>'04 Erectness',
  'HARVEST'=>'Harvest',
];
$stageColors=[
  '01 GRADING'=>'#059669',
  '02 GRADING'=>'#0ea5e9',
  '03 GRADING'=>'#f59e0b',
  '04 GRADING'=>'#8b5cf6',
  'HARVEST'=>'#ec4899',
];
try {
    $stats['total']=(int)$db->query('SELECT COUNT(*) FROM masterlist')->fetchColumn();
    $stats['validated']=(int)$db->query("SELECT COUNT(DISTINCT masterlist_id) FROM validation_sessions WHERE status='VERIFIED'")->fetchColumn();
    $stats['target']=(int)($db->query("SELECT config_value FROM system_config WHERE config_key='VALIDATION_TARGET'")->fetchColumn() ?: 0);
    $monthly=$db->query('SELECT m.* FROM dashboard_monthly_metrics m ORDER BY m.year_month DESC LIMIT 6')->fetchAll();
    /* PIE DATA (FUNCTIONAL, LIVE): count encoded rows per grading stage.
       Portable MySQL + SQLite: fetch rows, filter hidden + empty in PHP. */
    try {
        $hiddenIds=[];
        try { $hiddenIds=$db->query('SELECT location_id FROM hidden_trial_locations')->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $hIgnore) { $hiddenIds=[]; }
        $hiddenMap=[];
        foreach ((array)$hiddenIds as $hid) { $hiddenMap[(string)$hid]=true; }
        $pieRows=$db->query('SELECT grading_stage, location_id, germination, es_value, gs_value, remarks FROM grading_records')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pieRows as $pr) {
            $locId=(string)($pr['location_id'] ?? '');
            if ($locId!=='' && isset($hiddenMap[$locId])) continue;
            if (!row_has_data($pr)) continue;
            $norm=normalize_stage_name((string)($pr['grading_stage'] ?? ''));
            if ($norm==='') continue;
            $found=null;
            if (strpos($norm,'01')!==false) $found='01 GRADING';
            elseif (strpos($norm,'02')!==false) $found='02 GRADING';
            elseif (strpos($norm,'03')!==false) $found='03 GRADING';
            elseif (strpos($norm,'04')!==false) $found='04 GRADING';
            elseif (strpos($norm,'HARVEST')!==false) $found='HARVEST';
            else {
                foreach ($stageLabels as $stageKey=>$lbl) {
                    if ($norm===$stageKey) { $found=$stageKey; break; }
                }
                if ($found===null) {
                    $found=$norm;
                    if (!isset($stageLabels[$found])) $stageLabels[$found]=ucwords(strtolower($norm));
                    if (!isset($stageColors[$found])) {
                        $extra=['#14b8a6','#f97316','#6366f1','#eab308','#06b6d4'];
                        $stageColors[$found]=$extra[count($stageColors)%count($extra)];
                    }
                }
            }
            $stageData[$found]=(($stageData[$found] ?? 0)+1);
        }
    } catch (Throwable $pieIgnore) {
    /* LIVE PIE #2 — dual dialect: MySQL CONCAT first, SQLite || fallback. */
    try {
        $pieRows=$db->query('SELECT grading_stage, COUNT(DISTINCT CONCAT(location_id,"|",variety_id,"|",replicate)) AS plots FROM grading_records GROUP BY grading_stage')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $pieMysqlIgnore) {
        try {
            $pieRows=$db->query('SELECT grading_stage, COUNT(DISTINCT location_id || "|" || variety_id || "|" || replicate) AS plots FROM grading_records GROUP BY grading_stage')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $pieIgnore2) {
            try { $pieRows=$db->query('SELECT grading_stage, COUNT(*) AS plots FROM grading_records GROUP BY grading_stage')->fetchAll(PDO::FETCH_ASSOC); }
            catch (Throwable $pieIgnore3) { $pieRows=[]; }
        }
    }
        try {
            $stageRows=$db->query('SELECT grading_stage, COUNT(*) AS plots FROM grading_records GROUP BY grading_stage')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stageRows as $row) {
                $key=normalize_stage_name((string)($row['grading_stage'] ?? ''));
                if ($key==='') continue;
                $found=null;
                foreach ($stageLabels as $stageKey=>$label) {
                    if ($key===$stageKey || strpos($key,$stageKey)!==false || strpos($stageKey,$key)!==false) { $found=$stageKey; break; }
                }
                if ($found===null) {
                    $stageLabels[$key]=ucwords(strtolower($key));
                    if (!isset($stageColors[$key])) {
                        $extra=['#14b8a6','#f97316','#6366f1','#eab308','#06b6d4'];
                        $stageColors[$key]=$extra[count($stageColors)%count($extra)];
                    }
                    $found=$key;
                }
                $stageData[$found]=(($stageData[$found] ?? 0)+(int)($row['plots'] ?? 0));
            }
        } catch (Throwable $ignore2) { /* leave pie empty */ }
    }
    $totalEncoded=array_sum($stageData);
    /* Build SVG donut segments from live data (functional pie). */
    $donutSegments='';
    if ($totalEncoded>0) {
        $circ=2*M_PI*65; $off=0;
        foreach ($stageLabels as $sk=>$sl) {
            $c=(int)($stageData[$sk] ?? 0);
            if ($c<=0) continue;
            $frac=$c/$totalEncoded; $len=$frac*$circ;
            $col=$stageColors[$sk] ?? '#94a3b8';
            $donutSegments.='<circle cx="75" cy="75" r="65" fill="none" stroke="'.e($col).'" stroke-width="14" stroke-dasharray="'.round($len,2).' '.round($circ,2).'" stroke-dashoffset="'.round(-$off,2).'" stroke-linecap="butt"><title>'.e($sl).': '.number_format($c).'</title></circle>';
            $off+=$len;
        }
    }
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
  --ink:#06281c;
  --muted:#1c4632;
  --line:#bfe3cb;
  --green:#059669;
  --blue:#0369a1;
  --amber:#b45309;
  --purple:#7c3aed;
  --pink:#be185d;
  --shadow:0 20px 40px -10px rgba(15,23,42,0.12);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; padding: 0; height: 100%;
  background: #e9f7ef;
  color: var(--ink);
  font-family: "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  overflow-x: hidden;
}
body::before {
  content: "";
  position: fixed; z-index: -1;
  top: 0; left: 0; width: 100%; height: 100%;
  background:
    radial-gradient(circle at 15% 20%, rgba(4,120,87,0.38), transparent 38%),
    radial-gradient(circle at 85% 30%, rgba(22,163,74,0.36), transparent 38%),
    radial-gradient(circle at 40% 85%, rgba(21,128,61,0.28), transparent 38%),
    linear-gradient(135deg, #86efac 0%, #bbf7d0 40%, #dcfce7 70%, #86efac 100%);
  background-size: 200% 200%;
  animation: gradientMove 18s ease-in-out infinite alternate;
}
.bg-blob {
  position: fixed; z-index: -1;
  border-radius: 50%; filter: blur(70px); opacity: 0.5;
  animation: floatBlob 14s ease-in-out infinite;
}
.blob-1 { top: -10%; left: -10%; width: 600px; height: 600px; background: #22c55e; animation-delay: 0s; }
.blob-2 { bottom: -10%; right: -10%; width: 700px; height: 700px; background: #047857; animation-delay: -4s; }
.blob-3 { top: 30%; left: 50%; width: 500px; height: 500px; background: #86efac; animation-delay: -8s; }
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
.card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.5); border-radius: 20px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 20px; }
.card h2 { margin: 0 0 16px; font-size: 17px; font-weight: 900; letter-spacing: 0.3px; text-transform: uppercase; color: #0f172a; }
.card .hint { margin: 0 0 20px; font-size: 13.5px; font-weight: 600; line-height: 1.6; color: #334155; }
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
      <div class="month-head"><span>Month</span><span>Total Records</span><span>Validated</span><span>Completion</span></div>
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
      <div class="month-head"><span>Month</span><span>Total Records</span><span>Validated</span><span>Completion</span></div>
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
.donut-center .sub { font-size: 12px; color: #1e293b; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
.monthly-wrap { display: grid; grid-template-columns: 1fr; gap: 12px; }
.month-row { display: grid; grid-template-columns: 140px 1fr 120px 100px; gap: 12px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 12px; }
.month-row:last-child { border-bottom: none; }
.month-name { font-weight: 800; color: var(--ink); }
.month-total { color: var(--muted); }
/* ===== LABEL VISIBILITY BOOST (high-contrast override) ===== */
.dashboard-title h1{color:#ffffff;font-weight:900;text-shadow:0 2px 12px rgba(0,0,0,.55);}
.dashboard-title p{color:#e2e8f0;font-weight:600;text-shadow:0 1px 8px rgba(0,0,0,.5);}
.card{background:rgba(255,255,255,.96);border:1px solid #ffffff;}
.card h2{color:#0f172a;font-weight:900;letter-spacing:.2px;}
.card .hint{color:#334155;font-weight:600;}
.metric-label{color:#0f172a;font-weight:900;}
.metric-value{color:#0f172a;}
.metric-sub{color:#334155;font-weight:600;}
.month-name,.month-total,.month-val,.month-pct,.legend-item b,.legend-item span{color:#0f172a;}
.month-total,.month-val{font-weight:900;}
.sec-h{color:#0f172a;font-weight:900;}
.rank-badge{color:#0f172a;font-weight:900;}
.empty{color:#475569;font-weight:600;}
.legend-dot{box-shadow:0 0 0 2px rgba(15,23,42,.15);}
.chart-legend-item{color:#0f172a;font-weight:700;}
.chart-legend-value{color:#0f172a;font-weight:900;}
.donut-center .big{color:#0f172a;}
.donut-center .sub{color:#334155;font-weight:800;}
.pct-big{color:#0f172a;}
.kpi-card b{color:#0f172a;}
.kpi-card span{color:#334155;font-weight:700;}
.live{color:#065f46;border-color:#6ee7b7;background:#d1fae5;}
/* ===== END VISIBILITY BOOST ===== */
.month-val { color: var(--green); font-weight: 800; }
.month-pct { font-weight: 800; color: var(--blue); }
.empty-state { color: var(--muted); font-size: 13px; padding: 30px 0; text-align: center; }
  /* ===== HIGH-VISIBILITY LABEL OVERRIDES ===== */
  .card { background: rgba(255,255,255,0.97) !important; border: 2px solid #94a3b8 !important; }
  .card h2 { color: #0b1220 !important; font-size: 19px !important; font-weight: 900 !important; letter-spacing: -0.3px !important; text-transform: none !important; }
  .card .hint { color: #1e293b !important; font-size: 13.5px !important; font-weight: 600 !important; line-height: 1.6 !important; }
  .dashboard-subtitle { color: #e2e8f0 !important; font-weight: 600 !important; }
  .metric-label { color: #0f172a !important; font-size: 13px !important; font-weight: 900 !important; letter-spacing: 0.8px !important; }
  .metric-value { color: #0b1220 !important; font-size: 42px !important; font-weight: 900 !important; }
  .metric-grid article.card div[style] { font-size: 13px !important; color: #334155 !important; font-weight: 700 !important; margin-top: 8px !important; }
  .metric-sub { font-size: 13px !important; color: #334155 !important; font-weight: 700 !important; }
  .donut-center .big { font-size: 34px !important; font-weight: 900 !important; color: #0b1220 !important; }
  .donut-center .sub { font-size: 12px !important; font-weight: 800 !important; color: #334155 !important; text-transform: uppercase !important; letter-spacing: 0.6px !important; }
  .chart-legend-item { font-size: 13.5px !important; font-weight: 700 !important; color: #0b1220 !important; }
  .chart-legend-label { color: #0b1220 !important; font-weight: 800 !important; }
  .chart-legend-value { font-weight: 900 !important; color: #0f172a !important; }
  .chart-legend-dot { width: 13px !important; height: 13px !important; border: 2px solid #fff !important; box-shadow: 0 1px 4px rgba(15,23,42,0.35) !important; }
  .donut-svg circle { transition: stroke-width 0.2s ease; }
  .donut-svg circle:hover { stroke-width: 19 !important; }
  .month-head { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; color: #0f172a; padding-bottom: 8px; border-bottom: 2px solid #94a3b8; }
  .month-name { font-weight: 900 !important; color: #0b1220 !important; font-size: 13.5px !important; }
  .month-total { color: #0f172a !important; font-weight: 800 !important; font-size: 13.5px !important; }
  .month-val { color: #047857 !important; font-weight: 900 !important; font-size: 13.5px !important; }
  .month-pct { font-weight: 900 !important; color: #0369a1 !important; font-size: 13.5px !important; }
  .progress-meta span { color: #0f172a !important; font-weight: 800 !important; font-size: 13px !important; }
@media (max-width: 1000px) {
  .metric-grid { grid-template-columns: 1fr 1fr; }
  .month-row { grid-template-columns: 1fr 1fr 1fr 1fr; font-size: 11px; }
}
@media (max-width: 600px) {
  .metric-grid { grid-template-columns: 1fr; }
}
</style>

