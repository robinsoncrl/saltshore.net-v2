<?php
/**
 * Saltshore Owner Portal — Configuration
 */

// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'saltshore_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// Management allowlist (login identifier values that can access Management tab/page)
// Identifier can be owner username (e.g. CEO1) or employee_code (e.g. EMP-001)
define('MANAGEMENT_ALLOWLIST', ['CEO1', 'Admin']);
define('CEO_OWNER_IDENTIFIER', 'CEO1');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Timezone
date_default_timezone_set('America/New_York');

// Connect to database
// First try without a dbname to detect if the database needs to be created
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Database doesn't exist yet — try to create it automatically
    if ($e->getCode() == 1049) {
        try {
            $bootstrap = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $sql = file_get_contents(__DIR__ . '/db/setup.sql');
            // Execute each statement (split on ";")
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $bootstrap->exec($statement);
                }
            }
            unset($bootstrap);
            // Reconnect now that the DB exists
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $setup_err) {
            http_response_code(500);
            die('
<!DOCTYPE html><html><head>
<title>Setup Required — Saltshore Portal</title>
<style>
body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#F7F9FB;}
.box{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:40px;max-width:500px;text-align:center;}
h2{color:#0A1A2F;margin-bottom:16px;}
p{color:#555;line-height:1.6;}
code{background:#f3f4f6;padding:3px 8px;border-radius:4px;font-size:0.9rem;}
pre{background:#f3f4f6;padding:16px;border-radius:6px;text-align:left;font-size:0.85rem;overflow:auto;}
.err{color:#991B1B;background:#FEE2E2;padding:12px;border-radius:6px;margin:16px 0;font-size:0.9rem;}
</style>
</head><body>
<div class="box">
    <h2>Database Setup Required</h2>
    <p>The portal database could not be created automatically.</p>
    <div class="err">' . htmlspecialchars($setup_err->getMessage()) . '</div>
    <p>Please run the setup script manually in <strong>phpMyAdmin</strong> or via MySQL CLI:</p>
    <pre>mysql -u root -p &lt; portal/db/setup.sql</pre>
    <p>Then reload this page.</p>
</div>
</body></html>');
        }
    } else {
        http_response_code(500);
        die('
<!DOCTYPE html><html><head>
<title>Connection Error — Saltshore Portal</title>
<style>
body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#F7F9FB;}
.box{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:40px;max-width:500px;text-align:center;}
h2{color:#0A1A2F;} p{color:#555;line-height:1.6;}
.err{color:#991B1B;background:#FEE2E2;padding:12px;border-radius:6px;margin:16px 0;font-size:0.9rem;}
</style>
</head><body>
<div class="box">
    <h2>Database Connection Error</h2>
    <div class="err">' . htmlspecialchars($e->getMessage()) . '</div>
    <p>Check that XAMPP MySQL is running and that <code>config.php</code> credentials are correct.</p>
</div>
</body></html>');
    }
}

// Ensure newer portal tables exist for upgraded installs.
$pdo->exec("CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    employee_code VARCHAR(40) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    role_title VARCHAR(100) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    hourly_rate DECIMAL(10,2) DEFAULT NULL,
    pin_hash VARCHAR(255) DEFAULT NULL,
    pin_active TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_code (employee_code),
    KEY idx_emp_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS work_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    schedule_date DATE NOT NULL,
    shift_start TIME NOT NULL,
    shift_end TIME NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sched_user_date (user_id, schedule_date),
    KEY idx_sched_employee (employee_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS expense_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    request_date DATE NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    receipt_reference VARCHAR(255) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by VARCHAR(80) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expense_user_status (user_id, status),
    KEY idx_expense_employee (employee_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS paystubs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    approved_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    gross_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
    business_revenue DECIMAL(10,2) NOT NULL DEFAULT 0,
    generated_by VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_paystub_user_employee (user_id, employee_id),
    KEY idx_paystub_period (period_start, period_end),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// Backfill credential columns/index for older installs.
$col_check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
$idx_check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?");

$col_check->execute([DB_NAME, 'employees', 'employee_code']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN employee_code VARCHAR(40) DEFAULT NULL AFTER full_name");
}

$col_check->execute([DB_NAME, 'employees', 'pin_hash']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN pin_hash VARCHAR(255) DEFAULT NULL AFTER hourly_rate");
}

$col_check->execute([DB_NAME, 'employees', 'pin_active']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN pin_active TINYINT(1) NOT NULL DEFAULT 0 AFTER pin_hash");
}

$col_check->execute([DB_NAME, 'employees', 'photo_url']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN photo_url VARCHAR(255) DEFAULT NULL AFTER hourly_rate");
}

$col_check->execute([DB_NAME, 'employees', 'address_line1']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN address_line1 VARCHAR(255) DEFAULT NULL AFTER phone");
}

$col_check->execute([DB_NAME, 'employees', 'city_state_zip']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN city_state_zip VARCHAR(255) DEFAULT NULL AFTER address_line1");
}

$col_check->execute([DB_NAME, 'employees', 'emergency_contact']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL AFTER city_state_zip");
}

$col_check->execute([DB_NAME, 'employees', 'login_locked']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN login_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER pin_active");
}

$col_check->execute([DB_NAME, 'time_blocks', 'employee_id']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE time_blocks ADD COLUMN employee_id INT UNSIGNED DEFAULT NULL AFTER user_id");
}

$col_check->execute([DB_NAME, 'time_blocks', 'submitted_for_approval']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE time_blocks ADD COLUMN submitted_for_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER is_billable");
}

$col_check->execute([DB_NAME, 'time_blocks', 'is_approved']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE time_blocks ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted_for_approval");
}

$col_check->execute([DB_NAME, 'time_blocks', 'approved_at']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE time_blocks ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER is_approved");
}

$col_check->execute([DB_NAME, 'time_blocks', 'approved_by']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE time_blocks ADD COLUMN approved_by VARCHAR(80) DEFAULT NULL AFTER approved_at");
}

$idx_check->execute([DB_NAME, 'time_blocks', 'idx_time_blocks_employee']);
if ((int)$idx_check->fetchColumn() === 0) {
    $pdo->exec("CREATE INDEX idx_time_blocks_employee ON time_blocks (employee_id, start_time)");
}

$idx_check->execute([DB_NAME, 'employees', 'uq_employee_code']);
if ((int)$idx_check->fetchColumn() === 0) {
    $pdo->exec("CREATE UNIQUE INDEX uq_employee_code ON employees (employee_code)");
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper: verify CSRF token
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper: require authentication
function require_auth() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function current_login_identifier() {
    if (!empty($_SESSION['employee_code'])) {
        return strtoupper(trim((string)$_SESSION['employee_code']));
    }
    return strtoupper(trim((string)($_SESSION['username'] ?? '')));
}

function is_employee_login() {
    return (($_SESSION['login_role'] ?? '') === 'employee');
}

function is_owner_login() {
    return (($_SESSION['login_role'] ?? '') === 'owner');
}

function is_management_user() {
    $identifier = current_login_identifier();
    $allow = array_map('strtoupper', MANAGEMENT_ALLOWLIST);
    return in_array($identifier, $allow, true);
}

function is_ceo_owner() {
    return is_owner_login() && (
        current_login_identifier() === strtoupper(CEO_OWNER_IDENTIFIER) ||
        is_management_user()
    );
}

function deny_access_to_calgen() {
    $_SESSION['flash_success'] = 'Access restricted for your role.';
    header('Location: calgen.php');
    exit;
}

// Helper: format currency
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

// Helper: format duration (seconds to H:MM:SS)
function format_duration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
}
