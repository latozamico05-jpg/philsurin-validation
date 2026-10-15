<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

// --- Expo Go bridge: CORS so a phone app (Expo Go / browser on another
// device) can call this API. Token auth via Authorization: Bearer header. ---
$expoOrigin = (string) getenv('EXPO_ORIGIN');
if (($expoOrigin === '' || $expoOrigin === '*') && isset($_SERVER['HTTP_ORIGIN'])) {
    $expoOrigin = (string) $_SERVER['HTTP_ORIGIN'];
}
if ($expoOrigin === '') {
    $expoOrigin = '*';
}
header('Access-Control-Allow-Origin: ' . $expoOrigin);
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
$db->exec("CREATE TABLE IF NOT EXISTS api_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(128) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL DEFAULT 'expo-app', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_used_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");

function api_response(array $data, int $status = 200): void {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function api_success($data = []): void { api_response(['success'=>true,'data'=>$data]); }
function api_error(string $code, string $message, int $status = 400): void { api_response(['success'=>false,'error'=>['code'=>$code,'message'=>$message]], $status); }
function api_body(): array { $raw=file_get_contents('php://input'); $data=json_decode($raw ?: '{}', true); return is_array($data) ? $data : []; }
function api_bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                $header = (string) $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
        return trim($m[1]);
    }
    return '';
}
function api_user(): array {
    global $db;
    // 1) Expo app token (works from any device, incl. Expo Go).
    $bearer = api_bearer_token();
    if ($bearer !== '') {
        $hash = hash('sha256', $bearer);
        $q = $db->prepare('SELECT u.id,u.username,u.active,u.role FROM api_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND u.active=1');
        $q->execute([$hash]);
        $user = $q->fetch();
        if ($user) {
            $db->prepare('UPDATE api_tokens SET last_used_at=NOW() WHERE token_hash=?')->execute([$hash]);
            $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
            return $user;
        }
        api_error('UNAUTHORIZED', 'Invalid or expired app token. Log in again.', 401);
    }
    // 2) Same-device PHP session (desktop browser + mobile.php).
    if (empty($_SESSION['user']['id'])) api_error('UNAUTHORIZED','Authentication required.',401);
    global $db; $q=$db->prepare('SELECT id,username,active,role FROM users WHERE id=?'); $q->execute([$_SESSION['user']['id']]); $user=$q->fetch();
    if (!$user || !$user['active']) api_error('UNAUTHORIZED','User account is inactive.',401);
    return $user;
}
function api_require_role(array $roles): array { $user=api_user(); $actual=array_filter(array_merge([(string)($user['role'] ?? '')], explode(',',(string)($user['roles'] ?? '')))); if (!array_intersect($roles,$actual)) api_error('FORBIDDEN','You do not have permission to perform this action.',403); return $user; }
function audit_log(?int $userId,string $action,string $entityType='',string $entityId='',?array $old=null,?array $new=null): void { global $db; $q=$db->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)'); $q->execute([$userId,$action,$entityType,$entityId,$old?json_encode($old):null,$new?json_encode($new):null,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]); }
function can_access_location(int $userId, int $locationId): bool { global $db; $q=$db->prepare("SELECT 1 FROM users u WHERE u.id=? AND (LOWER(u.role)='admin' OR EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.role_id=ur.role_id WHERE ur.user_id=u.id AND r.role_code='ADMIN') OR EXISTS(SELECT 1 FROM user_locations ul WHERE ul.user_id=u.id AND ul.location_id=?))"); $q->execute([$userId,$locationId]); return (bool)$q->fetchColumn(); }
