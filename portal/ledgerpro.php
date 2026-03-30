<?php
/**
 * Saltshore Owner Portal — LedgerPro Module
 * Transactions, CSV import, and reconciliation
 */

$page_title   = 'LedgerPro — Ledger & Reconciliation';
$current_page = 'ledger';

include 'includes/header.php';

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';

    // Manual transaction entry
    if ($action === 'add_transaction') {
        $date        = $_POST['date']        ?? date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category']    ?? 'Uncategorized');
        $amount      = (float)($_POST['amount']   ?? 0);
        $type        = $_POST['type'] ?? 'expense';
        if ($type === 'expense' && $amount > 0) $amount = -$amount;

        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, date, description, category, amount, reconciled, source)
            VALUES (?, ?, ?, ?, ?, 0, 'manual')
        ");
        $stmt->execute([$_SESSION['user_id'], $date, $description, $category, $amount]);
        $_SESSION['flash_success'] = 'Transaction added.';
        header('Location: ledgerpro.php');
        exit;
    }

    // CSV import
    if ($action === 'import_csv' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Upload error.';
            header('Location: ledgerpro.php');
            exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $_SESSION['flash_error'] = 'Only CSV files are supported.';
            header('Location: ledgerpro.php');
            exit;
        }

        $handle = fopen($file['tmp_name'], 'r');
        $inserted = 0;
        $skipped  = 0;
        $header_skipped = false;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip header row
            if (!$header_skipped) {
                $header_skipped = true;
                // auto-detect: if first cell looks like a date, don't skip
                if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $row[0] ?? '')) {
                    continue;
                }
            }

            // Expected columns: date, description, amount [, category]
            if (count($row) < 3) { $skipped++; continue; }

            $date        = date('Y-m-d', strtotime($row[0]));
            $description = substr(trim($row[1]), 0, 255);
            $amount      = (float)str_replace(['$', ',', '"'], '', $row[2]);
            $category    = isset($row[3]) ? substr(trim($row[3]), 0, 100) : 'Import';

            if ($date === '1970-01-01') { $skipped++; continue; }

            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, date, description, category, amount, reconciled, source)
                VALUES (?, ?, ?, ?, ?, 0, 'csv')
            ");
            $stmt->execute([$_SESSION['user_id'], $date, $description, $category, $amount]);
            $inserted++;
        }
        fclose($handle);

        $_SESSION['flash_success'] = "Imported {$inserted} transactions" . ($skipped ? ", skipped {$skipped} rows." : '.');
        header('Location: ledgerpro.php');
        exit;
    }

    // Reconcile a transaction
    if ($action === 'reconcile') {
        $tx_id = (int)($_POST['tx_id'] ?? 0);
        $pdo->prepare("UPDATE transactions SET reconciled = 1 WHERE id = ? AND user_id = ?")
            ->execute([$tx_id, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = 'Transaction reconciled.';
        header('Location: ledgerpro.php');
        exit;
    }

    // Delete a transaction
    if ($action === 'delete_tx') {
        $tx_id = (int)($_POST['tx_id'] ?? 0);
        $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?")
            ->execute([$tx_id, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = 'Transaction deleted.';
        header('Location: ledgerpro.php');
        exit;
    }

    // Generate paystub document
    if ($action === 'create_paystub') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';

        if ($employee_id <= 0 || $period_start === '' || $period_end === '') {
            $_SESSION['flash_error'] = 'Employee and pay period are required for paystub generation.';
            header('Location: ledgerpro.php');
            exit;
        }

        $emp_stmt = $pdo->prepare("SELECT id, full_name, hourly_rate FROM employees WHERE id = ? AND user_id = ? LIMIT 1");
        $emp_stmt->execute([$employee_id, $_SESSION['user_id']]);
        $employee = $emp_stmt->fetch();

        if (!$employee) {
            $_SESSION['flash_error'] = 'Employee record not found.';
            header('Location: ledgerpro.php');
            exit;
        }

        $hours_stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0)
            FROM time_blocks
            WHERE user_id = ?
              AND employee_id = ?
              AND is_approved = 1
              AND DATE(start_time) BETWEEN ? AND ?");
        $hours_stmt->execute([$_SESSION['user_id'], $employee_id, $period_start, $period_end]);
        $approved_seconds = (int)$hours_stmt->fetchColumn();
        $approved_hours = round($approved_seconds / 3600, 2);

        $hourly_rate = (float)($employee['hourly_rate'] ?? 0);
        $gross_pay = round($approved_hours * $hourly_rate, 2);

        $revenue_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0)
            FROM invoices
            WHERE user_id = ?
              AND status = 'paid'
              AND DATE(paid_at) BETWEEN ? AND ?");
        $revenue_stmt->execute([$_SESSION['user_id'], $period_start, $period_end]);
        $business_revenue = (float)$revenue_stmt->fetchColumn();

        $insert = $pdo->prepare("INSERT INTO paystubs (user_id, employee_id, period_start, period_end, approved_hours, hourly_rate, gross_pay, business_revenue, generated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $_SESSION['user_id'],
            $employee_id,
            $period_start,
            $period_end,
            $approved_hours,
            $hourly_rate,
            $gross_pay,
            $business_revenue,
            current_login_identifier()
        ]);

        $new_paystub_id = (int)$pdo->lastInsertId();
        $_SESSION['flash_success'] = 'Paystub document created.';
        header('Location: ledgerpro.php?paystub=' . $new_paystub_id);
        exit;
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_type  = $_GET['type']  ?? 'all';  // all | income | expense

$where_clauses = ['user_id = ?'];
$params        = [$_SESSION['user_id']];

if ($filter_month) {
    $where_clauses[] = "DATE_FORMAT(date, '%Y-%m') = ?";
    $params[]        = $filter_month;
}
if ($filter_type === 'income') {
    $where_clauses[] = 'amount > 0';
} elseif ($filter_type === 'expense') {
    $where_clauses[] = 'amount < 0';
}

$where = 'WHERE ' . implode(' AND ', $where_clauses);

$tx_stmt = $pdo->prepare("SELECT * FROM transactions {$where} ORDER BY date DESC, id DESC");
$tx_stmt->execute($params);
$transactions = $tx_stmt->fetchAll();

// Monthly summary
$income   = array_sum(array_map(fn($t) => $t['amount'] > 0 ? $t['amount'] : 0, $transactions));
$expenses = array_sum(array_map(fn($t) => $t['amount'] < 0 ? abs($t['amount']) : 0, $transactions));
$net      = $income - $expenses;

// Unreconciled count
$unrec_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND reconciled = 0");
$unrec_stmt->execute([$_SESSION['user_id']]);
$unreconciled_count = (int)$unrec_stmt->fetchColumn();

// Last 6 months for cash-flow chart
$months_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m   = date('Y-m', strtotime("-{$i} months"));
    $lbl = date('M Y', strtotime("-{$i} months"));

    $inc_s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m')=? AND amount>0");
    $inc_s->execute([$_SESSION['user_id'], $m]);

    $exp_s = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m')=? AND amount<0");
    $exp_s->execute([$_SESSION['user_id'], $m]);

    $months_data[] = ['label' => $lbl, 'income' => (float)$inc_s->fetchColumn(), 'expenses' => (float)$exp_s->fetchColumn()];
}

$employees_stmt = $pdo->prepare("SELECT id, full_name, hourly_rate FROM employees WHERE user_id = ? AND status = 'active' ORDER BY full_name ASC");
$employees_stmt->execute([$_SESSION['user_id']]);
$employees = $employees_stmt->fetchAll();

$paystub_history_stmt = $pdo->prepare("SELECT p.*, e.full_name
    FROM paystubs p
    INNER JOIN employees e ON e.id = p.employee_id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 20");
$paystub_history_stmt->execute([$_SESSION['user_id']]);
$paystub_history = $paystub_history_stmt->fetchAll();

$selected_paystub = null;
$selected_paystub_id = (int)($_GET['paystub'] ?? 0);
if ($selected_paystub_id > 0) {
    $selected_stmt = $pdo->prepare("SELECT p.*, e.full_name, e.employee_code
        FROM paystubs p
        INNER JOIN employees e ON e.id = p.employee_id
        WHERE p.id = ? AND p.user_id = ?
        LIMIT 1");
    $selected_stmt->execute([$selected_paystub_id, $_SESSION['user_id']]);
    $selected_paystub = $selected_stmt->fetch();
}

$flash       = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- KPI Strip -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: var(--space-2xl);">
    <div class="kpi-card">
        <div class="kpi-label">Income (Month)</div>
        <div class="kpi-value" style="color: var(--success);"><?= format_currency($income) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Expenses (Month)</div>
        <div class="kpi-value" style="color: var(--danger);"><?= format_currency($expenses) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Net Cash Flow</div>
        <div class="kpi-value" style="color: <?= $net >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= format_currency($net) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Unreconciled</div>
        <div class="kpi-value" style="color: <?= $unreconciled_count > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
            <?= $unreconciled_count ?>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl); margin-bottom: var(--space-2xl);">
    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Create Paystub Document</h3>
        <p style="font-size: 0.88rem; opacity: 0.75; margin-bottom: var(--space-lg);">Builds from approved CalGen hours and paid FinPro revenue context.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create_paystub">
            <div class="form-group">
                <label for="paystub_employee_id">Employee</label>
                <select id="paystub_employee_id" name="employee_id" required>
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?><?= $emp['hourly_rate'] !== null ? ' - ' . format_currency((float)$emp['hourly_rate']) . '/hr' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div class="form-group">
                    <label for="period_start">Period Start</label>
                    <input type="date" id="period_start" name="period_start" required>
                </div>
                <div class="form-group">
                    <label for="period_end">Period End</label>
                    <input type="date" id="period_end" name="period_end" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Generate Paystub</button>
        </form>
    </div>

    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Paystub History</h3>
        <div style="display:grid; gap:8px; max-height: 300px; overflow:auto;">
            <?php if (!empty($paystub_history)): ?>
                <?php foreach ($paystub_history as $stub): ?>
                    <a href="ledgerpro.php?paystub=<?= (int)$stub['id'] ?>" style="display:flex; justify-content:space-between; gap:10px; text-decoration:none; color:inherit; border:1px solid var(--panel-border); border-radius:8px; padding:8px 10px;">
                        <span><strong><?= htmlspecialchars($stub['full_name']) ?></strong><br><span style="font-size:0.82rem; opacity:0.7;"><?= htmlspecialchars($stub['period_start']) ?> to <?= htmlspecialchars($stub['period_end']) ?></span></span>
                        <span style="font-weight:600;"><?= format_currency((float)$stub['gross_pay']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="opacity:0.6;">No paystubs generated yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($selected_paystub): ?>
<div class="data-table-container" id="paystubDocument" style="padding: var(--space-xl); margin-bottom: var(--space-2xl);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-bottom: var(--space-lg);">
        <div>
            <h3 style="color: var(--color-primary); margin-bottom: 6px;">Paystub #<?= (int)$selected_paystub['id'] ?></h3>
            <div style="opacity:0.75;">Employee: <strong><?= htmlspecialchars($selected_paystub['full_name']) ?></strong> (<?= htmlspecialchars($selected_paystub['employee_code'] ?? 'No ID') ?>)</div>
            <div style="opacity:0.75;">Pay Period: <?= htmlspecialchars($selected_paystub['period_start']) ?> to <?= htmlspecialchars($selected_paystub['period_end']) ?></div>
        </div>
        <button type="button" class="btn-secondary" onclick="window.print()">Print Document</button>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="kpi-card">
            <div class="kpi-label">Approved Hours</div>
            <div class="kpi-value"><?= number_format((float)$selected_paystub['approved_hours'], 2) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Hourly Rate</div>
            <div class="kpi-value"><?= format_currency((float)$selected_paystub['hourly_rate']) ?>/hr</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Gross Pay</div>
            <div class="kpi-value" style="color: var(--success);"><?= format_currency((float)$selected_paystub['gross_pay']) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Business Revenue Context</div>
            <div class="kpi-value"><?= format_currency((float)$selected_paystub['business_revenue']) ?></div>
        </div>
    </div>

    <div style="margin-top: var(--space-lg); opacity:0.75; font-size:0.85rem;">
        Generated by <?= htmlspecialchars($selected_paystub['generated_by']) ?> on <?= date('M d, Y g:i A', strtotime($selected_paystub['created_at'])) ?>.
    </div>
</div>
<?php endif; ?>

<!-- Cash Flow Chart -->
<div class="chart-container">
    <div class="chart-header">
        <h3>Cash Flow — Last 6 Months</h3>
        <p>Income vs Expenses</p>
    </div>
    <canvas id="cashFlowChart" height="70"></canvas>
</div>

<!-- Two column: import + add manual -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl); margin-bottom: var(--space-2xl);">

    <!-- CSV Import -->
    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Import CSV</h3>
        <p style="font-size: 0.9rem; opacity: 0.7; margin-bottom: var(--space-lg);">
            Expected columns: <code>date, description, amount[, category]</code>
        </p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="import_csv">
            <div class="form-group">
                <label for="csv-upload" id="csv-label" class="file-upload-label">Choose file…</label>
                <input type="file" id="csv-upload" name="csv_file" accept=".csv,.txt" required
                       style="display: none;">
            </div>
            <button type="submit" class="btn-primary">Import Transactions</button>
        </form>
    </div>

    <!-- Manual Entry -->
    <div class="data-table-container" style="padding: var(--space-xl);">
        <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Add Transaction</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add_transaction">
            <div class="form-group">
                <label for="tx-date">Date</label>
                <input type="date" id="tx-date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label for="tx-desc">Description</label>
                <input type="text" id="tx-desc" name="description" placeholder="Description" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-md);">
                <div class="form-group">
                    <label for="tx-type">Type</label>
                    <select id="tx-type" name="type">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tx-amount">Amount</label>
                    <input type="number" id="tx-amount" name="amount" min="0" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label for="tx-cat">Category</label>
                    <input type="text" id="tx-cat" name="category" placeholder="Category">
                </div>
            </div>
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </div>

</div>

<!-- Filters -->
<div style="display: flex; gap: var(--space-lg); align-items: center; margin-bottom: var(--space-xl);">
    <form method="GET" style="display: flex; gap: var(--space-md); align-items: center; flex-wrap: wrap;">
        <div class="form-group" style="margin: 0;">
            <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>"
                   style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm); font-family: var(--font-primary);">
        </div>
        <div class="form-group" style="margin: 0;">
            <select name="type" style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm); font-family: var(--font-primary);">
                <option value="all"     <?= $filter_type === 'all'     ? 'selected' : '' ?>>All</option>
                <option value="income"  <?= $filter_type === 'income'  ? 'selected' : '' ?>>Income</option>
                <option value="expense" <?= $filter_type === 'expense' ? 'selected' : '' ?>>Expenses</option>
            </select>
        </div>
        <button type="submit" class="btn-secondary">Filter</button>
    </form>
</div>

<!-- Transactions Table -->
<div class="data-table-container">
    <div class="data-table-header">
        <h3>Transactions — <?= date('F Y', strtotime($filter_month . '-01')) ?></h3>
        <span style="font-size: 0.85rem; opacity: 0.6;"><?= count($transactions) ?> records</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Source</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($transactions): ?>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($tx['date'])) ?></td>
                        <td><?= htmlspecialchars($tx['description']) ?></td>
                        <td><?= htmlspecialchars($tx['category'] ?? '—') ?></td>
                        <td style="font-weight: 500; color: <?= $tx['amount'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                            <?= format_currency($tx['amount']) ?>
                        </td>
                        <td style="font-size: 0.82rem; text-transform: capitalize; opacity: 0.7;">
                            <?= htmlspecialchars($tx['source'] ?? 'manual') ?>
                        </td>
                        <td>
                            <?= $tx['reconciled']
                                ? '<span style="color: var(--success);">✓ Reconciled</span>'
                                : '<span style="color: var(--warning);">Pending</span>'
                            ?>
                        </td>
                        <td style="display: flex; gap: var(--space-sm);">
                            <?php if (!$tx['reconciled']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="reconcile">
                                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                                    <button type="submit" class="btn-success"
                                            style="padding: 4px 10px; font-size: 0.82rem;">Reconcile</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_tx">
                                <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                                <button type="submit" class="btn-danger"
                                        data-confirm="Delete this transaction?"
                                        style="padding: 4px 10px; font-size: 0.82rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; opacity: 0.5;">No transactions for this period</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Cash Flow Chart
new Chart(document.getElementById('cashFlowChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($months_data, 'label')) ?>,
        datasets: [
            {
                label: 'Income',
                data: <?= json_encode(array_column($months_data, 'income')) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderRadius: 4
            },
            {
                label: 'Expenses',
                data: <?= json_encode(array_column($months_data, 'expenses')) ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => '$' + v.toLocaleString() }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
