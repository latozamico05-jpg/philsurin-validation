<?php
// ============================================================================
//  audit-auto.php — ONE-CLICK AUTO-CONNECT to the Audit Trail.
//  Open on the tunnel:  https://lnmmkbpg-3000.asse.devtunnels.ms/audit-auto.php
//  It pings audit.php and auto-sends you there (302 -> /audit.php).
//  If the session is missing you land on the login screen; after login the
//  fixed proxy keeps you connected — no more bounce loop.
// ============================================================================
require_once __DIR__ . '/config.php';
$target = rtrim(BASE_URL, '/') . '/audit.php';
if (!empty($_GET['location'])) {
    $target .= '?location=' . urlencode((string) $_GET['location']);
}
if (empty($_SESSION['user'])) {
    // Not logged in yet: bounce to login first, then the app returns here.
    header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?next=' . urlencode($target));
    exit;
}
header('Location: ' . $target);
exit;
