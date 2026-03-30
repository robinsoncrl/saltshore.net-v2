<?php
/**
 * Saltshore Owner Portal - FinPro Module
 */

require_once 'config.php';

$page_title = is_employee_login() ? 'FinPro - My Pay & Expenses' : 'FinPro - Invoicing & Earnings';
$current_page = 'finpro';

include 'includes/header.php';

$is_employee = is_employee_login();
$can_manage = is_management_user();
$employee_id = (int)($_SESSION['employee_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'submit_expense_request' && $is_employee) {
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
        $expense_type = trim($_POST['expense_type'] ?? 'Expense');
        $amount = max(0, (float)($_POST['amount'] ?? 0));
        $receipt_reference = trim($_POST['receipt_reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($employee_id <= 0 || $amount <= 0) {
            $_SESSION['flash_success'] = 'Valid expense amount is required.';
            header('Location: finpro.php');
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO expense_requests (user_id, employee_id, request_date, expense_type, amount, receipt_reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $employee_id,
            $expense_date,
            $expense_type,
            $amount,
            $receipt_reference !== '' ? $receipt_reference : null,
            $notes !== '' ? $notes : null
        ]);

        $_SESSION['flash_success'] = 'Expense reimbursement request submitted.';
        header('Location: finpro.php');
        exit;
    }

    if ($action === 'review_expense_request' && $can_manage) {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            $_SESSION['flash_success'] = 'Invalid review action.';
            header('Location: finpro.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE expense_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, current_login_identifier(), $request_id, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = 'Expense request updated.';
        header('Location: finpro.php');
        exit;
    }

    if (!$is_employee) {
        if ($action === 'update_rate') {
            $rate = max(0, (float)($_POST['hourly_rate'] ?? 0));
            $stmt = $pdo->prepare("INSERT INTO portal_settings (user_id, setting_key, setting_value)
                VALUES (?, 'hourly_rate', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$_SESSION['user_id'], $rate]);
            $_SESSION['flash_success'] = 'Hourly rate updated.';
            header('Location: finpro.php');
            exit;
        }

        if ($action === 'create_invoice') {
            $client = trim($_POST['client'] ?? 'Client');
            $description = trim($_POST['description'] ?? '');
            $hours = max(0, (float)($_POST['hours'] ?? 0));
            $rate = max(0, (float)($_POST['rate'] ?? 0));
            $amount = $hours * $rate;

            $stmt = $pdo->prepare("INSERT INTO invoices (user_id, client_name, description, hours, rate, amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'unpaid', NOW())");
            $stmt->execute([$_SESSION['user_id'], $client, $description, $hours, $rate, $amount]);
            $_SESSION['flash_success'] = 'Invoice created.';
            header('Location: finpro.php');
            exit;
        }

        if ($action === 'mark_paid') {
            $invoice_id = (int)($_POST['invoice_id'] ?? 0);

            $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$invoice_id, $_SESSION['user_id']]);

            $inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
            $inv_stmt->execute([$invoice_id, $_SESSION['user_id']]);
            $inv = $inv_stmt->fetch();

            if ($inv) {
                $tx_stmt = $pdo->prepare("INSERT INTO transactions (user_id, date, description, category, amount, reconciled, source)
                    VALUES (?, NOW(), ?, 'Invoice Income', ?, 1, 'finpro')");
                $tx_stmt->execute([
                    $_SESSION['user_id'],
                    'Invoice #' . $invoice_id . ' - ' . $inv['client_name'],
                    $inv['amount']
                ]);
            }

            $_SESSION['flash_success'] = 'Invoice marked as paid and sent to LedgerPro.';
            header('Location: finpro.php');
            exit;
        }

        if ($action === 'delete_invoice') {
            $invoice_id = (int)($_POST['invoice_id'] ?? 0);
            if ($invoice_id > 0) {
                $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND source = 'finpro' AND description LIKE ?")
                    ->execute([$_SESSION['user_id'], 'Invoice #' . $invoice_id . ' - %']);

                $pdo->prepare("DELETE FROM invoices WHERE id = ? AND user_id = ?")
                    ->execute([$invoice_id, $_SESSION['user_id']]);
                $_SESSION['flash_success'] = 'Invoice deleted across portal data.';
            }

            header('Location: finpro.php');
            exit;
        }
    }
}

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($is_employee) {
    $employee_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND user_id = ? LIMIT 1");
    $employee_stmt->execute([$employee_id, $_SESSION['user_id']]);
    $employee = $employee_stmt->fetch();

    if (!$employee) {
        echo '<div class="alert alert-error">Employee profile not found.</div>';
        include 'includes/footer.php';
        exit;
    }

    $hourly_rate = (float)($employee['hourly_rate'] ?? 0);

    $wtd_hours_stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0)
        FROM time_blocks
        WHERE user_id = ?
          AND employee_id = ?
          AND is_approved = 1
          AND YEARWEEK(start_time, 1) = YEARWEEK(CURDATE(), 1)");
    $wtd_hours_stmt->execute([$_SESSION['user_id'], $employee_id]);
    $wtd_seconds = (int)$wtd_hours_stmt->fetchColumn();
    $wtd_hours = round($wtd_seconds / 3600, 2);
    $wtd_pay = $wtd_hours * $hourly_rate;

    $hist_stmt = $pdo->prepare("SELECT DATE_FORMAT(start_time, '%x-W%v') AS week_key,
            COALESCE(SUM(duration_seconds), 0) AS seconds_total
        FROM time_blocks
        WHERE user_id = ?
          AND employee_id = ?
          AND is_approved = 1
        GROUP BY DATE_FORMAT(start_time, '%x-W%v')
        ORDER BY MIN(start_time) DESC
        LIMIT 12");
    $hist_stmt->execute([$_SESSION['user_id'], $employee_id]);
    $historical_weeks = $hist_stmt->fetchAll();

    $requests_stmt = $pdo->prepare("SELECT * FROM expense_requests WHERE user_id = ? AND employee_id = ? ORDER BY created_at DESC LIMIT 25");
    $requests_stmt->execute([$_SESSION['user_id'], $employee_id]);
    $expense_requests = $requests_stmt->fetchAll();
    ?>

    <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: var(--space-2xl);">
        <div class="kpi-card">
            <div class="kpi-label">Week to Date Hours</div>
            <div class="kpi-value"><?= number_format($wtd_hours, 2) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Week to Date Pay</div>
            <div class="kpi-value" style="color: var(--success);"><?= format_currency($wtd_pay) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Hourly Rate</div>
            <div class="kpi-value"><?= format_currency($hourly_rate) ?>/hr</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Pending Expenses</div>
            <div class="kpi-value" style="color: var(--warning);"><?= count(array_filter($expense_requests, fn($r) => $r['status'] === 'pending')) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1.1fr 1fr; gap: var(--space-xl); margin-bottom: var(--space-2xl);">
        <div class="data-table-container" style="padding: var(--space-xl);">
            <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Submit Expense / Reimbursement</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="submit_expense_request">
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-md);">
                    <div class="form-group">
                        <label for="expense_date">Date</label>
                        <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="expense_type">Type</label>
                        <input type="text" id="expense_type" name="expense_type" placeholder="Fuel, Supplies, Travel" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" id="amount" name="amount" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="receipt_reference">Receipt / Document Link</label>
                    <input type="text" id="receipt_reference" name="receipt_reference" placeholder="Receipt ID, file path, or link">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Reimbursement notes"></textarea>
                </div>
                <button type="submit" class="btn-primary">Submit Request</button>
            </form>
        </div>

        <div class="data-table-container" style="padding: var(--space-xl);">
            <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Historical Pay by Week</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Approved Hours</th>
                        <th>Estimated Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historical_weeks): ?>
                        <?php foreach ($historical_weeks as $week): ?>
                            <?php $week_hours = round(((int)$week['seconds_total']) / 3600, 2); ?>
                            <tr>
                                <td><?= htmlspecialchars($week['week_key']) ?></td>
                                <td><?= number_format($week_hours, 2) ?></td>
                                <td><?= format_currency($week_hours * $hourly_rate) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; opacity:0.6;">No approved time history yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-table-container">
        <div class="data-table-header">
            <h3>My Expense Requests</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Receipt</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expense_requests): ?>
                    <?php foreach ($expense_requests as $req): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                            <td><?= htmlspecialchars($req['expense_type']) ?></td>
                            <td><?= format_currency((float)$req['amount']) ?></td>
                            <td><?= htmlspecialchars($req['receipt_reference'] ?? '—') ?></td>
                            <td>
                                <?php if ($req['status'] === 'approved'): ?>
                                    <span class="pill-success">Approved</span>
                                <?php elseif ($req['status'] === 'rejected'): ?>
                                    <span class="pill-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="pill-muted">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($req['notes'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; opacity:0.6;">No requests submitted yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    include 'includes/footer.php';
    exit;
}

$current_month = date('Y-m');

$rate_stmt = $pdo->prepare("SELECT setting_value FROM portal_settings WHERE user_id = ? AND setting_key = 'hourly_rate'");
$rate_stmt->execute([$_SESSION['user_id']]);
$hourly_rate = (float)($rate_stmt->fetchColumn() ?: 0);

$hrs_stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0)
    FROM time_blocks
    WHERE user_id = ?
      AND DATE_FORMAT(start_time, '%Y-%m') = ?
      AND is_billable = 1");
$hrs_stmt->execute([$_SESSION['user_id'], $current_month]);
$billable_seconds = (int)$hrs_stmt->fetchColumn();
$billable_hours = round($billable_seconds / 3600, 2);
$estimated_earnings = $billable_hours * $hourly_rate;

$inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$inv_stmt->execute([$_SESSION['user_id']]);
$invoices = $inv_stmt->fetchAll();

$total_invoiced = array_sum(array_column($invoices, 'amount'));
$total_paid = array_sum(array_column(array_filter($invoices, fn($i) => $i['status'] === 'paid'), 'amount'));
$total_unpaid = $total_invoiced - $total_paid;

$expense_review_stmt = $pdo->prepare("SELECT er.*, e.full_name
    FROM expense_requests er
    INNER JOIN employees e ON e.id = er.employee_id
    WHERE er.user_id = ?
    ORDER BY er.created_at DESC
    LIMIT 40");
$expense_review_stmt->execute([$_SESSION['user_id']]);
$expense_review = $expense_review_stmt->fetchAll();
?>

<?php if ($flash): ?>
<div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: var(--space-2xl);">
    <div class="kpi-card">
        <div class="kpi-label">Billable Hours (MTD)</div>
        <div class="kpi-value"><?= number_format($billable_hours, 1) ?> hrs</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Estimated Earnings</div>
        <div class="kpi-value" style="color: var(--success);"><?= format_currency($estimated_earnings) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Invoiced</div>
        <div class="kpi-value"><?= format_currency($total_invoiced) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Outstanding</div>
        <div class="kpi-value" style="color: var(--warning);"><?= format_currency($total_unpaid) ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl); margin-bottom: var(--space-2xl);">
    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Hourly Rate</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update_rate">
            <div class="form-group">
                <label for="hourly_rate">Default Rate ($/hr)</label>
                <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01" value="<?= htmlspecialchars($hourly_rate) ?>" placeholder="e.g. 75.00">
            </div>
            <button type="submit" class="btn-primary">Save Rate</button>
        </form>
    </div>

    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Earnings Calculator</h3>
        <div class="form-group">
            <label for="calc-rate">Rate ($/hr)</label>
            <input type="number" id="calc-rate" min="0" step="0.01" value="<?= htmlspecialchars($hourly_rate) ?>" placeholder="Rate">
        </div>
        <div class="form-group">
            <label for="calc-hours">Hours</label>
            <input type="number" id="calc-hours" min="0" step="0.1" value="<?= htmlspecialchars($billable_hours) ?>" placeholder="Hours">
        </div>
        <div style="font-size: 0.85rem; color: var(--color-secondary); margin-bottom: var(--space-sm);">Total</div>
        <div id="calc-result" style="font-size: 2rem; font-weight: 500; color: var(--color-primary);">$0.00</div>
    </div>
</div>

<div class="data-table-container" style="padding: var(--space-xl); margin-bottom: var(--space-2xl);">
    <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">New Invoice</h3>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="create_invoice">
        <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr; gap: var(--space-lg); align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="client">Client / Description</label>
                <input type="text" id="client" name="client" placeholder="Client name" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="description">Notes</label>
                <input type="text" id="description" name="description" placeholder="Optional notes">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="hours">Hours</label>
                <input type="number" id="hours" name="hours" min="0" step="0.1" value="<?= htmlspecialchars($billable_hours) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="rate">Rate ($/hr)</label>
                <input type="number" id="rate" name="rate" min="0" step="0.01" value="<?= htmlspecialchars($hourly_rate) ?>" required>
            </div>
        </div>
        <div style="margin-top: var(--space-lg);">
            <button type="submit" class="btn-primary">Create Invoice</button>
        </div>
    </form>
</div>

<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Invoice History</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Client</th>
                <th>Description</th>
                <th>Hours</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($invoices): ?>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td>#<?= $inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['client_name']) ?></td>
                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($inv['description'] ?? '—') ?></td>
                        <td><?= number_format($inv['hours'], 1) ?></td>
                        <td><?= format_currency($inv['rate']) ?></td>
                        <td><strong><?= format_currency($inv['amount']) ?></strong></td>
                        <td><?= $inv['status'] === 'paid' ? '<span style="color: var(--success); font-weight: 500;">Approved/Paid</span>' : '<span style="color: var(--warning);">Unpaid</span>' ?></td>
                        <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this invoice from FinPro and linked LedgerPro entries?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_invoice">
                                <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                <button type="submit" class="btn-icon-danger" title="Delete invoice" aria-label="Delete invoice">&#128465;</button>
                            </form>
                            <?php if ($inv['status'] !== 'paid'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                    <button type="submit" class="btn-success" style="padding: 6px 12px; font-size: 0.85rem;">Mark Paid</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align: center; opacity: 0.5;">No invoices yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="data-table-container">
    <div class="data-table-header">
        <h3>Employee Expense Requests</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Receipt</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($expense_review): ?>
                <?php foreach ($expense_review as $req): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                        <td><?= htmlspecialchars($req['full_name']) ?></td>
                        <td><?= htmlspecialchars($req['expense_type']) ?></td>
                        <td><?= format_currency((float)$req['amount']) ?></td>
                        <td><?= htmlspecialchars($req['receipt_reference'] ?? '—') ?></td>
                        <td>
                            <?php if ($req['status'] === 'approved'): ?>
                                <span class="pill-success">Approved</span>
                            <?php elseif ($req['status'] === 'rejected'): ?>
                                <span class="pill-danger">Rejected</span>
                            <?php else: ?>
                                <span class="pill-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex; gap:6px;">
                            <?php if ($req['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="review_expense_request">
                                    <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn-success" style="padding: 5px 10px;">Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="review_expense_request">
                                    <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn-danger" style="padding: 5px 10px;">Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="opacity:0.7; font-size:0.85rem;">Reviewed by <?= htmlspecialchars($req['reviewed_by'] ?? '—') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; opacity: 0.5;">No expense requests submitted.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
