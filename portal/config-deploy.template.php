<?php
/**
 * Saltshore Portal Configuration — Spaceship Deployment Template
 * 
 * IMPORTANT:
 * 1. Copy this to portal/config-deploy.php on your Spaceship server
 * 2. Replace placeholder values with actual Spaceship credentials
 * 3. Do NOT commit config-deploy.php to version control
 * 4. Keep this file ONLY on the server, not in git
 * 5. Restrict file permissions: chmod 600 config-deploy.php
 */

// =============================
// DATABASE CREDENTIALS (from Spaceship)
// =============================
// Get these from Spaceship cPanel → Databases

define('DB_HOST', 'localhost');              // Often 'localhost' on Spaceship; ask support if unclear
define('DB_PORT', 3306);                     // Standard MySQL port
define('DB_USER', 'your_db_user_here');      // Create this user in Spaceship cPanel
define('DB_PASS', 'your_db_password_here');  // Use a strong password
define('DB_NAME', 'your_db_name_here');      // e.g., saltshore_v2 or accountname_v2

// =============================
// SESSION & SECURITY
// =============================

// Session storage path (must exist and be writable)
session_save_path(__DIR__ . '/tmp/sessions');

// Set timezone
date_default_timezone_set('UTC');

// Session cookie settings
ini_set('session.cookie_secure', 1);      // HTTPS only (recommended on production)
ini_set('session.cookie_httponly', 1);    // Prevent JavaScript access
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection

// =============================
// APPLICATION CONSTANTS
// =============================

// Owner identifier (used for role checks)
define('CEO_OWNER_IDENTIFIER', 'CEO1');

// Your live domain (include protocol)
define('SITE_URL', 'https://yourdomain.com');

// Portal subdomain (if using separate subdomain)
define('PORTAL_URL', 'https://yourdomain.com/portal');
// or: define('PORTAL_URL', 'https://portal.yourdomain.com');

// Security salt for additional hashing (generate random value)
// Example: openssl rand -base64 32
define('SALT', 'REPLACE_ME_WITH_RANDOM_BASE64_STRING');

// =============================
// ERROR LOGGING (Production)
// =============================

// Display errors: OFF in production (never expose to users)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log file path (ensure portal/logs/ exists and is writable)
ini_set('error_log', __DIR__ . '/logs/error.log');

// =============================
// PHP SETTINGS
// =============================

// Max file upload size (for employee photos, etc.)
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

// Execution timeout
ini_set('max_execution_time', 300);

// =============================
// DATABASE CONNECTION (PDO)
// =============================

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        3306,
        DB_NAME
    );
    
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    
} catch (PDOException $e) {
    // Log error securely; don't expose message to user
    error_log('Database connection failed: ' . $e->getMessage());
    
    // Display generic error message to user
    die('Service temporarily unavailable. Please try again later.');
}

// =============================
// END CONFIG-DEPLOY.PHP
// =============================
?>
