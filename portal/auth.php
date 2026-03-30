<?php
/**
 * Saltshore Owner Portal — Authentication Handlers
 */

require_once 'config.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }
    
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_role'] = 'owner';
        $_SESSION['login_time'] = time();
        
        header('Location: index.php');
        exit;
    } else {
        // Try employee credential login (Employee ID + PIN)
        $emp_stmt = $pdo->prepare("SELECT id, user_id, full_name, employee_code, pin_hash, pin_active, login_locked, status FROM employees WHERE employee_code = ? LIMIT 1");
        $emp_stmt->execute([strtoupper($identifier)]);
        $emp = $emp_stmt->fetch();

        if ($emp && $emp['status'] !== 'active') {
            $_SESSION['login_error'] = 'Employee access is suspended. Contact management.';
            header('Location: login.php');
            exit;
        }

        if ($emp && (int)$emp['login_locked'] === 1) {
            $_SESSION['login_error'] = 'Employee login is locked. Contact the owner for unlock.';
            header('Location: login.php');
            exit;
        }

        if ($emp && ((int)$emp['pin_active'] !== 1 || empty($emp['pin_hash']))) {
            $_SESSION['login_error'] = 'Employee PIN is not active. Contact management.';
            header('Location: login.php');
            exit;
        }

        if ($emp && password_verify($password, $emp['pin_hash'])) {
            session_regenerate_id(true);

            // Employees operate within the owner account workspace
            $_SESSION['user_id'] = (int)$emp['user_id'];
            $_SESSION['username'] = $emp['full_name'];
            $_SESSION['employee_id'] = (int)$emp['id'];
            $_SESSION['employee_code'] = $emp['employee_code'];
            $_SESSION['login_role'] = 'employee';
            $_SESSION['login_time'] = time();

            header('Location: index.php');
            exit;
        }

        $_SESSION['login_error'] = 'Invalid login credentials';
        header('Location: login.php');
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
