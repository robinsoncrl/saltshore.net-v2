<?php
require_once 'config.php';
// Redirect to setup if no users exist yet
$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count === 0) {
    header('Location: setup.php');
    exit;
}
$setup_done = isset($_GET['setup']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Saltshore Owner Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/portal.css">
    <style>
        .pin-hint { font-size: 0.82rem; color: var(--color-secondary); opacity: 0.65; margin-top: 4px; }
    </style>
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-card">
            <img src="../assets/img/SSS_logo_2.png" alt="Saltshore Systems" class="login-logo">
            <h1>Owner Portal</h1>
            <p class="login-subtitle">Saltshore Systems</p>

            <?php if ($setup_done): ?>
                <div class="alert alert-success">Access created — log in below.</div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['login_error'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form method="POST" action="auth.php" class="login-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <div class="form-group">
                    <label for="username">Admin Name or Employee ID</label>
                    <input type="text"
                           id="username"
                           name="username"
                           required
                           autocomplete="username"
                           autofocus>
                </div>

                <div class="form-group">
                    <label for="pin">4-Digit PIN</label>
                    <input type="password"
                           id="pin"
                           name="password"
                           inputmode="numeric"
                           pattern="[0-9]{4}"
                           maxlength="4"
                           placeholder="••••"
                           required
                           autocomplete="current-password">
                          <p class="pin-hint">Use admin PIN or assigned employee login PIN.</p>
                </div>

                <button type="submit" class="btn-primary btn-full">Log In</button>
            </form>

            <p style="margin-bottom: var(--space-md);">
                <a href="../index.php" style="font-size: 0.88rem; color: var(--color-accent); text-decoration: none;">&larr; Back to Homepage</a>
            </p>

            <p class="login-footer">Secure access for authorized personnel only.</p>
        </div>
    </div>

</body>
</html>
