<?php
/**
 * Saltshore Owner Portal — Layout Header
 * Include this at the top of every authenticated page
 */

require_once 'config.php';
require_auth();

$page_title = $page_title ?? 'Dashboard';
$current_page = $current_page ?? '';

// Route-level access control
$owner_only_pages = ['dashboard', 'ledger', 'kpis', 'reports', 'settings'];
if (in_array($current_page, $owner_only_pages, true) && !(is_owner_login() || is_management_user())) {
    deny_access_to_calgen();
}
if ($current_page === 'management' && !is_management_user()) {
    deny_access_to_calgen();
}
if ($current_page === 'finpro' && !(is_owner_login() || is_management_user() || is_employee_login())) {
    deny_access_to_calgen();
}

$is_owner = is_owner_login();
$can_manage = is_management_user();
$can_access_owner_pages = $is_owner || $can_manage;
$can_access_finpro = $can_access_owner_pages || is_employee_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Saltshore Owner Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="portal-layout">
    
    <!-- Sidebar -->
    <aside class="portal-sidebar" id="portal-sidebar">
        <div class="portal-logo">
            <img src="../assets/img/SSS_logo_2.png" alt="Saltshore Systems">
            <h2>Owner Portal</h2>
            <p>Saltshore Systems</p>
        </div>
        
        <nav class="portal-nav">
            <ul>
                <?php if ($can_access_owner_pages): ?>
                <li><a href="index.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">📊</span>
                    Dashboard
                </a></li>
                <?php endif; ?>

                <li><a href="calgen.php" class="<?= $current_page === 'calgen' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">⏱️</span>
                    CalGen
                </a></li>

                <?php if ($can_access_finpro): ?>
                <li><a href="finpro.php" class="<?= $current_page === 'finpro' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">💰</span>
                    <?= is_employee_login() ? 'My Pay' : 'FinPro' ?>
                </a></li>
                <?php if ($can_access_owner_pages): ?>
                <li><a href="ledgerpro.php" class="<?= $current_page === 'ledger' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">📋</span>
                    LedgerPro
                </a></li>
                <li><a href="kpis.php" class="<?= $current_page === 'kpis' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">📈</span>
                    KPIs
                </a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">📄</span>
                    Reports
                </a></li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($can_manage): ?>
                <li><a href="management.php" class="<?= $current_page === 'management' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">👥</span>
                    Management
                </a></li>
                <?php endif; ?>

                <?php if ($can_access_owner_pages): ?>
                <li><a href="settings.php" class="<?= $current_page === 'settings' ? 'active' : '' ?>">
                    <span class="portal-nav-icon">⚙️</span>
                    Settings
                </a></li>
                <?php endif; ?>

                <li><a href="auth.php?action=logout" style="color: rgba(239, 68, 68, 0.8);">
                    <span class="portal-nav-icon">🚪</span>
                    Logout
                </a></li>
            </ul>
        </nav>
    </aside>
    <div class="portal-sidebar-backdrop" data-sidebar-close></div>
    
    <!-- Main Content Area -->
    <main class="portal-main">
        
        <!-- Top Bar -->
        <header class="portal-topbar">
            <div class="portal-topbar-left">
                <button
                    type="button"
                    class="portal-menu-toggle"
                    id="portal-menu-toggle"
                    aria-label="Toggle navigation menu"
                    aria-controls="portal-sidebar"
                    aria-expanded="false"
                >☰</button>
                <h1><?= htmlspecialchars($page_title) ?></h1>
            </div>
            <div class="portal-topbar-actions">
                <span class="portal-user">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    <span style="opacity: 0.6; font-size: 0.78rem; margin-left: 6px;">
                        <?= is_employee_login() ? '(Employee Portal)' : '(Owner Portal)' ?>
                    </span>
                </span>
            </div>
        </header>
        
        <!-- Content -->
        <div class="portal-content">
