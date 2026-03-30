<?php
/**
 * Saltshore Owner Portal — Settings
 */

$page_title   = 'Settings';
$current_page = 'settings';

include 'includes/header.php';

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';

    // Save business profile & preferences
    if ($action === 'save_settings') {
        $fields = [
            'business_name'  => substr(trim($_POST['business_name']  ?? ''), 0, 100),
            'owner_name'     => substr(trim($_POST['owner_name']      ?? ''), 0, 100),
            'business_email' => substr(trim($_POST['business_email']  ?? ''), 0, 150),
            'timezone'       => $_POST['timezone']    ?? 'America/New_York',
            'hourly_rate'    => max(0, (float)($_POST['hourly_rate']   ?? 0)),
            'currency'       => $_POST['currency']    ?? 'USD',
        ];

        foreach ($fields as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO portal_settings (user_id, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$_SESSION['user_id'], $key, $value]);
        }

        $_SESSION['flash_success'] = 'Settings saved.';
        header('Location: settings.php');
        exit;
    }

    // Change PIN
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (!preg_match('/^\d{4}$/', $new)) {
            $_SESSION['flash_error'] = 'New PIN must be exactly 4 digits.';
            header('Location: settings.php');
            exit;
        }

        if ($new !== $confirm) {
            $_SESSION['flash_error'] = 'New PINs do not match.';
            header('Location: settings.php');
            exit;
        }

        $user_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $user_stmt->execute([$_SESSION['user_id']]);
        $user = $user_stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
            header('Location: settings.php');
            exit;
        }

        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([$new_hash, $_SESSION['user_id']]);

        $_SESSION['flash_success'] = 'Password updated.';
        header('Location: settings.php');
        exit;
    }
}

// ── Load current settings ──────────────────────────────────────────────────
$s_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM portal_settings WHERE user_id = ?");
$s_stmt->execute([$_SESSION['user_id']]);
$raw_settings = $s_stmt->fetchAll();

$settings = [];
foreach ($raw_settings as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Defaults
$settings += [
    'business_name'  => 'Saltshore Systems',
    'owner_name'     => '',
    'business_email' => '',
    'timezone'       => 'America/New_York',
    'hourly_rate'    => '0',
    'currency'       => 'USD',
];

$flash       = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$timezones = DateTimeZone::listIdentifiers();
?>

<?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Business Profile -->
<div class="data-table-container" style="padding: var(--space-xl); margin-bottom: var(--space-2xl); max-width: 680px;">
    <h3 style="color: var(--color-primary); margin-bottom: var(--space-xl);">Business Profile</h3>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="save_settings">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
            <div class="form-group">
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name"
                       value="<?= htmlspecialchars($settings['business_name']) ?>"
                       placeholder="e.g. Saltshore Systems">
            </div>
            <div class="form-group">
                <label for="owner_name">Owner Name</label>
                <input type="text" id="owner_name" name="owner_name"
                       value="<?= htmlspecialchars($settings['owner_name']) ?>"
                       placeholder="Your name">
            </div>
        </div>

        <div class="form-group">
            <label for="business_email">Business Email</label>
            <input type="email" id="business_email" name="business_email"
                   value="<?= htmlspecialchars($settings['business_email']) ?>"
                   placeholder="you@example.com">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-lg);">
            <div class="form-group">
                <label for="hourly_rate">Default Rate ($/hr)</label>
                <input type="number" id="hourly_rate" name="hourly_rate"
                       min="0" step="0.01"
                       value="<?= htmlspecialchars($settings['hourly_rate']) ?>">
            </div>
            <div class="form-group">
                <label for="currency">Currency</label>
                <select id="currency" name="currency">
                    <option value="USD" <?= $settings['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    <option value="CAD" <?= $settings['currency'] === 'CAD' ? 'selected' : '' ?>>CAD (CA$)</option>
                    <option value="EUR" <?= $settings['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                    <option value="GBP" <?= $settings['currency'] === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $settings['timezone'] === $tz ? 'selected' : '' ?>>
                            <?= $tz ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<!-- Change PIN -->
<div class="data-table-container" style="padding: var(--space-xl); max-width: 680px;">
    <h3 style="color: var(--color-primary); margin-bottom: var(--space-xl);">Change PIN</h3>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="change_password">

        <div class="form-group">
            <label for="current_password">Current PIN</label>
            <input type="password" id="current_password" name="current_password"
                   inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                   placeholder="••••" required autocomplete="current-password">
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
            <div class="form-group">
                <label for="new_password">New PIN</label>
                <input type="password" id="new_password" name="new_password"
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       placeholder="••••" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New PIN</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                       placeholder="••••" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="btn-primary">Update PIN</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
