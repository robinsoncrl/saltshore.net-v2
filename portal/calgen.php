<?php
/**
 * Saltshore Owner Portal - CalGen Module
 */

$page_title = 'CalGen - Time Tracking';
$current_page = 'calgen';

include 'includes/header.php';

$can_manage = is_management_user();
$is_employee = is_employee_login();
$employee_id = (int)($_SESSION['employee_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'punch_in') {
        $stmt = $pdo->prepare("INSERT INTO time_blocks (user_id, employee_id, start_time, category, submitted_for_approval, is_approved) VALUES (?, ?, NOW(), ?, ?, ?)");
        $submitted = $is_employee ? 1 : 0;
        $approved = $is_employee ? 0 : 1;
        $stmt->execute([
            $_SESSION['user_id'],
            $is_employee ? $employee_id : null,
            $_POST['category'] ?? 'General',
            $submitted,
            $approved
        ]);
        $_SESSION['active_timer'] = $pdo->lastInsertId();
        $_SESSION['flash_success'] = 'Punch-in recorded.';
        header('Location: calgen.php');
        exit;
    }

    if ($action === 'punch_out' && !empty($_SESSION['active_timer'])) {
        $block_id = (int)$_SESSION['active_timer'];

        $update = $pdo->prepare("UPDATE time_blocks
            SET end_time = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                notes = ?,
                submitted_for_approval = ?
            WHERE id = ? AND user_id = ?");
        $update->execute([
            $_POST['notes'] ?? '',
            $is_employee ? 1 : 0,
            $block_id,
            $_SESSION['user_id']
        ]);

        $dur_stmt = $pdo->prepare("SELECT duration_seconds FROM time_blocks WHERE id = ? AND user_id = ? LIMIT 1");
        $dur_stmt->execute([$block_id, $_SESSION['user_id']]);
        $worked_seconds = (int)$dur_stmt->fetchColumn();

        unset($_SESSION['active_timer']);
        $_SESSION['flash_success'] = 'Punch-out saved. Time worked: ' . format_duration($worked_seconds) . '.';
        header('Location: calgen.php');
        exit;
    }

    if ($action === 'submit_for_approval' && $is_employee) {
        $block_id = (int)($_POST['block_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE time_blocks SET submitted_for_approval = 1 WHERE id = ? AND user_id = ? AND employee_id = ?");
        $stmt->execute([$block_id, $_SESSION['user_id'], $employee_id]);
        $_SESSION['flash_success'] = 'Time entry submitted for management approval.';
        header('Location: calgen.php');
        exit;
    }

    if ($action === 'approve_time' && $can_manage) {
        $block_id = (int)($_POST['block_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE time_blocks SET is_approved = 1, approved_at = NOW(), approved_by = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([current_login_identifier(), $block_id, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = 'Time entry approved.';
        header('Location: calgen.php');
        exit;
    }

    if ($action === 'delete_block' && $can_manage) {
        $block_id = (int)($_POST['block_id'] ?? 0);
        if ($block_id > 0) {
            $pdo->prepare("DELETE FROM time_blocks WHERE id = ? AND user_id = ?")
                ->execute([$block_id, $_SESSION['user_id']]);
            if (!empty($_SESSION['active_timer']) && (int)$_SESSION['active_timer'] === $block_id) {
                unset($_SESSION['active_timer']);
            }
            $_SESSION['flash_success'] = 'Time block deleted.';
        }
        header('Location: calgen.php');
        exit;
    }

    if ($action === 'create_schedule' && $can_manage) {
        $target_employee_id = (int)($_POST['employee_id'] ?? 0);
        $schedule_date = $_POST['schedule_date'] ?? '';
        $shift_start = $_POST['shift_start'] ?? '';
        $shift_end = $_POST['shift_end'] ?? '';
        $notes = trim($_POST['schedule_notes'] ?? '');

        if ($target_employee_id > 0 && $schedule_date !== '' && $shift_start !== '' && $shift_end !== '') {
            $stmt = $pdo->prepare("INSERT INTO work_schedules (user_id, employee_id, schedule_date, shift_start, shift_end, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $target_employee_id,
                $schedule_date,
                $shift_start,
                $shift_end,
                $notes !== '' ? $notes : null
            ]);
            $_SESSION['flash_success'] = 'Work schedule created.';
        } else {
            $_SESSION['flash_success'] = 'Please complete all schedule fields.';
        }

        header('Location: calgen.php');
        exit;
    }

    if ($action === 'delete_schedule' && $can_manage) {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $pdo->prepare("DELETE FROM work_schedules WHERE id = ? AND user_id = ?")
                ->execute([$schedule_id, $_SESSION['user_id']]);
            $_SESSION['flash_success'] = 'Schedule deleted.';
        }
        header('Location: calgen.php');
        exit;
    }

    header('Location: calgen.php');
    exit;
}

$active_timer = null;
if (!empty($_SESSION['active_timer'])) {
    $active_stmt = $pdo->prepare("SELECT * FROM time_blocks WHERE id = ? AND user_id = ? AND end_time IS NULL");
    $active_stmt->execute([$_SESSION['active_timer'], $_SESSION['user_id']]);
    $active_timer = $active_stmt->fetch();
    if (!$active_timer) {
        unset($_SESSION['active_timer']);
    }
}

$current_month = date('Y-m');
if ($is_employee) {
    $blocks_stmt = $pdo->prepare("SELECT tb.*
        FROM time_blocks tb
        WHERE tb.user_id = ?
          AND tb.employee_id = ?
          AND DATE_FORMAT(tb.start_time, '%Y-%m') = ?
        ORDER BY tb.start_time DESC");
    $blocks_stmt->execute([$_SESSION['user_id'], $employee_id, $current_month]);
} else {
    $blocks_stmt = $pdo->prepare("SELECT tb.*, e.full_name AS employee_name
        FROM time_blocks tb
        LEFT JOIN employees e ON e.id = tb.employee_id
        WHERE tb.user_id = ?
          AND DATE_FORMAT(tb.start_time, '%Y-%m') = ?
        ORDER BY tb.start_time DESC");
    $blocks_stmt->execute([$_SESSION['user_id'], $current_month]);
}
$time_blocks = $blocks_stmt->fetchAll();

$total_seconds = array_sum(array_map(function ($block) {
    return (int)$block['duration_seconds'];
}, $time_blocks));
$total_hours = round($total_seconds / 3600, 2);

$billable_stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0) as total
    FROM time_blocks
    WHERE user_id = ?
      AND DATE_FORMAT(start_time, '%Y-%m') = ?
      AND is_billable = 1" . ($is_employee ? " AND employee_id = ?" : ""));
if ($is_employee) {
    $billable_stmt->execute([$_SESSION['user_id'], $current_month, $employee_id]);
} else {
    $billable_stmt->execute([$_SESSION['user_id'], $current_month]);
}
$billable_seconds = (int)$billable_stmt->fetchColumn();
$billable_hours = round($billable_seconds / 3600, 2);

$employees_stmt = $pdo->prepare("SELECT id, full_name, role_title FROM employees WHERE user_id = ? AND status = 'active' ORDER BY full_name ASC");
$employees_stmt->execute([$_SESSION['user_id']]);
$employees = $employees_stmt->fetchAll();

$schedules_stmt = $pdo->prepare("SELECT ws.*, e.full_name
    FROM work_schedules ws
    INNER JOIN employees e ON e.id = ws.employee_id
    WHERE ws.user_id = ?
      AND ws.schedule_date >= CURDATE()" . ($is_employee ? " AND ws.employee_id = ?" : "") . "
    ORDER BY ws.schedule_date ASC, ws.shift_start ASC
    LIMIT 50");
if ($is_employee) {
    $schedules_stmt->execute([$_SESSION['user_id'], $employee_id]);
} else {
    $schedules_stmt->execute([$_SESSION['user_id']]);
}
$upcoming_schedules = $schedules_stmt->fetchAll();

$pending_approval_blocks = [];
if ($can_manage) {
    $pending_stmt = $pdo->prepare("SELECT tb.*, e.full_name AS employee_name
        FROM time_blocks tb
        INNER JOIN employees e ON e.id = tb.employee_id
        WHERE tb.user_id = ?
          AND tb.submitted_for_approval = 1
          AND tb.is_approved = 0
          AND tb.end_time IS NOT NULL
        ORDER BY tb.start_time DESC
        LIMIT 25");
    $pending_stmt->execute([$_SESSION['user_id']]);
    $pending_approval_blocks = $pending_stmt->fetchAll();
}

$employee_profile = null;
if ($is_employee && $employee_id > 0) {
    $profile_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND user_id = ? LIMIT 1");
    $profile_stmt->execute([$employee_id, $_SESSION['user_id']]);
    $employee_profile = $profile_stmt->fetch();
}

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div style="display:flex; gap: var(--space-md); flex-wrap: wrap; margin-bottom: var(--space-lg);">
    <button type="button" class="btn-primary" data-modal-open="timeClockModal">Time Clock</button>
    <?php if ($is_employee): ?>
        <button type="button" class="btn-secondary" data-modal-open="myScheduleModal">My Schedule</button>
        <?php if ($employee_profile): ?>
            <button type="button" class="btn-secondary" data-modal-open="myProfileModal">My Profile</button>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($can_manage && !empty($employees)): ?>
        <button type="button" class="btn-secondary" data-modal-open="scheduleModal">+ Schedule Employee</button>
    <?php endif; ?>
</div>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="kpi-card">
        <div class="kpi-label">Total Hours</div>
        <div class="kpi-value"><?= number_format($total_hours, 1) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Billable Hours</div>
        <div class="kpi-value" style="color: var(--success);"><?= number_format($billable_hours, 1) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Time Blocks</div>
        <div class="kpi-value"><?= count($time_blocks) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Pending Approval</div>
        <div class="kpi-value" style="color: var(--warning);">
            <?= count(array_filter($time_blocks, function ($b) { return (int)$b['submitted_for_approval'] === 1 && (int)$b['is_approved'] === 0 && !empty($b['end_time']); })) ?>
        </div>
    </div>
</div>

<div class="data-table-container" style="margin-top: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Time Blocks - <?= date('F Y') ?></h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <?php if (!$is_employee): ?><th>Employee</th><?php endif; ?>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th>Duration</th>
                <th>Category</th>
                <th>Approval</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($time_blocks)): ?>
                <?php foreach ($time_blocks as $block): ?>
                    <tr>
                        <?php if (!$is_employee): ?>
                            <td><?= htmlspecialchars($block['employee_name'] ?? 'Owner/Manager') ?></td>
                        <?php endif; ?>
                        <td><?= date('M d, Y', strtotime($block['start_time'])) ?></td>
                        <td><?= date('g:i A', strtotime($block['start_time'])) ?></td>
                        <td><?= $block['end_time'] ? date('g:i A', strtotime($block['end_time'])) : '<em style="opacity:0.5">Active</em>' ?></td>
                        <td><strong><?= format_duration((int)$block['duration_seconds']) ?></strong></td>
                        <td><?= htmlspecialchars($block['category']) ?></td>
                        <td>
                            <?php if ((int)$block['is_approved'] === 1): ?>
                                <span class="pill-success">Approved</span>
                            <?php elseif ((int)$block['submitted_for_approval'] === 1): ?>
                                <span class="pill-muted">Pending</span>
                            <?php else: ?>
                                <span class="pill-muted">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($block['notes'] ?? '') ?></td>
                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                            <?php if ($is_employee && !empty($block['end_time']) && (int)$block['submitted_for_approval'] === 0): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="submit_for_approval">
                                    <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">
                                    <button type="submit" class="btn-secondary" style="padding: 6px 10px;">Submit</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($can_manage): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this time block?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete_block">
                                    <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">
                                    <button type="submit" class="btn-icon-danger" title="Delete time block" aria-label="Delete time block">&#128465;</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?= $is_employee ? '8' : '9' ?>" style="text-align:center; opacity:0.6;">No time blocks this month.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($can_manage): ?>
<div class="data-table-container" style="margin-top: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Management Time Approval Queue</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Shift</th>
                <th>Duration</th>
                <th>Category</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($pending_approval_blocks)): ?>
                <?php foreach ($pending_approval_blocks as $pending): ?>
                    <tr>
                        <td><?= htmlspecialchars($pending['employee_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($pending['start_time'])) ?></td>
                        <td><?= date('g:i A', strtotime($pending['start_time'])) ?> - <?= date('g:i A', strtotime($pending['end_time'])) ?></td>
                        <td><?= format_duration((int)$pending['duration_seconds']) ?></td>
                        <td><?= htmlspecialchars($pending['category']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="approve_time">
                                <input type="hidden" name="block_id" value="<?= (int)$pending['id'] ?>">
                                <button type="submit" class="btn-success" style="padding:6px 12px;">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; opacity:0.6;">No pending entries to approve.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="data-table-container" style="margin-top: var(--space-2xl);">
    <div class="data-table-header">
        <h3><?= $is_employee ? 'My Upcoming Schedule' : 'Upcoming Employee Schedules' ?></h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <?php if (!$is_employee): ?><th>Employee</th><?php endif; ?>
                <th>Shift</th>
                <th>Notes</th>
                <?php if ($can_manage): ?><th>Action</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($upcoming_schedules)): ?>
                <?php foreach ($upcoming_schedules as $schedule): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($schedule['schedule_date'])) ?></td>
                        <?php if (!$is_employee): ?><td><?= htmlspecialchars($schedule['full_name']) ?></td><?php endif; ?>
                        <td><?= date('g:i A', strtotime($schedule['shift_start'])) ?> - <?= date('g:i A', strtotime($schedule['shift_end'])) ?></td>
                        <td><?= htmlspecialchars($schedule['notes'] ?? '—') ?></td>
                        <?php if ($can_manage): ?>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this schedule?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                                <button type="submit" class="btn-icon-danger" title="Delete schedule" aria-label="Delete schedule">&#128465;</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?= $can_manage ? ($is_employee ? '4' : '5') : ($is_employee ? '3' : '4') ?>" style="text-align:center; opacity:0.6;">No upcoming schedules.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="portal-modal" id="timeClockModal" aria-hidden="true">
    <div class="portal-modal__backdrop" data-modal-close></div>
    <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="timeClockModalTitle">
        <div class="portal-modal__header">
            <h3 id="timeClockModalTitle">Time Clock</h3>
            <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="portal-modal__body">
            <?php if ($active_timer): ?>
                <div class="timer-widget" style="margin-bottom: 0;">
                    <h3>Timer Running</h3>
                    <div class="timer-display" id="timerDisplay">00:00:00</div>
                    <p style="opacity: 0.9; margin-bottom: var(--space-xl);">
                        Category: <strong><?= htmlspecialchars($active_timer['category']) ?></strong><br>
                        Started: <?= date('g:i A', strtotime($active_timer['start_time'])) ?>
                    </p>
                    <form method="POST" class="timer-controls">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="punch_out">
                        <textarea name="notes" placeholder="Notes (optional)" rows="2" style="width: 100%; margin-bottom: var(--space-md); padding: var(--space-md); border-radius: var(--radius-sm); border: 1px solid var(--panel-border);"></textarea>
                        <button type="submit" class="btn-primary">Punch Out</button>
                    </form>
                </div>
                <script>
                const startTime = new Date('<?= $active_timer['start_time'] ?>').getTime();
                function updateTimer() {
                    const now = Date.now();
                    const elapsed = Math.floor((now - startTime) / 1000);
                    const hours = Math.floor(elapsed / 3600);
                    const minutes = Math.floor((elapsed % 3600) / 60);
                    const seconds = elapsed % 60;
                    const timerEl = document.getElementById('timerDisplay');
                    if (timerEl) {
                        timerEl.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    }
                }
                setInterval(updateTimer, 1000);
                updateTimer();
                </script>
            <?php else: ?>
                <div class="timer-widget" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%); margin-bottom: 0;">
                    <h3>Ready to Track Time</h3>
                    <div class="timer-display">00:00:00</div>
                    <form method="POST" class="timer-controls">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="punch_in">
                        <select name="category" required style="padding: 12px 16px; border-radius: var(--radius-sm); border: none; margin-right: var(--space-md); font-family: var(--font-primary);">
                            <option value="Client Work">Client Work</option>
                            <option value="Admin">Admin</option>
                            <option value="Development">Development</option>
                            <option value="Marketing">Marketing</option>
                            <option value="General">General</option>
                        </select>
                        <button type="submit" class="btn-primary" style="background: white; color: var(--success);">Punch In</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($can_manage && !empty($employees)): ?>
<div class="portal-modal" id="scheduleModal" aria-hidden="true">
    <div class="portal-modal__backdrop" data-modal-close></div>
    <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle">
        <div class="portal-modal__header">
            <h3 id="scheduleModalTitle">Create Work Schedule</h3>
            <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="POST" class="portal-modal__body">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create_schedule">

            <div class="form-group">
                <label for="employee_id">Employee</label>
                <select id="employee_id" name="employee_id" required>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?><?= !empty($emp['role_title']) ? ' - ' . htmlspecialchars($emp['role_title']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
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
                <button type="submit" class="btn-primary">Create Schedule</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($is_employee): ?>
<div class="portal-modal" id="myScheduleModal" aria-hidden="true">
    <div class="portal-modal__backdrop" data-modal-close></div>
    <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="myScheduleModalTitle" style="max-width: 860px;">
        <div class="portal-modal__header">
            <h3 id="myScheduleModalTitle">My Schedule Calendar</h3>
            <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="portal-modal__body">
            <div style="display:grid; gap:10px;">
                <?php if (!empty($upcoming_schedules)): ?>
                    <?php foreach ($upcoming_schedules as $item): ?>
                        <div style="border:1px solid var(--panel-border); border-radius:10px; padding:10px 12px;">
                            <strong><?= date('l, M d, Y', strtotime($item['schedule_date'])) ?></strong>
                            <div style="opacity:0.75; font-size:0.9rem;"><?= date('g:i A', strtotime($item['shift_start'])) ?> - <?= date('g:i A', strtotime($item['shift_end'])) ?></div>
                            <div style="opacity:0.7; font-size:0.85rem;"><?= htmlspecialchars($item['notes'] ?? 'No notes') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="opacity:0.7;">No scheduled shifts yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($is_employee && $employee_profile): ?>
    <?php
    $employee_month_hours = round(array_sum(array_map(function ($b) {
        return (int)$b['duration_seconds'];
    }, $time_blocks)) / 3600, 1);
    $approved_hours = round(array_sum(array_map(function ($b) {
        return ((int)$b['is_approved'] === 1) ? (int)$b['duration_seconds'] : 0;
    }, $time_blocks)) / 3600, 1);
    ?>
    <div class="portal-modal" id="myProfileModal" aria-hidden="true">
        <div class="portal-modal__backdrop" data-modal-close></div>
        <div class="portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="myProfileModalTitle" style="max-width: 900px;">
            <div class="portal-modal__header">
                <h3 id="myProfileModalTitle">My Employee Profile</h3>
                <button type="button" class="portal-modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="profile-tabs" style="display:flex; gap:8px; padding: 16px 20px; border-bottom: 1px solid var(--panel-border);">
                <button type="button" class="btn-secondary js-my-profile-tab is-active" data-target="my-profile-contact">Contact Sheet</button>
                <button type="button" class="btn-secondary js-my-profile-tab" data-target="my-profile-kpi">Performance KPI</button>
            </div>

            <div class="portal-modal__body js-my-profile-panel" id="my-profile-contact">
                <div style="display:grid; grid-template-columns: 120px 1fr; gap: var(--space-lg); align-items:start;">
                    <div style="width: 110px; height: 110px; border-radius: 50%; overflow:hidden; border:1px solid var(--panel-border); background:#f3f4f6;">
                        <img src="<?= htmlspecialchars($employee_profile['photo_url'] ?: '../assets/img/SSS_logo_2.png') ?>" alt="Employee photo" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <div>
                        <h4 style="margin-bottom:6px;"><?= htmlspecialchars($employee_profile['full_name']) ?></h4>
                        <div style="opacity:0.78;"><?= htmlspecialchars($employee_profile['role_title'] ?? 'Employee') ?></div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); margin-top: var(--space-lg);">
                    <div><strong>Email:</strong> <?= htmlspecialchars($employee_profile['email'] ?? '—') ?></div>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($employee_profile['phone'] ?? '—') ?></div>
                    <div><strong>Address:</strong> <?= htmlspecialchars($employee_profile['address_line1'] ?? '—') ?></div>
                    <div><strong>City/State/Zip:</strong> <?= htmlspecialchars($employee_profile['city_state_zip'] ?? '—') ?></div>
                    <div><strong>Emergency Contact:</strong> <?= htmlspecialchars($employee_profile['emergency_contact'] ?? '—') ?></div>
                    <div><strong>Start Date:</strong> <?= !empty($employee_profile['start_date']) ? date('M d, Y', strtotime($employee_profile['start_date'])) : '—' ?></div>
                </div>
            </div>

            <div class="portal-modal__body js-my-profile-panel" id="my-profile-kpi" style="display:none;">
                <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="kpi-card">
                        <div class="kpi-label">Hours This Month</div>
                        <div class="kpi-value"><?= number_format($employee_month_hours, 1) ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Approved Hours</div>
                        <div class="kpi-value" style="color: var(--success);"><?= number_format($approved_hours, 1) ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pending Approval</div>
                        <div class="kpi-value" style="color: var(--warning);"><?= count(array_filter($time_blocks, function ($b) { return (int)$b['submitted_for_approval'] === 1 && (int)$b['is_approved'] === 0; })) ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Upcoming Shifts</div>
                        <div class="kpi-value"><?= count($upcoming_schedules) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.querySelectorAll('.js-my-profile-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.js-my-profile-tab').forEach(function (btn) {
                btn.classList.remove('is-active');
            });
            document.querySelectorAll('.js-my-profile-panel').forEach(function (panel) {
                panel.style.display = 'none';
            });
            tab.classList.add('is-active');
            const target = document.getElementById(tab.getAttribute('data-target'));
            if (target) {
                target.style.display = 'block';
            }
        });
    });
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
