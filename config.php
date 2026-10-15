<?php
// config.php - session + DB + shared helpers. JSON endpoints must not print HTML here.
// Works on XAMPP, Cloudflare tunnel, AND Vercel (FrankenPHP container).
// On Vercel: set DB_HOST/DB_NAME/DB_USER/DB_PASS env vars to an EXTERNAL MySQL
// (Vercel has no local MySQL). Free options: Aiven / PlanetScale / Railway / InfinityFree remote MySQL.
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos((string)$_SERVER['HTTP_CF_VISITOR'], 'https') !== false) {
    $_SERVER['HTTPS'] = 'on';
}
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $_SERVER['HTTPS'] = 'on';
}
// Vercel serves everything over https behind its proxy.
if (isset($_SERVER['HTTP_X_VERCEL_ID']) || getenv('VERCEL') !== false) {
    $_SERVER['HTTPS'] = 'on';
}

if (!function_exists('e')) { function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('require_login')) { function require_login() { if (empty($_SESSION['user'])) { header('Location: index.php'); exit; } } }
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
function is_index_guest_request($script) {
	return $script === 'index.php'
		&& ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
		&& empty($_GET['logout']);
}
try {
	$db_host = getenv('DB_HOST') ?: 'localhost';
	$db_port = getenv('DB_PORT') ?: '';
	$db_name = getenv('DB_NAME') ?: 'philsurin';
	$db_user = getenv('DB_USER') ?: 'root';
	$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
	$dsn_host = $db_port !== '' ? "{$db_host};port={$db_port}" : $db_host;
	$db = new PDO("mysql:host={$dsn_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
	require_once __DIR__ . '/db-bootstrap.php';
	// Auto-create tables + seed on EVERY host (Vercel/external MySQL has no phpMyAdmin step).
	try {
		if (function_exists('philsurin_bootstrap_db')) philsurin_bootstrap_db($db);
		if (function_exists('philsurin_bootstrap_db_extra')) philsurin_bootstrap_db_extra($db);
	} catch (Throwable $bootstrapError) { error_log('DB bootstrap: ' . $bootstrapError->getMessage()); }
	// DB-backed sessions for Vercel (must be set BEFORE session_start, and needs $db).
	if (session_status() === PHP_SESSION_NONE && strtolower((string)(getenv('SESSION_HANDLER') ?: '')) === 'db') {
		$sdb = $db;
		session_set_save_handler(
			function () { return true; },
			function () { return true; },
			function ($id) use ($sdb) { try { $q=$sdb->prepare('SELECT data FROM php_sessions WHERE id=?'); $q->execute([$id]); $v=$q->fetchColumn(); return $v===false?'':(string)$v; } catch (Throwable $t) { return ''; } },
			function ($id, $data) use ($sdb) { try { $q=$sdb->prepare('REPLACE INTO php_sessions(id,data) VALUES(?,?)'); $q->execute([$id,$data]); return true; } catch (Throwable $t) { return false; } },
			function ($id) use ($sdb) { try { $q=$sdb->prepare('DELETE FROM php_sessions WHERE id=?'); $q->execute([$id]); return true; } catch (Throwable $t) { return false; } },
			function ($max) use ($sdb) { try { $sdb->exec('DELETE FROM php_sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL '.(int)$max.' SECOND)'); return true; } catch (Throwable $t) { return false; } }
		);
	}
} catch (PDOException $ex) {
	error_log('Database connection failed: ' . $ex->getMessage());
	http_response_code(500);
	echo 'Sorry, the application is temporarily unavailable. Please try again later.';
	exit;
}
// Shared helper libraries. Every page and endpoint depends on these being
// present, so load them here once instead of in each entry point.
require_once __DIR__ . '/grading_functions.php';   // normalize_stage_name(), save_validation_rows(), grading_stage_list()
require_once __DIR__ . '/roles.php';               // philsurin_can_encode_stage(), philsurin_require_export(), ...
if (file_exists(__DIR__ . '/location_filters.php')) {
	require_once __DIR__ . '/location_filters.php'; // row_has_data() used by report/export/progress
}
if (session_status() === PHP_SESSION_NONE) {
	session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
	session_start();
}
if (is_index_guest_request($script) && !empty($_SESSION['user']) && strtolower((string) ($_SESSION['user']['role'] ?? 'viewer')) === 'viewer') {
	header('Location: dashboard.php');
	exit;
}
// Page-only assets (never on JSON/POST/export/sync so responses stay clean).
// Assets must never print before a redirect: early output breaks
// require_login() and the logout bounce with "headers already sent".
// Emit them only for signed-in pages, or for the guest login screen itself.
$asset_safe = !isset($_GET['logout']) && (!empty($_SESSION['user']) || $script === 'index.php');
$page_scripts = [
	'index.php' => ['validation-form.js','location-id-preview.js?v=blank-20261001','login-brand.js','password-eye.js'],
	'users.php' => ['password-eye.js'],
	'dashboard.php' => ['dashboard-progress.js?v=pie1'],
];
$asset_pages = ['index.php','dashboard.php','users.php','locations.php','locations_management.php','audit.php','audit_management.php','report.php'];
// JSON / XML / CSV endpoints must never receive page chrome: any HTML printed
// before the body breaks the client's parser (Excel exports, PWA fetch, API).
$asset_excluded = ['report_data.php','validation_data.php','progress_data.php','export.php','import.php','offline-data.php','export_locations.php','sync.php','health.php'];
if ($asset_safe && in_array($script, $asset_pages, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') === false && !in_array($script, $asset_excluded, true)) {
	$theme_v = @filemtime(__DIR__ . '/ios-theme.css') ?: time();
	$oq_v = @filemtime(__DIR__ . '/offline-queue.js') ?: time();
	$sw_v = @filemtime(__DIR__ . '/sw.js') ?: time();
	// Echoed here (before page HTML) so single-file pages like index.php/report.php
	// get offline support without editing each page. Matches previous behavior.
	echo '<link rel="stylesheet" href="ios-theme.css?v='.$theme_v.'">';
	echo '<script src="reportmeta.js" defer></script>';
	echo '<script src="offline-queue.js?v='.$oq_v.'" defer></script>';
	echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("sw.js?v='.$sw_v.'").catch(function(){});});}</script>';
	foreach ($page_scripts[$script] ?? [] as $js) {
		echo '<script src="'.e($js).'" defer></script>';
	}
	function philsurin_page_assets() {} // no-op: assets already printed above
}
// Sidebar is page chrome for the web UI. Guard it so JSON/XML endpoints and the
// /api/ routes never get HTML prepended to their body (that broke client-side
// JSON parsing and Excel exports).
$is_api_request = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
if (!empty($_SESSION['user']) && is_index_guest_request($script) && !$is_api_request && is_file(__DIR__ . '/sidebar.php')) {
	require __DIR__ . '/sidebar.php';
}

