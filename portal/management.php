<?php
/**
 * Saltshore Owner Portal - Management
 * Employee onboarding, profile, scheduling, and credentials
 */

$page_title = 'Management - Team';
$current_page = 'management';

include 'includes/header.php';

$can_manage = is_management_user();
$can_ceo_owner = is_ceo_owner();

if (!$can_manage) {
    deny_access_to_calgen();
}

function handle_employee_photo_upload(int $employee_id, ?string $existing_photo): array {
    if (empty($_FILES['photo_file']) || !isset($_FILES['photo_file']['error'])) {
        return ['photo' => $existing_photo, 'error' => null];
    }

    $file = $_FILES['photo_file'];
    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['photo' => $existing_photo, 'error' => null];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['photo' => $existing_photo, 'error' => 'Photo upload failed. Please try again.'];
    }

    $max_bytes = 2 * 1024 * 1024;
    if ((int)$file['size'] > $max_bytes) {
        return ['photo' => $existing_photo, 'error' => 'Photo must be 2MB or less.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return ['photo' => $existing_photo, 'error' => 'Invalid photo format. Use JPG, PNG, WEBP, or GIF.'];
    }

    $upload_dir = __DIR__ . '/uploads/employee_photos';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['photo' => $existing_photo, 'error' => 'Unable to create photo upload directory.'];
    }

    $ext = $allowed[$mime];
    $name = 'emp_' . $employee_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $upload_dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['photo' => $existing_photo, 'error' => 'Unable to save uploaded photo.'];
    }

    $new_path = 'uploads/employee_photos/' . $name;

    if (!empty($existing_photo) && str_starts_with($existing_photo, 'uploads/employee_photos/')) {
        $old_file = __DIR__ . '/' . $existing_photo;
        if (is_file($old_file)) {
            @unlink($old_file);
        }
    }

    return ['photo' => $new_path, 'error' => null];
}

function employee_belongs_to_user(PDO $pdo, int $employee_id, int $user_id): bool {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$employee_id, $user_id]);
    return (bool)$stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'onboard_employee') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role_title = trim($_POST['role_title'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $hourly_rate = $_POST['hourly_rate'] !== '' ? (float)$_POST['hourly_rate'] : null;

        if ($full_name === '') {
            $_SESSION['flash_success'] = 'Employee name is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO employees (user_id, full_name, email, phone, role_title, start_date, hourly_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $_SESSION['user_id'],
                $full_name,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $role_title !== '' ? $role_title : null,
                $start_date !== '' ? $start_date : null,
                $hourly_rate
            ]);
            $_SESSION['flash_success'] = 'Employee onboarded.';
        }

        header('Location: management.php');
        exit;
    }

    if ($action === 'save_employee_profile') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        if (!employee_belongs_to_user($pdo, $employee_id, (int)$_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Employee not found.';
            header('Location: management.php');
            exit;
        }

        $existing_stmt = $pdo->prepare("SELECT photo_url FROM employees WHERE id = ? AND user_id = ? LIMIT 1");
        $existing_stmt->execute([$employee_id, $_SESSION['user_id']]);
        $existing = $existing_stmt->fetch();
        $existing_photo = $existing['photo_url'] ?? null;

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role_title = trim($_POST['role_title'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $hourly_rate = $_POST['hourly_rate'] !== '' ? (float)$_POST['hourly_rate'] : null;
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $city_state_zip = trim($_POST['city_state_zip'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        $upload_result = handle_employee_photo_upload($employee_id, $existing_photo);
        if (!empty($upload_result['error'])) {
            $_SESSION['flash_success'] = $upload_result['error'];
            header('Location: management.php');
            exit;
        }
        $photo_url = $upload_result['photo'];

        if ($full_name === '') {
            $_SESSION['flash_success'] = 'Employee full name is required.';
            header('Location: management.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees
            SET full_name = ?,
                email = ?,
                phone = ?,
                role_title = ?,
                start_date = ?,
                hourly_rate = ?,
                photo_url = ?,
                address_line1 = ?,
                city_state_zip = ?,
                emergency_contact = ?
            WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $full_name,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $role_title !== '' ? $role_title : null,
            $start_date !== '' ? $start_date : null,
            $hourly_rate,
            $photo_url,
            $address_line1 !== '' ? $address_line1 : null,
            $city_state_zip !== '' ? $city_state_zip : null,
            $emergency_contact !== '' ? $emergency_contact : null,
            $employee_id,
            $_SESSION['user_id']
        ]);

        $_SESSION['flash_success'] = 'Employee profile updated.';
        header('Location: management.php');
        exit;
    }

    if ($action === 'create_schedule') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $schedule_date = $_POST['schedule_date'] ?? '';
        $shift_start = $_POST['shift_start'] ?? '';
        $shift_end = $_POST['shift_end'] ?? '';
        $notes = trim($_POST['schedule_notes'] ?? '');

        if (!employee_belongs_to_user($pdo, $employee_id, (int)$_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Employee not found.';
            header('Location: management.php');
            exit;
        }

        if ($schedule_date === '' || $shift_start === '' || $shift_end === '') {
            $_SESSION['flash_success'] = 'Please complete all schedule fields.';
            header('Location: management.php');
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO work_schedules (user_id, employee_id, schedule_date, shift_start, shift_end, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $employee_id,
            $schedule_date,
            $shift_start,
            $shift_end,
            $notes !== '' ? $notes : null
        ]);

        $_SESSION['flash_success'] = 'Schedule saved.';
        header('Location: management.php');
        exit;
    }

    if ($action === 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $pdo->prepare("DELETE FROM work_schedules WHERE id = ? AND user_id = ?")
                ->execute([$schedule_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Schedule deleted.';
        }
        header('Location: management.php');
        exit;
    }

    if ($action === 'save_credentials') {
        if (!$can_ceo_owner) {
            $_SESSION['flash_success'] = 'Only CEO1 can manage IDs and PIN controls.';
            header('Location: management.php');
            exit;
        }

        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $employee_code = strtoupper(trim($_POST['employee_code'] ?? ''));
        $pin = trim($_POST['pin'] ?? '');
        $pin_confirm = trim($_POST['pin_confirm'] ?? '');

        if (!employee_belongs_to_user($pdo, $employee_id, (int)$_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Employee not found.';
            header('Location: management.php');
            exit;
        }

        if ($employee_code === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $employee_code)) {
            $_SESSION['flash_success'] = 'Employee ID must be 3-40 chars: A-Z, 0-9, _ or -.';
            header('Location: management.php');
            exit;
        }

        $dup_stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ? AND id <> ? LIMIT 1");
        $dup_stmt->execute([$employee_code, $employee_id]);
        if ($dup_stmt->fetch()) {
            $_SESSION['flash_success'] = 'Employee ID already in use.';
            header('Location: management.php');
            exit;
        }

        if ($pin !== '') {
            if (!preg_match('/^\d{4}$/', $pin)) {
                $_SESSION['flash_success'] = 'PIN must be exactly 4 digits.';
                header('Location: management.php');
                exit;
            }
            if ($pin !== $pin_confirm) {
                $_SESSION['flash_success'] = 'PIN confirmation does not match.';
                header('Location: management.php');
                exit;
            }

            $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE employees SET employee_code = ?, pin_hash = ?, pin_active = 1, login_locked = 0, status = 'active' WHERE id = ? AND user_id = ?");
            $upd->execute([$employee_code, $pin_hash, $employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Credentials updated and unlocked.';
        } else {
            $upd = $pdo->prepare("UPDATE employees SET employee_code = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$employee_code, $employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee ID updated.';
        }

        header('Location: management.php');
        exit;
    }

    if ($action === 'lock_login' || $action === 'unlock_login' || $action === 'suspend_employee' || $action === 'unsuspend_employee' || $action === 'revoke_access') {
        if (!$can_ceo_owner) {
            $_SESSION['flash_success'] = 'Only CEO1 can perform credential security actions.';
            header('Location: management.php');
            exit;
        }

        $employee_id = (int)($_POST['employee_id'] ?? 0);
        if (!employee_belongs_to_user($pdo, $employee_id, (int)$_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Employee not found.';
            header('Location: management.php');
            exit;
        }

        if ($action === 'lock_login') {
            $pdo->prepare("UPDATE employees SET login_locked = 1 WHERE id = ? AND user_id = ?")
                ->execute([$employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee login locked.';
        }

        if ($action === 'unlock_login') {
            $pdo->prepare("UPDATE employees SET login_locked = 0, status = 'active' WHERE id = ? AND user_id = ?")
                ->execute([$employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee login unlocked.';
        }

        if ($action === 'suspend_employee') {
            $pdo->prepare("UPDATE employees SET status = 'inactive', login_locked = 1 WHERE id = ? AND user_id = ?")
                ->execute([$employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee suspended.';
        }

        if ($action === 'unsuspend_employee') {
            $pdo->prepare("UPDATE employees SET status = 'active', login_locked = 0 WHERE id = ? AND user_id = ?")
                ->execute([$employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee suspension removed.';
        }

        if ($action === 'revoke_access') {
            $pdo->prepare("UPDATE employees SET employee_code = NULL, pin_hash = NULL, pin_active = 0, login_locked = 1, status = 'inactive' WHERE id = ? AND user_id = ?")
                ->execute([$employee_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Employee ID/PIN revoked and access disabled.';
        }

        header('Location: management.php');
        exit;
    }
}

$active_stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ? AND status = 'active' ORDER BY full_name ASC");
$active_stmt->execute([$_SESSION['user_id']]);
$employees = $active_stmt->fetchAll();

$inactive_stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ? AND status = 'inactive' ORDER BY full_name ASC");
$inactive_stmt->execute([$_SESSION['user_id']]);
$archived = $inactive_stmt->fetchAll();

$metrics_stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN DATE_FORMAT(start_time, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN duration_seconds ELSE 0 END), 0) AS month_seconds,
    COALESCE(SUM(CASE WHEN DATE_FORMAT(start_time, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND is_approved = 1 THEN duration_seconds ELSE 0 END), 0) AS approved_seconds,
    COALESCE(SUM(CASE WHEN DATE_FORMAT(start_time, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND submitted_for_approval = 1 AND is_approved = 0 THEN 1 ELSE 0 END), 0) AS pending_approvals
FROM time_blocks
WHERE user_id = ? AND employee_id = ?");

$sched_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM work_schedules WHERE user_id = ? AND employee_id = ? AND schedule_date >= CURDATE()");
$schedules_stmt = $pdo->prepare("SELECT id, employee_id, schedule_date, shift_start, shift_end, notes FROM work_schedules WHERE user_id = ? AND schedule_date >= CURDATE() ORDER BY schedule_date ASC, shift_start ASC");
$schedules_stmt->execute([$_SESSION['user_id']]);
$all_schedules = $schedules_stmt->fetchAll();

$schedules_by_employee = [];
foreach ($all_schedules as $schedule) {
    $eid = (int)$schedule['employee_id'];
    if (!isset($schedules_by_employee[$eid])) {
        $schedules_by_employee[$eid] = [];
    }
    $schedules_by_employee[$eid][] = $schedule;
}

$employee_metrics = [];
foreach ($employees as $emp) {
    $employee_id = (int)$emp['id'];

    $metrics_stmt->execute([$_SESSION['user_id'], $employee_id]);
    $metric_row = $metrics_stmt->fetch();

    $sched_count_stmt->execute([$_SESSION['user_id'], $employee_id]);
    $upcoming_shifts = (int)$sched_count_stmt->fetchColumn();

    $employee_metrics[$employee_id] = [
        'month_hours' => round(((int)$metric_row['month_seconds']) / 3600, 1),
        'approved_hours' => round(((int)$metric_row['approved_seconds']) / 3600, 1),
        'pending_approvals' => (int)$metric_row['pending_approvals'],
        'upcoming_shifts' => $upcoming_shifts,
    ];
}

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<?php if ($flash): ?>
<div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="data-table-container" style="padding: var(--space-xl); margin-bottom: var(--space-2xl);">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-md); flex-wrap: wrap;">
        <div>
            <h3 style="color: var(--color-primary); margin-bottom: 4px;">Employee Roster</h3>
            <p style="opacity: 0.7; font-size: 0.9rem;">Manage schedules, employee profiles, and credential security controls.</p>
            <?php if (!$can_ceo_owner): ?>
                <p style="opacity: 0.7; font-size: 0.85rem; margin-top: 6px; color: var(--warning);">Credential lock/unlock/suspend/revoke actions are restricted to CEO1.</p>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-primary" data-modal-open="onboardModal">+ Onboard Employee</button>
    </div>
</div>

<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Active Employees</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Contact</th>
                <th>Start Date</th>
                <th>Rate</th>
                <th>Schedule</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($employees): ?>
                <?php foreach ($employees as $emp): ?>
                    <?php $metrics = $employee_metrics[(int)$emp['id']] ?? ['month_hours' => 0, 'approved_hours' => 0, 'pending_approvals' => 0, 'upcoming_shifts' => 0]; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['role_title'] ?? '—') ?></td>
                        <td>
                            <div><?= htmlspecialchars($emp['email'] ?? '—') ?></div>
                            <div style="opacity: 0.7; font-size: 0.82rem;"><?= htmlspecialchars($emp['phone'] ?? '—') ?></div>
                        </td>
                        <td><?= $emp['start_date'] ? date('M d, Y', strtotime($emp['start_date'])) : '—' ?></td>
                        <td><?= $emp['hourly_rate'] !== null ? format_currency((float)$emp['hourly_rate']) . '/hr' : '—' ?></td>
                        <td>
                            <div><strong><?= (int)$metrics['upcoming_shifts'] ?></strong> upcoming</div>
                            <div style="font-size: 0.82rem; opacity: 0.7;"><?= number_format((float)$metrics['month_hours'], 1) ?>h this month</div>
                        </td>
                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="btn-secondary" data-modal-open="scheduleModal" data-employee-id="<?= (int)$emp['id'] ?>" data-employee-name="<?= htmlspecialchars($emp['full_name'], ENT_QUOTES) ?>">Schedule</button>
                            <button type="button" class="btn-secondary" data-modal-open="profileModal<?= (int)$emp['id'] ?>">Profile</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; opacity: 0.55;">No active employees yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Credential Manager (CEO1)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Employee ID</th>
                <th>PIN</th>
                <th>Status</th>
                <th>Security Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($employees): ?>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                        <td>
                            <form method="POST" style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="save_credentials">
                                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                                <input type="text" name="employee_code" value="<?= htmlspecialchars($emp['employee_code'] ?? '') ?>" placeholder="EMP-001" style="width: 110px; padding: 6px 8px; border: 1px solid var(--panel-border); border-radius: 8px; font-family: var(--font-primary);" <?= $can_ceo_owner ? '' : 'disabled' ?>>
                        </td>
                        <td>
                                <input type="password" name="pin" maxlength="4" pattern="[0-9]{4}" placeholder="4-digit PIN" style="width: 100px; padding: 6px 8px; border: 1px solid var(--panel-border); border-radius: 8px; font-family: var(--font-primary);" <?= $can_ceo_owner ? '' : 'disabled' ?>>
                                <input type="password" name="pin_confirm" maxlength="4" pattern="[0-9]{4}" placeholder="Confirm" style="width: 90px; padding: 6px 8px; border: 1px solid var(--panel-border); border-radius: 8px; font-family: var(--font-primary);" <?= $can_ceo_owner ? '' : 'disabled' ?>>
                        </td>
                        <td>
                            <?= !empty($emp['employee_code']) ? '<span class="pill-success">ID Set</span>' : '<span class="pill-muted">No ID</span>' ?>
                            <?= !empty($emp['pin_active']) ? '<span class="pill-success">PIN Active</span>' : '<span class="pill-muted">PIN Off</span>' ?>
                            <?= !empty($emp['login_locked']) ? '<span class="pill-danger">Locked</span>' : '<span class="pill-success">Unlocked</span>' ?>
                            <?= ($emp['status'] === 'active') ? '<span class="pill-success">Active</span>' : '<span class="pill-danger">Suspended</span>' ?>
                        </td>
                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="submit" class="btn-secondary" style="padding: 6px 12px;" <?= $can_ceo_owner ? '' : 'disabled' ?>>Save</button>
                            </form>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                                <input type="hidden" name="action" value="<?= !empty($emp['login_locked']) ? 'unlock_login' : 'lock_login' ?>">
                                <button type="submit" class="btn-secondary" style="padding: 6px 12px;" <?= $can_ceo_owner ? '' : 'disabled' ?>><?= !empty($emp['login_locked']) ? 'Unlock' : 'Lock' ?></button>
                            </form>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                                <input type="hidden" name="action" value="<?= $emp['status'] === 'active' ? 'suspend_employee' : 'unsuspend_employee' ?>">
                                <button type="submit" class="btn-secondary" style="padding: 6px 12px;" <?= $can_ceo_owner ? '' : 'disabled' ?>><?= $emp['status'] === 'active' ? 'Suspend' : 'Unsuspend' ?></button>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke employee ID/PIN and disable access?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                                <input type="hidden" name="action" value="revoke_access">
                                <button type="submit" class="btn-icon-danger" title="Revoke Access" aria-label="Revoke Access" <?= $can_ceo_owner ? '' : 'disabled' ?>>&#128465;</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; opacity: 0.55;">Onboard employees first to assign credentials.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($archived): ?>
<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Inactive / Suspended Employees</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Security</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($archived as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['full_name']) ?></td>
                    <td><?= htmlspecialchars($emp['role_title'] ?? '—') ?></td>
                    <td style="display:flex; gap:8px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="unsuspend_employee">
                            <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                            <button type="submit" class="btn-success" style="padding: 6px 12px;" <?= $can_ceo_owner ? '' : 'disabled' ?>>Reactivate</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Fully revoke employee credentials?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="revoke_access">
                            <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                            <button type="submit" class="btn-icon-danger" title="Revoke Access" aria-label="Revoke Access" <?= $can_ceo_owner ? '' : 'disabled' ?>>&#128465;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="portal-modal" id="onboardModal" aria-hidden="true">
    <div class="portal-modal__backdrop" data-modal-close></div>
    <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="onboardModalTitle">
        <div class="portal-modal__header">
            <h3 id="onboardModalTitle">Onboard Employee</h3>
            <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="POST" class="portal-modal__body">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="onboard_employee">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-md);">
                <div class="form-group">
                    <label for="role_title">Role</label>
                    <input type="text" id="role_title" name="role_title" placeholder="Tech, Admin, Support...">
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date">
                </div>
                <div class="form-group">
                    <label for="hourly_rate">Hourly Rate</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>

            <div class="portal-modal__footer">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Create Employee</button>
            </div>
        </form>
    </div>
</div>

<div class="portal-modal" id="scheduleModal" aria-hidden="true">
    <div class="portal-modal__backdrop" data-modal-close></div>
    <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle" style="max-width: 840px;">
        <div class="portal-modal__header">
            <h3 id="scheduleModalTitle">Schedule Employee Shift</h3>
            <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="POST" class="portal-modal__body" style="border-bottom: 1px solid var(--panel-border);">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create_schedule">
            <input type="hidden" name="employee_id" id="scheduleEmployeeId" value="">

            <div style="font-size: 0.9rem; color: var(--color-secondary); margin-bottom: var(--space-md);">
                Scheduling for: <strong id="scheduleEmployeeName">Select employee</strong>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-md);">
                <div class="form-group">
                    <label for="schedule_date">Date</label>
                    <input type="date" id="schedule_date" name="schedule_date" required>
                </div>
                <div class="form-group">
                    <label for="shift_start">Start</label>
                    <input type="time" id="shift_start" name="shift_start" required>
                </div>
                <div class="form-group">
                    <label for="shift_end">End</label>
                    <input type="time" id="shift_end" name="shift_end" required>
                </div>
            </div>

            <div class="form-group">
                <label for="schedule_notes">Notes</label>
                <input type="text" id="schedule_notes" name="schedule_notes" placeholder="Location, assignment, reminders...">
            </div>

            <div class="portal-modal__footer">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Save Shift</button>
            </div>
        </form>

        <div class="portal-modal__body">
            <h4 style="margin-bottom: var(--space-md); color: var(--color-primary);">Upcoming Scheduled Shifts</h4>
            <div id="scheduleCalendarRows" style="display:grid; gap:10px;"></div>
        </div>
    </div>
</div>

<?php foreach ($employees as $emp): ?>
    <?php $metrics = $employee_metrics[(int)$emp['id']] ?? ['month_hours' => 0, 'approved_hours' => 0, 'pending_approvals' => 0, 'upcoming_shifts' => 0]; ?>
    <div class="portal-modal" id="profileModal<?= (int)$emp['id'] ?>" aria-hidden="true">
        <div class="portal-modal__backdrop" data-modal-close></div>
        <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="profileTitle<?= (int)$emp['id'] ?>" style="max-width: 900px;">
            <div class="portal-modal__header">
                <h3 id="profileTitle<?= (int)$emp['id'] ?>">Employee Profile - <?= htmlspecialchars($emp['full_name']) ?></h3>
                <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="profile-tabs" style="display:flex; gap:8px; padding: 16px 20px; border-bottom: 1px solid var(--panel-border);">
                <button type="button" class="btn-secondary js-profile-tab is-active" data-target="profile-contact-<?= (int)$emp['id'] ?>">Contact Sheet</button>
                <button type="button" class="btn-secondary js-profile-tab" data-target="profile-kpi-<?= (int)$emp['id'] ?>">Performance KPI</button>
            </div>

            <div class="portal-modal__body js-profile-panel" id="profile-contact-<?= (int)$emp['id'] ?>">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save_employee_profile">
                    <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">

                    <div style="display:grid; grid-template-columns: 120px 1fr; gap: var(--space-lg); align-items:start; margin-bottom: var(--space-lg);">
                        <div style="width: 110px; height: 110px; border-radius: 50%; overflow:hidden; border:1px solid var(--panel-border); background:#f3f4f6;">
                            <img src="<?= htmlspecialchars($emp['photo_url'] ?: '../assets/img/SSS_logo_2.png') ?>" alt="Employee photo" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Upload Employee Photo</label>
                            <input type="file" name="photo_file" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small style="opacity:0.7;">JPG, PNG, WEBP, or GIF up to 2MB.</small>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($emp['full_name']) ?>" required></div>
                        <div class="form-group"><label>Role</label><input type="text" name="role_title" value="<?= htmlspecialchars($emp['role_title'] ?? '') ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($emp['email'] ?? '') ?>"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group"><label>Address</label><input type="text" name="address_line1" value="<?= htmlspecialchars($emp['address_line1'] ?? '') ?>"></div>
                        <div class="form-group"><label>City/State/Zip</label><input type="text" name="city_state_zip" value="<?= htmlspecialchars($emp['city_state_zip'] ?? '') ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" value="<?= htmlspecialchars($emp['emergency_contact'] ?? '') ?>"></div>
                        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= htmlspecialchars($emp['start_date'] ?? '') ?>"></div>
                        <div class="form-group"><label>Hourly Rate</label><input type="number" name="hourly_rate" min="0" step="0.01" value="<?= htmlspecialchars((string)($emp['hourly_rate'] ?? '')) ?>"></div>
                    </div>

                    <div class="portal-modal__footer">
                        <button type="button" class="btn-secondary" data-modal-close>Close</button>
                        <button type="submit" class="btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>

            <div class="portal-modal__body js-profile-panel" id="profile-kpi-<?= (int)$emp['id'] ?>" style="display:none;">
                <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="kpi-card">
                        <div class="kpi-label">Hours This Month</div>
                        <div class="kpi-value"><?= number_format((float)$metrics['month_hours'], 1) ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Approved Hours</div>
                        <div class="kpi-value" style="color: var(--success);"><?= number_format((float)$metrics['approved_hours'], 1) ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pending Approval</div>
                        <div class="kpi-value" style="color: var(--warning);"><?= (int)$metrics['pending_approvals'] ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Upcoming Shifts</div>
                        <div class="kpi-value"><?= (int)$metrics['upcoming_shifts'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
const schedulesByEmployee = <?= json_encode($schedules_by_employee) ?>;

function renderEmployeeSchedules(employeeId) {
    const root = document.getElementById('scheduleCalendarRows');
    if (!root) return;

    root.innerHTML = '';
    const rows = schedulesByEmployee[String(employeeId)] || [];

    if (!rows.length) {
        root.innerHTML = '<div style="opacity:0.6; padding:10px; border:1px dashed var(--panel-border); border-radius:8px;">No upcoming shifts for this employee.</div>';
        return;
    }

    rows.forEach(function (row) {
        const wrap = document.createElement('div');
        wrap.style.border = '1px solid var(--panel-border)';
        wrap.style.borderRadius = '10px';
        wrap.style.padding = '10px 12px';
        wrap.style.display = 'flex';
        wrap.style.justifyContent = 'space-between';
        wrap.style.gap = '10px';
        wrap.style.alignItems = 'center';

        const left = document.createElement('div');
        left.innerHTML = '<strong>' + row.schedule_date + '</strong><div style="opacity:0.7; font-size:0.85rem;">' + row.shift_start + ' - ' + row.shift_end + '</div><div style="opacity:0.75; font-size:0.82rem;">' + (row.notes || '-') + '</div>';

        const right = document.createElement('form');
        right.method = 'POST';
        right.innerHTML =
            '<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">' +
            '<input type="hidden" name="action" value="delete_schedule">' +
            '<input type="hidden" name="schedule_id" value="' + row.id + '">' +
            '<button type="submit" class="btn-icon-danger" title="Delete shift" aria-label="Delete shift">&#128465;</button>';

        wrap.appendChild(left);
        wrap.appendChild(right);
        root.appendChild(wrap);
    });
}

document.querySelectorAll('[data-modal-open="scheduleModal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const employeeId = btn.getAttribute('data-employee-id');
        const employeeName = btn.getAttribute('data-employee-name');
        const idInput = document.getElementById('scheduleEmployeeId');
        const nameEl = document.getElementById('scheduleEmployeeName');
        if (idInput) idInput.value = employeeId || '';
        if (nameEl) nameEl.textContent = employeeName || 'Selected employee';
        renderEmployeeSchedules(employeeId);
    });
});

document.querySelectorAll('.js-profile-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        const modal = tab.closest('.portal-modal__dialog');
        if (!modal) return;

        modal.querySelectorAll('.js-profile-tab').forEach(function (b) {
            b.classList.remove('is-active');
        });
        modal.querySelectorAll('.js-profile-panel').forEach(function (panel) {
            panel.style.display = 'none';
        });

        const targetId = tab.getAttribute('data-target');
        const target = document.getElementById(targetId);
        tab.classList.add('is-active');
        if (target) target.style.display = 'block';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
