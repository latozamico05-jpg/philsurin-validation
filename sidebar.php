<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
// Live count of active varieties, used for the sidebar badge.
$varietyCount = 0;
if (isset($db) && $db instanceof PDO) {
    try {
        $varietyCount = (int) $db->query('SELECT COUNT(*) FROM varieties WHERE active=1')->fetchColumn();
    } catch (Throwable $e) {
        $varietyCount = 0;
    }
}
if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
body:has(.sidebar){padding-left:260px}body:has(.sidebar) main>header span{display:none}.report-link{display:none!important}
.sidebar{position:fixed;z-index:1000;top:0;left:0;bottom:0;width:236px;padding:28px 16px 20px;background:#10243b;color:#dce8f0;display:flex;flex-direction:column;box-shadow:8px 0 28px rgba(20,40,60,.12);border-right:1px solid #203a52;font-family:Inter,Segoe UI,Arial,sans-serif}
.sidebar-brand{display:flex;align-items:center;gap:11px;margin:4px 6px 14px;padding:4px 4px 15px;border-bottom:1px solid rgba(120,150,175,.35);font-size:19px;font-weight:800;letter-spacing:.5px;line-height:1.1;white-space:nowrap}
.sidebar-brand img.sidebar-seal{display:block!important;flex:0 0 58px!important;width:58px!important;height:58px!important;max-width:58px!important;object-fit:contain;background:transparent}
.sidebar-brand>span{flex:0 0 auto;white-space:nowrap;overflow:visible;text-overflow:clip}
.user-chip{display:flex;align-items:center;gap:10px;margin:22px 8px 28px}.user-avatar{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#21a879;color:#fff;font-weight:800}.user-chip b{display:block;color:#fff;font-size:12px}.user-chip small{display:block;color:#8ca3b7;font-size:10px;margin-top:3px}
.nav-label{display:block;color:#718ba2;font-size:9px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;padding:0 12px 9px}.nav-link,.logout-link{display:flex;align-items:center;gap:12px;color:#a9bac9;text-decoration:none;font-size:12px;font-weight:800;padding:12px;border-radius:8px;margin:3px 0}.nav-link:hover,.nav-link.active{color:#fff;background:#1d4960}.nav-link.active{box-shadow:inset -3px 0 #45d19a}.nav-badge{display:inline-grid;place-items:center;min-width:22px;height:20px;margin-left:auto;padding:0 7px;border-radius:999px;background:#e11d48;color:#fff;font-size:10px;font-weight:800;line-height:1;letter-spacing:.2px;box-shadow:0 2px 6px rgba(225,29,72,.45)}
.nav-link.active .nav-badge{background:#fff;color:#e11d48;box-shadow:none}
.nav-icon{display:inline-grid;place-items:center;width:20px;color:#7ed9b5;font-size:9px;font-weight:900;letter-spacing:-.3px}.logout-link{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);border-radius:0;padding-top:18px;justify-content:center!important;text-align:center!important}body:has(.sidebar) .sidebar a.logout-link{justify-content:center!important;text-align:center!important}
.sidebar-footer{margin-top:10px;padding:12px 4px 2px;border-top:1px solid rgba(120,150,175,.28);color:#8ca3b7;font-size:9.5px;font-weight:800;letter-spacing:.8px;text-align:center;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-audit{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;padding:6px 0 0}
.sidebar-audit-head{display:flex;align-items:center;gap:8px;color:#718ba2;font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:8px 12px 6px}
.sidebar-audit-count{background:#e11d48;color:#fff;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;letter-spacing:.2px}
.sidebar-audit-list{margin:0;padding:0;list-style:none;overflow-y:auto;flex:1;min-height:0}
.sidebar-audit-item{display:flex;align-items:center;gap:8px;padding:5px 12px;border-top:1px solid rgba(120,150,175,.18);font-size:11px;word-wrap:break-word;line-height:1.3}
.sidebar-audit-item:first-child{border-top:none}
.sidebar-audit-time{color:#8ca3b7;font-size:9px;white-space:nowrap;flex:0 0 auto}
.sidebar-audit-user{font-weight:800;color:#fff;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sidebar-audit-action{font-size:9.5px;color:#a9bac9;flex:1 1 auto;min-width:0;word-wrap:break-word;overflow:hidden;text-overflow:ellipsis}
.sidebar-audit-entity{color:#f0b4b4;font-size:8.5px;font-weight:700;margin-left:4px;white-space:nowrap}
@media(max-width:760px){body:has(.sidebar){padding-left:0}.sidebar{position:relative;width:100%;height:auto;padding:16px}.user-chip{margin:14px 8px}.sidebar-nav{display:grid;grid-template-columns:1fr 1fr}.nav-label{grid-column:1/-1}.logout-link{margin-top:12px}}
</style>
<aside class="sidebar" aria-label="Main navigation">
  <div class="sidebar-brand"><img class="sidebar-seal" src="assets/philsurin-seal.png" alt="" width="58" height="58"><span>PHILSURIN</span></div>
  <div class="user-chip"><span class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'] ?? 'U', 0, 1)) ?></span><span><b><?= e($_SESSION['user']['username'] ?? 'User') ?></b><small><?= e(strtoupper($_SESSION['user']['role'] ?? 'USER')) ?></small></span></div>
  <nav class="sidebar-nav">
    <span class="nav-label">Workspace</span>
    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
    <?php if (strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')) !== 'viewer'): ?><a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Encoding</a><?php endif ?>
    <?php if (!in_array(strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')), ['validator', 'validator_germination', 'validator_field', 'validator_harvest'], true)): ?><a class="nav-link <?= $currentPage === 'report.php' ? 'active' : '' ?>" href="report.php">Report</a><?php endif ?>
    <?php if (strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')) !== 'viewer'): ?>
    <a class="nav-link <?= $currentPage === 'locations.php' ? 'active' : '' ?>" href="locations.php">Trial locations</a>
    <?php endif ?>
    <?php if (philsurin_can_audit() && !philsurin_is_admin()): ?>
    <a class="nav-link <?= in_array($currentPage, ['audit.php', 'audit_management.php'], true) ? 'active' : '' ?>" href="audit.php">Audit trail</a>
    <?php endif ?>
    <?php if (in_array(strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')), ['admin'], true)): ?><a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php">Manage users</a>
    <a class="nav-link" href="users.php?varieties=1">Variety List<span class="nav-badge"><?= $varietyCount ?></span></a><?php endif ?>
  </nav>
  <?php
  $auditLogs = [];
  $auditLogsError = '';
  if (philsurin_is_admin()) {
      try {
          $auditIdCol = philsurin_audit_id_column($db);
          $auditLogs = $db->query("SELECT a.{$auditIdCol} AS id, a.user_id, COALESCE(u.username, '—') AS username, a.action, a.entity_type, a.entity_id, a.created_at FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC, a.{$auditIdCol} DESC LIMIT 60")->fetchAll();
      } catch (Throwable $t) {
          $auditLogsError = 'No audit data available.';
      }
  }
  ?>
  <?php if (philsurin_is_admin()): ?>
  <div class="sidebar-audit">
      <div class="sidebar-audit-head">
          Audit logs
          <span class="sidebar-audit-count"><?= count($auditLogs) ?></span>
      </div>
      <?php if ($auditLogsError): ?>
          <div class="sidebar-audit-msg"><?= e($auditLogsError) ?></div>
      <?php elseif ($auditLogs): ?>
          <ul class="sidebar-audit-list">
              <?php foreach (array_slice($auditLogs, 0, 40) as $log): ?>
                  <li class="sidebar-audit-item">
                      <span class="sidebar-audit-time"><?= e($log['created_at']) ?></span>
                      <span class="sidebar-audit-user"><?= e($log['username']) ?></span>
                      <span class="sidebar-audit-action"><?= e($log['action']) ?><?php if (!empty($log['entity_type'])): ?><span class="sidebar-audit-entity"><?= e($log['entity_type']) ?></span><?php endif; ?></span>
                  </li>
              <?php endforeach; ?>
          </ul>
      <?php else: ?>
          <div class="sidebar-audit-msg">No system activity yet.</div>
      <?php endif; ?>
  </div>
  <?php endif; ?>
  <a class="logout-link" href="index.php?logout=1">Log out</a>
  <div class="sidebar-footer">DEV. MICO C. LATOZA</div>
</aside>
