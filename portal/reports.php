<?php
/**
 * Saltshore Owner Portal — Reports
 * Monthly / Quarterly / Custom date-range summaries with CSV export
 */

$page_title   = 'Reports';
$current_page = 'reports';

include 'includes/header.php';

// ── Determine date range ───────────────────────────────────────────────────
$period     = $_GET['period']     ?? 'month';
$date_from  = $_GET['date_from']  ?? date('Y-m-01');
$date_to    = $_GET['date_to']    ?? date('Y-m-t');

switch ($period) {
    case 'month':
        $selected_month = $_GET['selected_month'] ?? date('Y-m');
        $date_from = $selected_month . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
        $period_label = date('F Y', strtotime($date_from));
        break;
    case 'quarter':
        $q   = (int)ceil(date('n') / 3);
        $yr  = (int)date('Y');
        $qm  = [1 => '01', 2 => '04', 3 => '07', 4 => '10'][$q];
        $date_from = "{$yr}-{$qm}-01";
        $date_to   = date('Y-m-t', strtotime('+2 months', strtotime($date_from)));
        $period_label = "Q{$q} {$yr}";
        break;
    case 'year':
        $yr = $_GET['year'] ?? date('Y');
        $date_from = "{$yr}-01-01";
        $date_to   = "{$yr}-12-31";
        $period_label = "Full Year {$yr}";
        break;
    case 'custom':
        $period_label = date('M d Y', strtotime($date_from)) . ' – ' . date('M d Y', strtotime($date_to));
        break;
    default:
        $period_label = '';
}

// ── Handle CSV export ──────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="saltshore-' . $export_type . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($export_type === 'transactions') {
        fputcsv($out, ['Date', 'Description', 'Category', 'Amount', 'Source', 'Reconciled']);
        $stmt = $pdo->prepare("SELECT date, description, category, amount, source, reconciled FROM transactions WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date");
        $stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
        while ($row = $stmt->fetch()) {
            $row['reconciled'] = $row['reconciled'] ? 'Yes' : 'No';
            fputcsv($out, $row);
        }
    } elseif ($export_type === 'invoices') {
        fputcsv($out, ['Invoice #', 'Client', 'Description', 'Hours', 'Rate', 'Amount', 'Status', 'Created', 'Paid Date']);
        $stmt = $pdo->prepare("SELECT id, client_name, description, hours, rate, amount, status, created_at, paid_at FROM invoices WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at");
        $stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
        while ($row = $stmt->fetch()) {
            fputcsv($out, $row);
        }
    } elseif ($export_type === 'time_blocks') {
        fputcsv($out, ['Date', 'Start', 'End', 'Duration (hrs)', 'Category', 'Billable', 'Notes']);
        $stmt = $pdo->prepare("SELECT DATE(start_time), start_time, end_time, ROUND(duration_seconds/3600,2), category, is_billable, notes FROM time_blocks WHERE user_id = ? AND DATE(start_time) BETWEEN ? AND ? ORDER BY start_time");
        $stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
        while ($row = $stmt->fetch()) {
            $row[5] = $row[5] ? 'Yes' : 'No';
            fputcsv($out, $row);
        }
    }

    fclose($out);
    exit;
}

// ── Summary data for the selected period ──────────────────────────────────

// Revenue
$rev_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE user_id=? AND status='paid' AND DATE(paid_at) BETWEEN ? AND ?");
$rev_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$period_revenue = (float)$rev_stmt->fetchColumn();

// Expenses
$exp_stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=? AND amount<0 AND date BETWEEN ? AND ?");
$exp_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$period_expenses = (float)$exp_stmt->fetchColumn();
$period_profit   = $period_revenue - $period_expenses;

// Hours
$hrs_stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds),0) FROM time_blocks WHERE user_id=? AND DATE(start_time) BETWEEN ? AND ?");
$hrs_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$period_hours = round((float)$hrs_stmt->fetchColumn() / 3600, 1);

// Invoice count
$inv_c_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=? AND DATE(created_at) BETWEEN ? AND ?");
$inv_c_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$invoice_count = (int)$inv_c_stmt->fetchColumn();

// Expense categories
$cat_stmt = $pdo->prepare("SELECT category, SUM(ABS(amount)) as total FROM transactions WHERE user_id=? AND amount<0 AND date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$cat_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$expense_cats = $cat_stmt->fetchAll();

// Recent invoices
$inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id=? AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
$inv_stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$period_invoices = $inv_stmt->fetchAll();
?>

<!-- Period Selector -->
<div style="background: var(--panel-bg); border: 1px solid var(--panel-border); border-radius: var(--radius-md); padding: var(--space-xl); margin-bottom: var(--space-2xl);">
    <form method="GET" style="display: flex; gap: var(--space-lg); align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.85rem; color: var(--color-secondary);">Period</label>
            <select id="report-period" name="period" style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm); font-family: var(--font-primary);">
                <option value="month"   <?= $period === 'month'   ? 'selected' : '' ?>>Monthly</option>
                <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                <option value="year"    <?= $period === 'year'    ? 'selected' : '' ?>>Full Year</option>
                <option value="custom"  <?= $period === 'custom'  ? 'selected' : '' ?>>Custom Range</option>
            </select>
        </div>

        <div class="form-group" style="margin: 0;" id="month-picker" <?= $period !== 'month' ? 'style="display:none;"' : '' ?>>
            <label style="font-size: 0.85rem; color: var(--color-secondary);">Month</label>
            <input type="month" name="selected_month"
                   value="<?= htmlspecialchars($_GET['selected_month'] ?? date('Y-m')) ?>"
                   style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm);">
        </div>

        <div class="form-group" style="margin: 0;" id="year-picker" <?= $period !== 'year' ? 'style="display:none;"' : '' ?>>
            <label style="font-size: 0.85rem; color: var(--color-secondary);">Year</label>
            <select name="year" style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm);">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= (($_GET['year'] ?? date('Y')) == $y) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div id="custom-range" style="display: <?= $period === 'custom' ? 'flex' : 'none' ?>; gap: var(--space-md); align-items: flex-end;">
            <div class="form-group" style="margin: 0;">
                <label style="font-size: 0.85rem; color: var(--color-secondary);">From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                       style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm);">
            </div>
            <div class="form-group" style="margin: 0;">
                <label style="font-size: 0.85rem; color: var(--color-secondary);">To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                       style="padding: 8px 12px; border: 1px solid var(--panel-border); border-radius: var(--radius-sm);">
            </div>
        </div>

        <button type="submit" class="btn-primary">Generate Report</button>
    </form>
</div>

<!-- Period Header -->
<h2 style="color: var(--color-primary); margin-bottom: var(--space-xl);">
    <?= htmlspecialchars($period_label) ?>
</h2>

<!-- KPI Summary -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: var(--space-2xl);">
    <div class="kpi-card">
        <div class="kpi-label">Revenue</div>
        <div class="kpi-value" style="color: var(--success);"><?= format_currency($period_revenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Expenses</div>
        <div class="kpi-value" style="color: var(--danger);"><?= format_currency($period_expenses) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Net Profit</div>
        <div class="kpi-value" style="color: <?= $period_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= format_currency($period_profit) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Hours Worked</div>
        <div class="kpi-value"><?= number_format($period_hours, 1) ?> hrs</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Invoices Created</div>
        <div class="kpi-value"><?= $invoice_count ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Profit Margin</div>
        <div class="kpi-value">
            <?= $period_revenue > 0 ? number_format(($period_profit / $period_revenue) * 100, 1) . '%' : '—' ?>
        </div>
    </div>
</div>

<!-- Expense Breakdown -->
<?php if ($expense_cats): ?>
<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Expense Breakdown</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Amount</th>
                <th>% of Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expense_cats as $cat): ?>
                <tr>
                    <td><?= htmlspecialchars($cat['category']) ?></td>
                    <td><?= format_currency($cat['total']) ?></td>
                    <td>
                        <?= $period_expenses > 0
                            ? number_format(($cat['total'] / $period_expenses) * 100, 1) . '%'
                            : '—'
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Invoice Summary -->
<div class="data-table-container" style="margin-bottom: var(--space-2xl);">
    <div class="data-table-header">
        <h3>Invoices</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Client</th>
                <th>Hours</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($period_invoices): ?>
                <?php foreach ($period_invoices as $inv): ?>
                    <tr>
                        <td>#<?= $inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['client_name']) ?></td>
                        <td><?= number_format($inv['hours'], 1) ?></td>
                        <td><?= format_currency($inv['amount']) ?></td>
                        <td style="color: <?= $inv['status'] === 'paid' ? 'var(--success)' : 'var(--warning)' ?>">
                            <?= $inv['status'] === 'paid' ? '✓ Paid' : 'Unpaid' ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center; opacity: 0.5;">No invoices for this period</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Export Buttons -->
<div style="background: var(--panel-bg); border: 1px solid var(--panel-border); border-radius: var(--radius-md); padding: var(--space-xl);">
    <h3 style="color: var(--color-primary); margin-bottom: var(--space-lg);">Export Data</h3>
    <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'transactions'])) ?>"
           class="btn-secondary">Export Transactions (CSV)</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'invoices'])) ?>"
           class="btn-secondary">Export Invoices (CSV)</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'time_blocks'])) ?>"
           class="btn-secondary">Export Time Blocks (CSV)</a>
    </div>
</div>

<script>
const periodSelect  = document.getElementById('report-period');
const monthPicker   = document.getElementById('month-picker');
const yearPicker    = document.getElementById('year-picker');
const customRange   = document.getElementById('custom-range');

periodSelect.addEventListener('change', function () {
    monthPicker.style.display  = this.value === 'month'   ? ''     : 'none';
    yearPicker.style.display   = this.value === 'year'    ? ''     : 'none';
    customRange.style.display  = this.value === 'custom'  ? 'flex' : 'none';
});
</script>

<?php include 'includes/footer.php'; ?>
