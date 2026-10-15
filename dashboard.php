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
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PHILSURIN Dashboard</title><link rel="stylesheet" href="style.css"><style>
.dashboard-main{max-width:1540px}.dashboard-title{display:flex;justify-content:space-between;align-items:end;margin-bottom:22px}.dashboard-title h1{margin:0;font-size:26px}.dashboard-title p{margin:6px 0 0;color:var(--muted)}.live-pill{background:#e7f6f0;color:var(--green);font-weight:800;font-size:10px;padding:8px 11px;border-radius:99px}.metric-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.metric{position:relative;overflow:hidden}.metric:after{content:'';position:absolute;right:-23px;bottom:-35px;width:90px;height:90px;border-radius:50%;background:#eef9f3}.metric-label{color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}.metric-value{display:block;margin-top:12px;font-size:30px;letter-spacing:-1px}.metric-accent{color:var(--green)}.dashboard-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.progress-track{height:13px;background:#edf2f6;border-radius:99px;overflow:hidden}.progress-fill{height:100%;width:<?=$percent?>%;background:linear-gradient(90deg,#07865f,#47c896);border-radius:inherit}.progress-meta{display:flex;justify-content:space-between;margin-top:10px;color:var(--muted);font-size:11px}.empty-state{color:var(--muted);padding:22px 0;font-size:12px}.mini-table{width:100%;border-collapse:collapse}.mini-table th,.mini-table td{text-align:left;border-bottom:1px solid var(--line);padding:10px 4px;font-size:11px}.mini-table th{color:var(--muted);font-size:9px;text-transform:uppercase}.notice{padding:12px 14px;border-radius:9px;background:#fff7e5;color:#8a5a00;font-size:12px;margin-bottom:18px}@media(max-width:950px){.metric-grid{grid-template-columns:1fr 1fr}.dashboard-grid{grid-template-columns:1fr}}@media(max-width:560px){.metric-grid{grid-template-columns:1fr}.dashboard-title{align-items:start;gap:12px;flex-direction:column}}
</style></head><body><main class="dashboard-main"><header><h1>PHILSURIN</h1><span><?=e($_SESSION['user']['username'])?></span></header><?php if($error):?><div class="notice"><?=e($error)?></div><?php endif?><div class="dashboard-title"><div><h1>Validation Dashboard</h1><p>Live summary of PHILSURIN field validation activity.</p></div><span class="live-pill">â— LIVE DATABASE</span></div><section class="metric-grid"><article class="card metric"><span class="metric-label">Total masterlist records</span><strong class="metric-value"><?=number_format($stats['total'])?></strong></article><article class="card metric"><span class="metric-label">Validated plots</span><strong class="metric-value metric-accent"><?=number_format($stats['validated'])?></strong></article><article class="card metric"><span class="metric-label">Pending validation</span><strong class="metric-value"><?=number_format($stats['pending'])?></strong></article></section><section class="dashboard-grid"><article class="card"><h2>Validation goal</h2><p class="hint">Progress is calculated from system configuration and verified validation records.</p><div style="margin-top:24px" class="progress-track"><div class="progress-fill"></div></div><div class="progress-meta"><span><?=number_format($stats['validated'])?> validated</span><span><?=number_format($stats['target'])?> target Â· <?=$percent?>%</span></div></article></section><section class="card"><h2>Monthly analytics</h2><?php if($monthly):?><table class="mini-table"><thead><tr><th>Month</th><th>Total</th><th>Validated</th><th>Completion</th></tr></thead><tbody><?php foreach($monthly as $row):?><tr><td><?=e($row['year_month'])?></td><td><?=e($row['total_records'])?></td><td><?=e($row['validated_records'])?></td><td><?=e($row['completion_percent'])?>%</td></tr><?php endforeach?></tbody></table><?php else:?><div class="empty-state">No monthly analytics available yet.</div><?php endif?></section></main><style>.smallbtn{display:inline-block;background:#07865f;color:#fff;padding:9px 12px;border-radius:7px;text-decoration:none;font-weight:800;font-size:10px;margin:8px 5px 0 0}</style></body></html>
