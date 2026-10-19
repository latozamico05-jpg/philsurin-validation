<?php
// db-bootstrap.php - zero-SQL setup for Vercel/external MySQL.
// Creates core tables IF NOT EXISTS + seeds admin/viewer + varieties.
if (!function_exists('philsurin_bootstrap_db')) {
function philsurin_bootstrap_db(PDO $db): array {
$ran = [];
$driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
$is_sqlite = $driver === 'sqlite';
if ($is_sqlite) {
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL DEFAULT 'viewer', active INTEGER NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS varieties (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, role_code VARCHAR(50) NOT NULL UNIQUE, role_name VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, role_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS grading_records (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, location_id VARCHAR(100) NOT NULL, project_location VARCHAR(150) NOT NULL, date_planted DATE NULL, dr_dp DATE NULL, control_name VARCHAR(100), grading_stage VARCHAR(30) NOT NULL, variety_id INTEGER NOT NULL, location_size VARCHAR(50), grading_date DATE NOT NULL, replicate INTEGER NOT NULL, germination REAL NULL, es_value REAL NULL, gs_value REAL NULL, remarks TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_grading_location ON grading_records(location_id)");
    $db->exec("CREATE TABLE IF NOT EXISTS hidden_trial_locations (location_id VARCHAR(100) PRIMARY KEY, hidden_by INTEGER NULL, hidden_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
} else {
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL DEFAULT 'viewer', active TINYINT(1) NOT NULL DEFAULT 1, status ENUM('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS varieties (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS roles (role_id INT AUTO_INCREMENT PRIMARY KEY, role_code VARCHAR(50) NOT NULL UNIQUE, role_name VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (user_id INT NOT NULL, role_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, role_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS grading_records (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, location_id VARCHAR(100) NOT NULL, project_location VARCHAR(150) NOT NULL, date_planted DATE NULL, dr_dp DATE NULL, control_name VARCHAR(100), grading_stage VARCHAR(30) NOT NULL, variety_id INT NOT NULL, location_size VARCHAR(50), grading_date DATE NOT NULL, replicate INT NOT NULL, germination DECIMAL(10,2) NULL, es_value DECIMAL(10,2) NULL, gs_value DECIMAL(10,2) NULL, remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_grading_location(location_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS hidden_trial_locations (location_id VARCHAR(100) PRIMARY KEY, hidden_by INT NULL, hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}
$ran[]='users';$ran[]='varieties';$ran[]='roles';$ran[]='user_roles';$ran[]='grading_records';$ran[]='hidden';
return $ran;
}
}
if (!function_exists('philsurin_bootstrap_db_extra')) {
function philsurin_bootstrap_db_extra(PDO $db): array {
$ran = [];
$driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
$is_sqlite = $driver === 'sqlite';
if ($is_sqlite) {
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NULL, entity_id VARCHAR(100) NULL, old_data TEXT NULL, new_data TEXT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at)");
    $db->exec("CREATE TABLE IF NOT EXISTS api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token_hash VARCHAR(128) NOT NULL UNIQUE, name VARCHAR(100) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS system_config (config_key VARCHAR(100) PRIMARY KEY, config_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS php_sessions (id VARCHAR(128) PRIMARY KEY, data MEDIUMTEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
} else {
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NULL, entity_id VARCHAR(100) NULL, old_data JSON NULL, new_data JSON NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_audit_created(created_at))");
    $db->exec("CREATE TABLE IF NOT EXISTS api_tokens (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(128) NOT NULL UNIQUE, name VARCHAR(100) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS system_config (config_key VARCHAR(100) PRIMARY KEY, config_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS php_sessions (id VARCHAR(128) PRIMARY KEY, data MEDIUMTEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
}
$ran[]='audit_logs';$ran[]='api_tokens';$ran[]='system_config';$ran[]='php_sessions';
try {
if ((int)$db->query('SELECT COUNT(*) FROM roles')->fetchColumn()===0) {
$db->exec("INSERT INTO roles(role_code,role_name) VALUES ('ADMIN','Administrator'),('VIEWER','Viewer'),('VALIDATOR','Validator'),('VALIDATOR_GERMINATION','Germination Validator'),('VALIDATOR_FIELD','Field Validator'),('VALIDATOR_HARVEST','Harvest Validator')");
$ran[]='seed:roles';
}} catch (Throwable $t) {}
try {
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===0) {
$q=$db->prepare('INSERT INTO users(username,password_hash,role,active,status) VALUES(?,?,?,1,?)');
$q->execute(['admin',password_hash('admin123',PASSWORD_DEFAULT),'admin','ACTIVE']);
$q->execute(['viewer',password_hash('viewer123',PASSWORD_DEFAULT),'viewer','ACTIVE']);
$ran[]='seed:users';
}} catch (Throwable $t) {}
try {
if ((int)$db->query('SELECT COUNT(*) FROM varieties')->fetchColumn()===0) {
$vals=[]; for($i=1;$i<=19;$i++){ $c='PS-'.str_pad((string)$i,2,'0',STR_PAD_LEFT); $vals[]="('{$c}','Sugarcane Variety {$i}',1)"; }
$db->exec('INSERT INTO varieties(code,name,active) VALUES '.implode(',',$vals));
$ran[]='seed:varieties';
}} catch (Throwable $t) {}
try {
if ($is_sqlite) {
    $db->exec("INSERT OR IGNORE INTO system_config(config_key,config_value) VALUES ('VALIDATION_TARGET','1200')");
} else {
    $db->exec("INSERT IGNORE INTO system_config(config_key,config_value) VALUES ('VALIDATION_TARGET','1200')");
}
$ran[]='seed:config';
} catch (Throwable $t) {}
return $ran;
}
}

