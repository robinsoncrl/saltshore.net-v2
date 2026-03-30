<?php
/**
 * Saltshore Owner Portal — Configuration
 * 
 * Loads deployment-specific config if available (config-deploy.php on production)
 * Falls back to development defaults below
 */

// Check for deployment-specific config first (Spaceship or production server)
if (file_exists(__DIR__ . '/config-deploy.php')) {
    require_once __DIR__ . '/config-deploy.php';
} else {
    // Development/Local defaults
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'saltshore_portal');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('CEO_OWNER_IDENTIFIER', 'CEO1');
}

// Management allowlist (login identifier values that can access Management tab/page)
// Identifier can be owner username (e.g. CEO1) or employee_code (e.g. EMP-001)
if (!defined('MANAGEMENT_ALLOWLIST')) {
    define('MANAGEMENT_ALLOWLIST', ['CEO1', 'Admin']);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Timezone
date_default_timezone_set('America/New_York');

function portal_table_exists(PDO $pdo, string $tableName): bool
{
    static $statement = null;

    if ($statement === null) {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
    }

    $statement->execute([DB_NAME, $tableName]);
    return (int)$statement->fetchColumn() > 0;
}

function portal_execute_schema(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/db/setup.sql');
    if ($sql === false) {
        throw new RuntimeException('Unable to read portal/db/setup.sql');
    }

    $statements = [];
    $buffer = [];

    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $trimmed)) {
            continue;
        }

        $buffer[] = $line;

        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim(implode("\n", $buffer));
            $buffer = [];
        }
    }

    if ($buffer !== []) {
        $statements[] = trim(implode("\n", $buffer));
    }

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }

        $pdo->exec($statement);
    }
}

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
            $bootstrap->exec(
                sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    str_replace('`', '``', DB_NAME)
                )
            );
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
            portal_execute_schema($pdo);
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

if (!portal_table_exists($pdo, 'users')) {
    portal_execute_schema($pdo);
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

$pdo->exec("CREATE TABLE IF NOT EXISTS paystub_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(255) DEFAULT NULL,
    paystub_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_psr_user_status (user_id, status),
    KEY idx_psr_employee (employee_id),
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

$col_check->execute([DB_NAME, 'employees', 'public_bio']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN public_bio TEXT DEFAULT NULL AFTER photo_url");
}

$col_check->execute([DB_NAME, 'employees', 'show_on_about']);
if ((int)$col_check->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN show_on_about TINYINT(1) NOT NULL DEFAULT 0 AFTER public_bio");
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

$current_script = basename((string)($_SERVER['PHP_SELF'] ?? ''));
if (!empty($_SESSION['demo_mode']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $current_script !== 'auth.php') {
    $_SESSION['flash_success'] = 'Demo Mode is read-only in Phase 1. Changes are disabled.';
    header('Location: ' . $current_script);
    exit;
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

function demo_role() {
    return (string)($_SESSION['demo_role'] ?? '');
}

function is_demo_mode() {
    return !empty($_SESSION['demo_mode']);
}

function is_demo_manager() {
    return is_demo_mode() && demo_role() === 'manager';
}

function is_demo_employee() {
    return is_demo_mode() && demo_role() === 'employee';
}

function build_demo_seed_data(string $role): array {
    $labels = ['May 25', 'Jun 25', 'Jul 25', 'Aug 25', 'Sep 25', 'Oct 25', 'Nov 25', 'Dec 25', 'Jan 26', 'Feb 26', 'Mar 26', 'Apr 26'];
    $revenue = [12800, 13450, 14120, 15310, 16040, 15820, 16610, 17200, 18150, 18940, 19720, 20580];
    $expenses = [5200, 5480, 5610, 5940, 6320, 6210, 6480, 6650, 6880, 7020, 7190, 7410];
    $hours = [152, 158, 161, 168, 171, 169, 173, 177, 182, 185, 188, 191];

    $employees = [
        [
            'id' => 501, 
            'full_name' => 'Jordan Hale', 
            'employee_code' => 'EMP-501', 
            'hourly_rate' => 32.50,
            'role_title' => 'Senior Consultant',
            'email' => 'jordan@example.com',
            'phone' => '(555) 234-5678',
            'start_date' => '2024-06-15',
            'pin_active' => 1,
            'login_locked' => 0,
            'status' => 'active',
            'photo_url' => null,
        ],
        [
            'id' => 502, 
            'full_name' => 'Avery Stone', 
            'employee_code' => 'EMP-502', 
            'hourly_rate' => 29.00,
            'role_title' => 'Project Coordinator',
            'email' => 'avery@example.com',
            'phone' => '(555) 345-6789',
            'start_date' => '2024-09-01',
            'pin_active' => 1,
            'login_locked' => 0,
            'status' => 'active',
            'photo_url' => null,
        ],
        [
            'id' => 503, 
            'full_name' => 'Riley Shore', 
            'employee_code' => 'EMP-503', 
            'hourly_rate' => 34.25,
            'role_title' => 'Solutions Architect',
            'email' => 'riley@example.com',
            'phone' => '(555) 456-7890',
            'start_date' => '2024-08-20',
            'pin_active' => 1,
            'login_locked' => 0,
            'status' => 'active',
            'photo_url' => null,
        ],
    ];

    $paystubs = [
        ['id' => 9101, 'employee_id' => 501, 'full_name' => 'Jordan Hale', 'employee_code' => 'EMP-501', 'period_start' => '2026-03-01', 'period_end' => '2026-03-15', 'approved_hours' => 76.5, 'hourly_rate' => 32.50, 'gross_pay' => 2486.25, 'business_revenue' => 9730.00, 'created_at' => '2026-03-16 08:35:00', 'generated_by' => 'DEMO-MANAGER'],
        ['id' => 9102, 'employee_id' => 501, 'full_name' => 'Jordan Hale', 'employee_code' => 'EMP-501', 'period_start' => '2026-03-16', 'period_end' => '2026-03-31', 'approved_hours' => 80.0, 'hourly_rate' => 32.50, 'gross_pay' => 2600.00, 'business_revenue' => 9990.00, 'created_at' => '2026-04-01 09:05:00', 'generated_by' => 'DEMO-MANAGER'],
    ];

    $paystub_requests = [
        ['id' => 301, 'employee_id' => 501, 'full_name' => 'Jordan Hale', 'employee_code' => 'EMP-501', 'period_start' => '2026-03-16', 'period_end' => '2026-03-31', 'notes' => 'Bi-weekly request', 'status' => 'approved', 'reject_reason' => null, 'paystub_id' => 9102, 'created_at' => '2026-04-01 08:15:00'],
        ['id' => 302, 'employee_id' => 501, 'full_name' => 'Jordan Hale', 'employee_code' => 'EMP-501', 'period_start' => '2026-04-01', 'period_end' => '2026-04-15', 'notes' => 'Quarter start payroll', 'status' => 'pending', 'reject_reason' => null, 'paystub_id' => null, 'created_at' => '2026-04-15 07:45:00'],
    ];

    $expense_requests = [
        ['id' => 701, 'request_date' => '2026-03-20', 'expense_type' => 'Fuel', 'amount' => 68.42, 'receipt_reference' => 'RCPT-10092', 'notes' => 'Client visit mileage', 'status' => 'approved', 'reviewed_by' => 'DEMO-MANAGER'],
        ['id' => 702, 'request_date' => '2026-04-03', 'expense_type' => 'Supplies', 'amount' => 119.90, 'receipt_reference' => 'RCPT-10131', 'notes' => 'Printer and paper', 'status' => 'pending', 'reviewed_by' => null],
    ];

    $recent_transactions = [
        ['date' => '2026-04-12', 'description' => 'Invoice #A-118 Paid', 'category' => 'Invoice Income', 'amount' => 4860.00, 'reconciled' => 1],
        ['date' => '2026-04-11', 'description' => 'Software Licenses', 'category' => 'Operations', 'amount' => -249.00, 'reconciled' => 1],
        ['date' => '2026-04-09', 'description' => 'Merchant Fees', 'category' => 'Banking', 'amount' => -87.32, 'reconciled' => 0],
    ];

    $recent_blocks = [
        ['start_time' => '2026-04-12 08:05:00', 'end_time' => '2026-04-12 11:20:00', 'duration_seconds' => 11700, 'category' => 'Client Delivery'],
        ['start_time' => '2026-04-11 13:00:00', 'end_time' => '2026-04-11 17:10:00', 'duration_seconds' => 15000, 'category' => 'Implementation'],
        ['start_time' => '2026-04-10 09:10:00', 'end_time' => '2026-04-10 12:30:00', 'duration_seconds' => 12000, 'category' => 'Planning'],
    ];

    return [
        'role' => $role,
        'labels' => $labels,
        'revenue' => $revenue,
        'expenses' => $expenses,
        'hours' => $hours,
        'employees' => $employees,
        'paystubs' => $paystubs,
        'paystub_requests' => $paystub_requests,
        'expense_requests' => $expense_requests,
        'recent_transactions' => $recent_transactions,
        'recent_blocks' => $recent_blocks,
        'expense_categories' => [
            ['category' => 'Payroll', 'total' => 4200],
            ['category' => 'Software', 'total' => 980],
            ['category' => 'Operations', 'total' => 760],
            ['category' => 'Marketing', 'total' => 540],
        ],
    ];
}

function get_demo_seed_data(): array {
    if (!is_demo_mode()) {
        return [];
    }
    if (empty($_SESSION['demo_seed']) || !is_array($_SESSION['demo_seed'])) {
        $_SESSION['demo_seed'] = build_demo_seed_data(demo_role());
    }
    return $_SESSION['demo_seed'];
}

function is_employee_login() {
    return (($_SESSION['login_role'] ?? '') === 'employee');
}

function is_owner_login() {
    return (($_SESSION['login_role'] ?? '') === 'owner');
}

function is_management_user() {
    if (is_demo_manager()) {
        return true;
    }
    if (is_demo_employee()) {
        return false;
    }
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
