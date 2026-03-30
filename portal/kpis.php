<?php
/**
 * Saltshore Owner Portal — KPIs Trends
 */

$page_title   = 'KPIs — Trends & Analytics';
$current_page = 'kpis';

include 'includes/header.php';

// ── Build last-12-months data ──────────────────────────────────────────────
$chart_labels   = [];
$revenue_data   = [];
$expense_data   = [];
$net_data       = [];
$hours_data     = [];
$eff_rate_data  = [];

for ($i = 11; $i >= 0; $i--) {
    $m   = date('Y-m', strtotime("-{$i} months"));
    $lbl = date('M y', strtotime("-{$i} months"));
    $chart_labels[] = $lbl;

    // Revenue (paid invoices)
    $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE user_id=? AND status='paid' AND DATE_FORMAT(paid_at,'%Y-%m')=?");
    $r->execute([$_SESSION['user_id'], $m]);
    $rev = (float)$r->fetchColumn();
    $revenue_data[] = $rev;

    // Expenses
    $e = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=? AND amount<0 AND DATE_FORMAT(date,'%Y-%m')=?");
    $e->execute([$_SESSION['user_id'], $m]);
    $exp = (float)$e->fetchColumn();
    $expense_data[] = $exp;
    $net_data[]     = $rev - $exp;

    // Hours worked
    $h = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds),0) FROM time_blocks WHERE user_id=? AND DATE_FORMAT(start_time,'%Y-%m')=?");
    $h->execute([$_SESSION['user_id'], $m]);
    $secs = (float)$h->fetchColumn();
    $hrs  = round($secs / 3600, 1);
    $hours_data[]   = $hrs;
    $eff_rate_data[] = $hrs > 0 ? round($rev / $hrs, 2) : 0;
}

// ── Expense categories (current month) for donut ──────────────────────────
$current_month = date('Y-m');
$cat_stmt = $pdo->prepare("
    SELECT category, COALESCE(SUM(ABS(amount)), 0) as total
    FROM transactions
    WHERE user_id = ? AND amount < 0 AND DATE_FORMAT(date,'%Y-%m') = ?
    GROUP BY category
    ORDER BY total DESC
    LIMIT 8
");
$cat_stmt->execute([$_SESSION['user_id'], $current_month]);
$categories = $cat_stmt->fetchAll();

// ── Summary KPIs ──────────────────────────────────────────────────────────
$ytd_revenue  = array_sum(array_slice($revenue_data, -12));
$ytd_expenses = array_sum(array_slice($expense_data, -12));
$ytd_profit   = $ytd_revenue - $ytd_expenses;
$ytd_hours    = array_sum(array_slice($hours_data, -12));
$avg_monthly_revenue = $ytd_revenue / 12;
?>

<!-- Summary KPIs -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: var(--space-2xl);">
    <div class="kpi-card">
        <div class="kpi-label">YTD Revenue</div>
        <div class="kpi-value" style="color: var(--success);"><?= format_currency($ytd_revenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">YTD Expenses</div>
        <div class="kpi-value" style="color: var(--danger);"><?= format_currency($ytd_expenses) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">YTD Net Profit</div>
        <div class="kpi-value" style="color: <?= $ytd_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= format_currency($ytd_profit) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">YTD Hours</div>
        <div class="kpi-value"><?= number_format($ytd_hours, 1) ?> hrs</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg Monthly Revenue</div>
        <div class="kpi-value"><?= format_currency($avg_monthly_revenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Profit Margin</div>
        <div class="kpi-value">
            <?= $ytd_revenue > 0 ? number_format(($ytd_profit / $ytd_revenue) * 100, 1) . '%' : '—' ?>
        </div>
    </div>
</div>

<!-- Revenue vs Expenses (line) -->
<div class="chart-container">
    <div class="chart-header">
        <h3>Revenue vs Expenses</h3>
        <p>Last 12 months</p>
    </div>
    <canvas id="revExpChart" height="70"></canvas>
</div>

<!-- Net Profit + Hours side by side -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl); margin-bottom: var(--space-2xl);">
    <div class="chart-container" style="margin-bottom: 0;">
        <div class="chart-header">
            <h3>Net Profit</h3>
            <p>Monthly trend</p>
        </div>
        <canvas id="netProfitChart" height="140"></canvas>
    </div>
    <div class="chart-container" style="margin-bottom: 0;">
        <div class="chart-header">
            <h3>Hours Worked</h3>
            <p>Monthly trend</p>
        </div>
        <canvas id="hoursChart" height="140"></canvas>
    </div>
</div>

<!-- Effective rate + Expense categories side by side -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
    <div class="chart-container" style="margin-bottom: 0;">
        <div class="chart-header">
            <h3>Effective Hourly Rate</h3>
            <p>Revenue ÷ hours per month</p>
        </div>
        <canvas id="effRateChart" height="140"></canvas>
    </div>
    <div class="chart-container" style="margin-bottom: 0;">
        <div class="chart-header">
            <h3>Expense Categories</h3>
            <p><?= date('F Y') ?></p>
        </div>
        <?php if ($categories): ?>
            <canvas id="expCatChart" height="140"></canvas>
        <?php else: ?>
            <p style="text-align: center; opacity: 0.5; padding: 40px 0;">No expense data yet</p>
        <?php endif; ?>
    </div>
</div>

<script>
const labels    = <?= json_encode($chart_labels) ?>;
const revenue   = <?= json_encode($revenue_data) ?>;
const expenses  = <?= json_encode($expense_data) ?>;
const netProfit = <?= json_encode($net_data) ?>;
const hours     = <?= json_encode($hours_data) ?>;
const effRate   = <?= json_encode($eff_rate_data) ?>;

const catLabels = <?= json_encode(array_column($categories, 'category')) ?>;
const catTotals = <?= json_encode(array_map(fn($c) => (float)$c['total'], $categories)) ?>;

const sharedOptsY = {
    beginAtZero: true,
    ticks: { callback: v => '$' + v.toLocaleString() }
};

// ── Revenue vs Expenses ────────────────────────────────────────────────────
new Chart(document.getElementById('revExpChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Revenue',
                data: revenue,
                borderColor: '#2E8BC0',
                backgroundColor: 'rgba(46,139,192,0.08)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Expenses',
                data: expenses,
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: sharedOptsY }
    }
});

// ── Net Profit ─────────────────────────────────────────────────────────────
new Chart(document.getElementById('netProfitChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Net Profit',
            data: netProfit,
            backgroundColor: netProfit.map(v => v >= 0 ? 'rgba(16,185,129,0.7)' : 'rgba(239,68,68,0.7)'),
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: sharedOptsY }
    }
});

// ── Hours Worked ───────────────────────────────────────────────────────────
new Chart(document.getElementById('hoursChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Hours',
            data: hours,
            backgroundColor: 'rgba(46,139,192,0.6)',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// ── Effective Rate ─────────────────────────────────────────────────────────
new Chart(document.getElementById('effRateChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: '$/hr',
            data: effRate,
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245,158,11,0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => '$' + v + '/hr' }
            }
        }
    }
});

// ── Expense Donut ──────────────────────────────────────────────────────────
<?php if ($categories): ?>
const donutColors = [
    '#2E8BC0','#EF4444','#F59E0B','#10B981',
    '#8B5CF6','#EC4899','#06B6D4','#84CC16'
];
new Chart(document.getElementById('expCatChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catTotals,
            backgroundColor: donutColors.slice(0, catLabels.length),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: { font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ' $' + parseFloat(ctx.raw).toLocaleString('en-US', { minimumFractionDigits: 2 })
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
