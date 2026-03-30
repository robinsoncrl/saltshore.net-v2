<?php
/**
 * Saltshore Owner Portal — Dashboard Home
 */

$page_title = 'Dashboard';
$current_page = 'dashboard';

include 'includes/header.php';

// Fetch KPIs from database
$current_month = date('Y-m');

// Revenue (from FinPro invoices)
$revenue_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM invoices 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$revenue_stmt->execute([$current_month]);
$monthly_revenue = $revenue_stmt->fetchColumn();

// Expenses (from LedgerPro)
$expenses_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ABS(amount)), 0) as total 
    FROM transactions 
    WHERE amount < 0 
    AND DATE_FORMAT(date, '%Y-%m') = ?
");
$expenses_stmt->execute([$current_month]);
$monthly_expenses = $expenses_stmt->fetchColumn();

$net_profit = $monthly_revenue - $monthly_expenses;

// Hours worked (from CalGen)
$hours_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(duration_seconds), 0) as total 
    FROM time_blocks 
    WHERE DATE_FORMAT(start_time, '%Y-%m') = ?
");
$hours_stmt->execute([$current_month]);
$total_seconds = $hours_stmt->fetchColumn();
$hours_worked = round($total_seconds / 3600, 1);

$effective_rate = $hours_worked > 0 ? $monthly_revenue / $hours_worked : 0;

// Recent time blocks
$recent_blocks = $pdo->query("
    SELECT * FROM time_blocks 
    ORDER BY start_time DESC 
    LIMIT 5
")->fetchAll();

// Recent transactions
$recent_transactions = $pdo->query("
    SELECT * FROM transactions 
    ORDER BY date DESC, id DESC 
    LIMIT 5
")->fetchAll();
?>

<!-- KPI Cards -->
<div class="dashboard-grid">
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Monthly Revenue</span>
        </div>
        <div class="kpi-value"><?= format_currency($monthly_revenue) ?></div>
        <div class="kpi-change positive">↑ From FinPro invoices</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Monthly Expenses</span>
        </div>
        <div class="kpi-value"><?= format_currency($monthly_expenses) ?></div>
        <div class="kpi-change negative">↓ From LedgerPro</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Net Profit</span>
        </div>
        <div class="kpi-value" style="color: <?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
            <?= format_currency($net_profit) ?>
        </div>
        <div class="kpi-change">Revenue - Expenses</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Hours Worked</span>
        </div>
        <div class="kpi-value"><?= number_format($hours_worked, 1) ?> hrs</div>
        <div class="kpi-change">From CalGen tracking</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Effective Rate</span>
        </div>
        <div class="kpi-value"><?= format_currency($effective_rate) ?>/hr</div>
        <div class="kpi-change">Revenue ÷ Hours</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">Profit per Hour</span>
        </div>
        <div class="kpi-value"><?= format_currency($hours_worked > 0 ? $net_profit / $hours_worked : 0) ?>/hr</div>
        <div class="kpi-change">Net Profit ÷ Hours</div>
    </div>
</div>

<!-- Revenue vs Expenses Chart -->
<div class="chart-container">
    <div class="chart-header">
        <h3>Revenue vs Expenses</h3>
        <p>Last 6 months</p>
    </div>
    <canvas id="revenueExpensesChart" height="80"></canvas>
</div>

<!-- Recent Time Blocks -->
<div class="data-table-container">
    <div class="data-table-header">
        <h3>Recent Time Blocks</h3>
        <a href="calgen.php" class="btn-secondary">View All</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th>Duration</th>
                <th>Category</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($recent_blocks) > 0): ?>
                <?php foreach ($recent_blocks as $block): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($block['start_time'])) ?></td>
                        <td><?= date('g:i A', strtotime($block['start_time'])) ?></td>
                        <td><?= $block['end_time'] ? date('g:i A', strtotime($block['end_time'])) : '—' ?></td>
                        <td><?= format_duration($block['duration_seconds']) ?></td>
                        <td><?= htmlspecialchars($block['category'] ?? 'Uncategorized') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; opacity: 0.5;">No time blocks yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent Transactions -->
<div class="data-table-container">
    <div class="data-table-header">
        <h3>Recent Transactions</h3>
        <a href="ledgerpro.php" class="btn-secondary">View All</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($recent_transactions) > 0): ?>
                <?php foreach ($recent_transactions as $tx): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($tx['date'])) ?></td>
                        <td><?= htmlspecialchars($tx['description']) ?></td>
                        <td><?= htmlspecialchars($tx['category'] ?? '—') ?></td>
                        <td style="color: <?= $tx['amount'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                            <?= format_currency($tx['amount']) ?>
                        </td>
                        <td><?= $tx['reconciled'] ? '✓ Reconciled' : 'Pending' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; opacity: 0.5;">No transactions yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Revenue vs Expenses Chart
const ctx = document.getElementById('revenueExpensesChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
            {
                label: 'Revenue',
                data: [0, 0, 0, 0, 0, <?= $monthly_revenue ?>],
                borderColor: '#2E8BC0',
                backgroundColor: 'rgba(46, 139, 192, 0.1)',
                tension: 0.4
            },
            {
                label: 'Expenses',
                data: [0, 0, 0, 0, 0, <?= $monthly_expenses ?>],
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
