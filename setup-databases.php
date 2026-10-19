<?php
// setup-databases.php — Run database/schema.sql + database/seed.sql
// Works with MySQL or SQLite fallback.
require 'config.php';
require 'db-offline.php';

header('Content-Type: text/plain; charset=utf-8');

$driver = strtolower((string)($GLOBALS['DB_DRIVER'] ?? 'mysql'));
$is_sqlite = $driver === 'sqlite';

echo "========================================\n";
echo " PHILSURIN Database Setup\n";
echo "========================================\n";
echo "Driver: " . ($is_sqlite ? 'SQLite (offline fallback)' : 'MySQL') . "\n";
echo "========================================\n\n";

function run_sql_file_mysql(PDO $db, string $path): array {
    $sql = file_get_contents($path);
    if ($sql === false) {
        return ['ok' => false, 'error' => "Cannot read $path"];
    }
    $statements = array_map('trim', preg_split('/;\s*\n/', $sql));
    $ran = 0;
    $errors = [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '#')) {
            continue;
        }
        if (stripos($stmt, 'CREATE DATABASE') === 0 || stripos($stmt, 'USE ') === 0) {
            continue;
        }
        try {
            $db->exec($stmt);
            $ran++;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'exists') !== false || stripos($msg, 'Duplicate') !== false) {
                continue;
            }
            $errors[] = "$msg :: " . substr($stmt, 0, 80);
        }
    }
    return ['ok' => empty($errors), 'ran' => $ran, 'errors' => $errors];
}

function run_sql_file_sqlite(PDO $db, string $path): array {
    $sql = file_get_contents($path);
    if ($sql === false) {
        return ['ok' => false, 'error' => "Cannot read $path"];
    }
    $statements = array_map('trim', preg_split('/;\s*\n/', $sql));
    $ran = 0;
    $errors = [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '#')) {
            continue;
        }
        if (stripos($stmt, 'CREATE DATABASE') === 0 || stripos($stmt, 'USE ') === 0) {
            continue;
        }
        $s = $stmt;
        $s = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $s);
        $s = preg_replace('/\bBIGINT\b/i', 'INTEGER', $s);
        $s = preg_replace('/\bINT\b/i', 'INTEGER', $s);
        $s = preg_replace('/\bTINYINT\(1\)\b/i', 'INTEGER', $s);
        $s = preg_replace("/\bENUM\('[^']*'\)\b/i", 'TEXT', $s);
        $s = preg_replace('/\bTIMESTAMP\b/i', 'DATETIME', $s);
        $s = preg_replace("/ DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/i", '', $s);
        $s = preg_replace('/\bCHAR\(\d+\)\b/i', 'TEXT', $s);
        $s = preg_replace('/\bVARCHAR\(\d+\)\b/i', 'TEXT', $s);
        $s = preg_replace('/\bDECIMAL\(\d+,\d+\)\b/i', 'REAL', $s);
        try {
            $db->exec($s);
            $ran++;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'exists') !== false || stripos($msg, 'Duplicate') !== false) {
                continue;
            }
            $errors[] = "$msg :: " . substr($stmt, 0, 80);
        }
    }
    return ['ok' => empty($errors), 'ran' => $ran, 'errors' => $errors];
}
if ($is_sqlite) {
    echo "Using SQLite offline database...\n";
    $db = philsurin_offline_open();

    echo "\n--- Creating extended schema tables (SQLite) ---\n";
    philsurin_offline_schema_extended($db);
    echo "Extended tables created/verified.\n";

    echo "\n--- Seeding roles ---\n";
    try {
        $db->exec("INSERT OR IGNORE INTO roles(role_code,role_name,description) VALUES
            ('ADMIN','Administrator','Full system administration'),
            ('VIEWER','Viewer','Read-only authorized access'),
            ('VALIDATOR_GERMINATION','Germination Validator','Can submit germination validation'),
            ('VALIDATOR_FIELD','Field Validator','Can submit field grading validation'),
            ('VALIDATOR_HARVEST','Harvest Validator','Can submit harvest records')");
        echo "Roles seeded.\n";
    } catch (Throwable $e) { echo "Role seed warning: " . $e->getMessage() . "\n"; }

    echo "\n--- Seeding varieties ---\n";
    try {
        $varieties = [
            'VMC 84-524','Phil 99-1793','PSR 13-2121','PSR 13-2189','PSR 13-2237',
            'PSR 13-2298','PSR 13-2321','PSR 13-2369','PSR 16-129','PSR 16-175',
            'PSR 16-264','PSR 16-267','PSR 16-45','PSR 16-460','PSR 16-59',
            'PSR 17-06','PSR 17-116','PSR 17-178','PSR 17-191'
        ];
        foreach ($varieties as $v) {
            $db->exec("INSERT OR IGNORE INTO varieties(code,name,active) VALUES ('$v','$v',1)");
        }
        echo count($varieties) . " varieties seeded.\n";
    } catch (Throwable $e) { echo "Variety seed warning: " . $e->getMessage() . "\n"; }

    echo "\n--- Seeding measurement_definitions ---\n";
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
            $db->exec("INSERT OR IGNORE INTO measurement_definitions(code,stage,label,data_type,required,active,sort_order) VALUES
                ('" . implode("','", $m) . "')");
        }
        echo count($mdefs) . " measurement definitions seeded.\n";
    } catch (Throwable $e) { echo "Measurement def seed warning: " . $e->getMessage() . "\n"; }

    echo "\n--- Seeding system_config ---\n";
    try {
        $db->exec("INSERT OR IGNORE INTO system_config(config_key,config_value,description) VALUES
            ('VALIDATION_TARGET','1200','Dashboard validation target'),
            ('DEFAULT_REPLICATES','4','Number of raw replicates'),
            ('ACTIVE_C45_MODEL',NULL,'Active production model'),
            ('C45_MIN_CONFIDENCE','0.70','Minimum confidence')");
        echo "System config seeded.\n";
    } catch (Throwable $e) { echo "Config seed warning: " . $e->getMessage() . "\n"; }

    echo "\n--- Seeding users (admin/viewer) ---\n";
    try {
        $db->exec("INSERT OR IGNORE INTO users(username,password_hash,role,active,status) VALUES
            ('admin','" . password_hash('admin123', PASSWORD_DEFAULT) . "','admin',1,'ACTIVE'),
             ('viewer','" . password_hash('viewer123', PASSWORD_DEFAULT) . "','viewer',1,'ACTIVE')");
        echo "Users seeded.\n";
    } catch (Throwable $e) { echo "User seed warning: " . $e->getMessage() . "\n"; }

} else {
    echo "Using MySQL database...\n";
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        echo "ERROR: No MySQL database connection available.\n";
        exit(1);
    }

    echo "\n--- Running database/schema.sql ---\n";
    $r1 = run_sql_file_mysql($db, __DIR__ . '/database/schema.sql');
    echo "Ran {$r1['ran']} statements" . (!empty($r1['errors']) ? " with " . count($r1['errors']) . " error(s)" : "") . "\n";
    foreach ($r1['errors'] as $e) { echo "  ERROR: $e\n"; }

    echo "\n--- Running database/seed.sql ---\n";
    $r2 = run_sql_file_mysql($db, __DIR__ . '/database/seed.sql');
    echo "Ran {$r2['ran']} statements" . (!empty($r2['errors']) ? " with " . count($r2['errors']) . " error(s)" : "") . "\n";
    foreach ($r2['errors'] as $e) { echo "  ERROR: $e\n"; }
}

echo "\n========================================\n";
echo " Setup complete.\n";
echo "========================================\n";
echo "Tables now available in " . ($is_sqlite ? 'data/philsurin-offline.sqlite' : 'MySQL philsurin') . "\n";
echo "You can now access dashboard.php for live analytics.\n";
echo "If MySQL was down, data is stored in SQLite and will sync when MySQL returns.\n";