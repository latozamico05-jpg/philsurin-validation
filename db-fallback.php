<?php
// db-fallback.php — ALTERNATIVE DATABASE when MySQL/XAMPP is down.
// Tries MySQL first. If refused, opens local SQLite data/philsurin-fallback.sqlite
// with the SAME core tables so the app stays usable instead of 500/ECONNREFUSED.
// In config.php: require this file, then $db = philsurin_open_db();
function philsurin_sqlite_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL DEFAULT 'viewer', active INTEGER NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS varieties (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, role_code VARCHAR(50) NOT NULL UNIQUE, role_name VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, role_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS grading_records (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, location_id VARCHAR(100) NOT NULL, project_location VARCHAR(150) NOT NULL, date_planted DATE NULL, dr_dp DATE NULL, control_name VARCHAR(100), grading_stage VARCHAR(30) NOT NULL, variety_id INTEGER NOT NULL, location_size VARCHAR(50), grading_date DATE NOT NULL, replicate INTEGER NOT NULL, germination REAL NULL, es_value REAL NULL, gs_value REAL NULL, remarks TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_grading_location ON grading_records(location_id)");
    $db->exec("CREATE TABLE IF NOT EXISTS hidden_trial_locations (location_id VARCHAR(100) PRIMARY KEY, hidden_by INTEGER NULL, hidden_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NULL, entity_id VARCHAR(100) NULL, old_data TEXT NULL, new_data TEXT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token_hash VARCHAR(128) NOT NULL UNIQUE, name VARCHAR(100) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS system_config (config_key VARCHAR(100) PRIMARY KEY, config_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS php_sessions (id VARCHAR(128) PRIMARY KEY, data MEDIUMTEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    /* Extended dashboard tables — same logical schema as database/schema.sql */
    $db->exec("CREATE TABLE IF NOT EXISTS locations (location_id INTEGER PRIMARY KEY AUTOINCREMENT, location_code TEXT NOT NULL UNIQUE, project_name TEXT NOT NULL, farm_field_address TEXT, city_municipality TEXT, province TEXT, latitude REAL, longitude REAL, status TEXT NOT NULL DEFAULT 'ACTIVE', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS user_locations (user_id INTEGER NOT NULL, location_id INTEGER NOT NULL, assigned_by INTEGER NULL, assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, location_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS measurement_definitions (code TEXT PRIMARY KEY, stage TEXT NOT NULL, label TEXT NOT NULL, data_type TEXT NOT NULL, required INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS masterlist (masterlist_id INTEGER PRIMARY KEY AUTOINCREMENT, record_id TEXT NOT NULL UNIQUE, project_name TEXT NOT NULL, location_id INTEGER NULL, variety_id INTEGER NULL, dr_number TEXT, date_gathered TEXT, grading_35mar_date TEXT, grading_4mar_date TEXT, grading_6mar_date TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS validation_sessions (validation_session_id INTEGER PRIMARY KEY AUTOINCREMENT, masterlist_id INTEGER NOT NULL, validator_id INTEGER NULL, status TEXT NOT NULL DEFAULT 'PENDING', notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS dashboard_daily_metrics (metric_date TEXT PRIMARY KEY, total_masterlist INTEGER NOT NULL DEFAULT 0, validated_records INTEGER NOT NULL DEFAULT 0, pending_records INTEGER NOT NULL DEFAULT 0, recommendations INTEGER NOT NULL DEFAULT 0, grading_01_completed INTEGER NOT NULL DEFAULT 0, grading_02_completed INTEGER NOT NULL DEFAULT 0, grading_03_completed INTEGER NOT NULL DEFAULT 0, grading_04_completed INTEGER NOT NULL DEFAULT 0, harvest_completed INTEGER NOT NULL DEFAULT 0, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS dashboard_monthly_metrics (year_month TEXT PRIMARY KEY, total_records INTEGER NOT NULL DEFAULT 0, validated_records INTEGER NOT NULL DEFAULT 0, pending_records INTEGER NOT NULL DEFAULT 0, recommendations INTEGER NOT NULL DEFAULT 0, completion_percent REAL NOT NULL DEFAULT 0, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_masterlist_record_id ON masterlist(record_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_masterlist_location ON masterlist(location_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_validation_sessions_masterlist ON validation_sessions(masterlist_id)");
}

function philsurin_fallback_seed(PDO $db): void {
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 0) {
            $db->exec("INSERT INTO roles(role_code,role_name) VALUES ('ADMIN','Administrator'),('VIEWER','Viewer'),('VALIDATOR','Validator'),('VALIDATOR_GERMINATION','Germination Validator'),('VALIDATOR_FIELD','Field Validator'),('VALIDATOR_HARVEST','Harvest Validator')");
        }
    } catch (Throwable $t) {}
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            $q = $db->prepare('INSERT INTO users(username,password_hash,role,active,status) VALUES(?,?,?,1,?)');
            $q->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'ACTIVE']);
            $q->execute(['viewer', password_hash('viewer123', PASSWORD_DEFAULT), 'viewer', 'ACTIVE']);
        }
    } catch (Throwable $t) {}
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM varieties')->fetchColumn() === 0) {
            $vals = [];
            for ($i = 1; $i <= 19; $i++) { $c = 'PS-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT); $vals[] = "('{$c}','Sugarcane Variety {$i}',1)"; }
            $db->exec('INSERT INTO varieties(code,name,active) VALUES ' . implode(',', $vals));
        }
    } catch (Throwable $t) {}
    try { $db->exec("INSERT OR IGNORE INTO system_config(config_key,config_value) VALUES ('VALIDATION_TARGET','1200')"); } catch (Throwable $t) {}
    try {
        $mdefs = [
            ['GERMINATION','01_GRADING','Germination','NUMBER',1,1,1],
            ['E_S','01_GRADING','E-S','NUMBER',1,1,2],
            ['GS','01_GRADING','G-S','NUMBER',1,1,3],
            ['REMARKS_01','01_GRADING','Remarks','TEXT',0,1,4],
            ['UNIFORMITY','02_GRADING','Uniformity','NUMBER',0,1,1],
            ['POPULATION_02','02_GRADING','Population','NUMBER',0,1,2],
            ['HEIGHT_02','02_GRADING','Height','NUMBER',0,1,3],
            ['SIZE_02','02_GRADING','Size','NUMBER',0,1,4],
            ['GS_02','02_GRADING','GS','TEXT',0,1,5],
            ['REMARKS_02','02_GRADING','Remarks','TEXT',0,1,6],
            ['POPULATION_03','03_GRADING','Population','NUMBER',0,1,1],
            ['HEIGHT_03','03_GRADING','Height','NUMBER',0,1,2],
            ['SIZE_03','03_GRADING','Size','NUMBER',0,1,3],
            ['ERECTNESS_03','03_GRADING','Erectness','TEXT',0,1,4],
            ['GS_03','03_GRADING','GS','TEXT',0,1,5],
            ['REMARKS_03','03_GRADING','Remarks','TEXT',0,1,6],
            ['POPULATION_04','04_GRADING','Population','NUMBER',0,1,1],
            ['HEIGHT_04','04_GRADING','Height','NUMBER',0,1,2],
            ['SIZE_04','04_GRADING','Size','NUMBER',0,1,3],
            ['ERECTNESS_04','04_GRADING','Erectness','TEXT',0,1,4],
            ['GS_04','04_GRADING','GS','TEXT',0,1,5],
            ['REMARKS_04','04_GRADING','Remarks','TEXT',0,1,6],
            ['OBSERVATION','HARVEST','Observation','TEXT',0,1,1],
        ];
        foreach ($mdefs as $m) {
            $db->exec("INSERT OR IGNORE INTO measurement_definitions(code,stage,label,data_type,required,active,sort_order) VALUES ('" . implode("','", $m) . "')");
        }
    } catch (Throwable $t) {}
}
function philsurin_open_db(): PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '';
    $name = getenv('DB_NAME') ?: 'philsurin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $dsn_host = $port !== '' ? "{$host};port={$port}" : $host;
    try {
        $db = new PDO("mysql:host={$dsn_host};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->query('SELECT 1');
        $GLOBALS['DB_DRIVER'] = 'mysql';
        return $db;
    } catch (Throwable $mysqlError) {
        error_log('MySQL down, SQLite fallback active: ' . $mysqlError->getMessage());
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $db = new PDO('sqlite:' . $dir . '/philsurin-fallback.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        philsurin_sqlite_schema($db);
        philsurin_fallback_seed($db);
        $GLOBALS['DB_DRIVER'] = 'sqlite';
        return $db;
    }
}
function philsurin_db_driver(?PDO $db = null): string {
    if (isset($GLOBALS['DB_DRIVER'])) return (string)$GLOBALS['DB_DRIVER'];
    try { return (string)($db ? $db->getAttribute(PDO::ATTR_DRIVER_NAME) : 'mysql'); }
    catch (Throwable $t) { return 'mysql'; }
}
