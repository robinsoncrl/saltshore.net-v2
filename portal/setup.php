<?php
/**
 * Saltshore Owner Portal — First-Run Setup
 * Only accessible when no users exist
 */

require_once 'config.php';

// If a user already exists, go to login
$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count > 0) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $pin     = trim($_POST['pin']     ?? '');
    $confirm = trim($_POST['pin_confirm'] ?? '');

    if (!$name) {
        $error = 'Please enter your name.';
    } elseif (!preg_match('/^\d{4}$/', $pin)) {
        $error = 'PIN must be exactly 4 digits.';
    } elseif ($pin !== $confirm) {
        $error = 'PINs do not match.';
    } else {
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)")
            ->execute([$name, $hash]);
        header('Location: login.php?setup=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — Saltshore Owner Portal</title>
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
        <h1>Welcome</h1>
        <p class="login-subtitle">Create your Owner Portal access</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       placeholder="e.g. Robin"
                       required autofocus autocomplete="name">
            </div>

            <div class="form-group">
                <label for="pin">4-Digit PIN</label>
                <input type="password" id="pin" name="pin"
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       placeholder="••••" required autocomplete="new-password">
                <p class="pin-hint">Numbers only, exactly 4 digits.</p>
            </div>

            <div class="form-group">
                <label for="pin_confirm">Confirm PIN</label>
                <input type="password" id="pin_confirm" name="pin_confirm"
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       placeholder="••••" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary btn-full">Create Access</button>
        </form>
    </div>
</div>
</body>
</html>
