<?php
$settings = receipt_settings();
$currency = static fn(float $amount): string => 'Rs. ' . number_format($amount, 2);
$percent = static function (int $value, int $total): string {
    return $total > 0 ? number_format(($value / $total) * 100, 0) . '%' : '0%';
};
$statusLabel = static fn(string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = static function (string $status): string {
    return match ($status) {
        'completed', 'paid' => 'is-success',
        'ongoing', 'partial', 'confirmed' => 'is-warning',
        'cancelled', 'due', 'no_show' => 'is-danger',
        default => 'is-info',
    };
};
$periodLabel = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'][$dashboard['period']] ?? 'Today';
$jobTotal = array_sum($dashboard['job_status']);
$incomeTotal = array_sum($dashboard['income']);
$partsEnd = $incomeTotal ? ($dashboard['income']['parts'] / $incomeTotal) * 100 : 0;
$servicesEnd = $incomeTotal ? (($dashboard['income']['parts'] + $dashboard['income']['services']) / $incomeTotal) * 100 : 0;
$chargesEnd = $incomeTotal ? (($dashboard['income']['parts'] + $dashboard['income']['services'] + $dashboard['income']['charges']) / $incomeTotal) * 100 : 0;
$pendingEnd = $jobTotal ? ($dashboard['job_status']['pending'] / $jobTotal) * 100 : 0;
$ongoingEnd = $jobTotal ? (($dashboard['job_status']['pending'] + $dashboard['job_status']['ongoing']) / $jobTotal) * 100 : 0;
$completedEnd = $jobTotal ? (($dashboard['job_status']['pending'] + $dashboard['job_status']['ongoing'] + $dashboard['job_status']['completed']) / $jobTotal) * 100 : 0;
$salesMax = 1.0;
foreach ($dashboard['sales_chart'] as $salesDay) {
    $salesMax = max($salesMax, (float) $salesDay['job_card'], (float) $salesDay['quick']);
}
?>
<section class="dashboard-page">
    <div class="dashboard-heading">
        <div>
            <span class="eyebrow">Operations overview</span>
            <h1>Dashboard</h1>
            <p>Welcome back! Here's what's happening at <?= e($settings['garage_name'] ?? 'Deshappriya Motors') ?> today.</p>
        </div>
        <form class="dashboard-period" method="get">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="dashboard">
            <label for="dashboard-period">Period</label>
            <select id="dashboard-period" name="period" onchange="this.form.submit()">
                <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'] as $value => $label): ?><option value="<?= $value ?>" <?= $dashboard['period'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="dashboard-kpis">
        <?php foreach ([
            [$periodLabel . ' Sales', $currency($dashboard['sales']), 'Finalized invoice revenue', 'is-blue', '$', 'index.php?page=admin&section=reports-sales'],
            [$periodLabel . ' Payments', $currency($dashboard['payments']), 'Amount applied to invoices', 'is-green', '=', 'index.php?page=admin&section=reports-payments'],
            [$periodLabel . ' Pending Payments', $currency($dashboard['pending']), 'Invoice balance in selected period', 'is-orange', '!', 'index.php?page=admin&section=invoices'],
            [$periodLabel . ' Ongoing Jobs', number_format($dashboard['ongoing']), 'Jobs in selected period', 'is-purple', 'W', 'index.php?page=admin&section=jobcards-ongoing'],
            ['Completed Jobs', number_format($dashboard['completed']), 'Completed in selected period', 'is-teal', 'V', 'index.php?page=admin&section=jobcards-completed'],
            ['Low Stock Items', number_format($dashboard['low_stock']), 'Current stock alert', 'is-red', 'Q', 'index.php?page=admin&section=stock-low'],
            [$periodLabel . ' Gross Profit', $currency($dashboard['gross_profit']), 'Sales + other income - all expenses', 'is-gold', 'P', 'index.php?page=admin&section=reports-profit-loss'],
            [$periodLabel . ' Cash Settled', $currency($dashboard['cashier_settled']), 'Cash accepted from cashier handovers', 'is-green', 'C', 'index.php?page=admin&section=cashier-handovers'],
            [$periodLabel . ' Cash Remaining', $currency($dashboard['cashier_remaining']), 'Expected cash not yet settled', 'is-orange', 'R', 'index.php?page=admin&section=cashier-handovers'],
        ] as $kpi): ?><a class="dashboard-kpi dashboard-kpi-link <?= $kpi[3] ?>" href="<?= e($kpi[5]) ?>"><span class="dashboard-kpi-icon"><?= e($kpi[4]) ?></span><div><small><?= e($kpi[0]) ?></small><strong><?= e($kpi[1]) ?></strong><span><?= e($kpi[2]) ?></span></div></a><?php endforeach; ?>
    </div>

    <div class="dashboard-chart-grid">
        <article class="dashboard-card sales-chart-card">
            <div class="dashboard-card-heading"><div><h2>Sales Overview</h2><span>Finalized invoice revenue for <?= e(strtolower($periodLabel)) ?></span></div><span class="dashboard-range"><?= e($periodLabel) ?></span></div>
            <div class="sales-chart" aria-label="Sales overview chart">
                <div class="sales-axis"><span><?= e($currency($salesMax)) ?></span><span><?= e($currency($salesMax / 2)) ?></span><span>Rs. 0.00</span></div>
                <div class="sales-bars">
                    <?php foreach ($dashboard['sales_chart'] as $row): $jobHeight = $salesMax > 0 ? ($row['job_card'] / $salesMax) * 100 : 0; $quickHeight = $salesMax > 0 ? ($row['quick'] / $salesMax) * 100 : 0; ?><div class="sales-day"><div class="sales-columns"><span class="sales-bar job-bar" style="height:<?= number_format($jobHeight, 2, '.', '') ?>%" title="Job Card: <?= e($currency($row['job_card'])) ?>"></span><span class="sales-bar quick-bar" style="height:<?= number_format($quickHeight, 2, '.', '') ?>%" title="Quick Invoice: <?= e($currency($row['quick'])) ?>"></span></div><small><?= e(date('M d', strtotime($row['date']))) ?></small></div><?php endforeach; ?>
                </div>
            </div>
            <div class="chart-legend"><span><i class="legend-blue"></i>Job Card Sales</span><span><i class="legend-sky"></i>Quick Invoice Sales</span></div>
        </article>
        <article class="dashboard-card income-card">
            <div class="dashboard-card-heading"><div><h2>Income Breakdown</h2><span><?= e($periodLabel) ?>, excluding payment collections</span></div></div>
            <div class="donut-layout"><div class="dashboard-donut" style="--parts-end:<?= number_format($partsEnd, 2, '.', '') ?>%;--services-end:<?= number_format($servicesEnd, 2, '.', '') ?>%;--charges-end:<?= number_format($chargesEnd, 2, '.', '') ?>%"><div><strong><?= e($currency($incomeTotal)) ?></strong><span>Total Income</span></div></div><div class="donut-list"><?php foreach ([['Spare Parts Sales', $dashboard['income']['parts'], 'legend-blue'], ['Service Income', $dashboard['income']['services'], 'legend-green'], ['Special Charges', $dashboard['income']['charges'], 'legend-orange'], ['Other Income', $dashboard['income']['other'], 'legend-purple']] as $income): ?><div><span><i class="<?= e($income[2]) ?>"></i><?= e($income[0]) ?></span><b><?= e($percent((int) round($income[1]), (int) round($incomeTotal))) ?></b></div><?php endforeach; ?></div></div>
        </article>
        <article class="dashboard-card job-status-card">
            <div class="dashboard-card-heading"><div><h2>Job Card Status</h2><span>Selected period activity</span></div></div>
            <div class="donut-layout"><div class="dashboard-donut status-donut" style="--pending-end:<?= number_format($pendingEnd, 2, '.', '') ?>%;--ongoing-end:<?= number_format($ongoingEnd, 2, '.', '') ?>%;--completed-end:<?= number_format($completedEnd, 2, '.', '') ?>%"><div><strong><?= e(number_format($jobTotal)) ?></strong><span>Total Jobs</span></div></div><div class="donut-list"><?php foreach ([['pending', 'legend-blue'], ['ongoing', 'legend-orange'], ['completed', 'legend-green'], ['cancelled', 'legend-red']] as $status): ?><div><span><i class="<?= e($status[1]) ?>"></i><?= e($statusLabel($status[0])) ?></span><b><?= e(number_format($dashboard['job_status'][$status[0]])) ?> (<?= e($percent($dashboard['job_status'][$status[0]], $jobTotal)) ?>)</b></div><?php endforeach; ?></div></div>
        </article>
    </div>

    <div class="dashboard-widget-grid dashboard-widget-grid-three">
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Recent Job Cards</h2><a href="index.php?page=admin&amp;section=jobcards-pending">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>#</th><th>Job Card</th><th>Vehicle</th><th>Customer</th><th>Status</th><th>Date</th><th></th></tr></thead><tbody><?php if (!$dashboard['recent_jobs']): ?><tr><td colspan="7" class="dashboard-empty">No records found</td></tr><?php else: foreach ($dashboard['recent_jobs'] as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><a href="index.php?page=admin&amp;section=jobcards-view&amp;id=<?= (int) $row['id'] ?>"><?= e($row['job_card_no']) ?></a></td><td><?= e($row['vehicle_number'] ?: '-') ?></td><td><?= e($row['customer_name']) ?></td><td><span class="dashboard-badge <?= e($statusClass($row['status'])) ?>"><?= e($statusLabel($row['status'])) ?></span></td><td><?= e(date('Y-m-d', strtotime($row['created_date']))) ?></td><td><a class="dashboard-view-link" href="index.php?page=admin&amp;section=jobcards-view&amp;id=<?= (int) $row['id'] ?>">View</a></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Recent Invoices</h2><a href="index.php?page=admin&amp;section=invoices">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>#</th><th>Invoice</th><th>Customer</th><th>Total (Rs.)</th><th>Status</th></tr></thead><tbody><?php if (!$dashboard['recent_invoices']): ?><tr><td colspan="5" class="dashboard-empty">No records found</td></tr><?php else: foreach ($dashboard['recent_invoices'] as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><a href="index.php?page=admin&amp;section=invoices-view&amp;id=<?= (int) $row['id'] ?>"><?= e($row['invoice_no']) ?></a></td><td><?= e($row['customer_name']) ?></td><td><?= e(number_format((float) $row['total_amount'], 2)) ?></td><td><span class="dashboard-badge <?= e($statusClass($row['payment_status'])) ?>"><?= e($statusLabel($row['payment_status'])) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Low Stock Items</h2><a href="index.php?page=admin&amp;section=stock-low">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>#</th><th>Part Code</th><th>Part Name</th><th>Qty</th><th>Reorder</th></tr></thead><tbody><?php if (!$dashboard['low_items']): ?><tr><td colspan="5" class="dashboard-empty">No low stock items</td></tr><?php else: foreach ($dashboard['low_items'] as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= e($row['part_code']) ?></td><td><?= e($row['part_name']) ?></td><td class="stock-qty-low"><?= e(number_format((float) $row['stock_qty'], 0)) ?></td><td><?= e(number_format((float) $row['reorder_level'], 0)) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
    </div>

    <div class="dashboard-widget-grid dashboard-widget-grid-three">
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Today's Appointments</h2><a href="index.php?page=admin&amp;section=appointments">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>Time</th><th>Customer</th><th>Vehicle</th><th>Status</th></tr></thead><tbody><?php if (!$dashboard['appointments']): ?><tr><td colspan="4" class="dashboard-empty">No records found</td></tr><?php else: foreach ($dashboard['appointments'] as $row): ?><tr><td><?= e(date('h:i A', strtotime($row['appointment_time']))) ?></td><td><?= e($row['customer_name']) ?></td><td><?= e($row['vehicle_number'] ?: '-') ?></td><td><span class="dashboard-badge <?= e($statusClass($row['status'])) ?>"><?= e($row['status'] === 'confirmed' ? 'Scheduled' : $statusLabel($row['status'])) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Today's Expenses</h2><a href="index.php?page=admin&amp;section=expenses">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>#</th><th>Description</th><th>Category</th><th>Amount (Rs.)</th></tr></thead><tbody><?php if (!$dashboard['expenses']): ?><tr><td colspan="4" class="dashboard-empty">No records found</td></tr><?php else: foreach ($dashboard['expenses'] as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= e($row['description']) ?></td><td><?= e($row['category']) ?></td><td><?= e(number_format((float) $row['total_amount'], 2)) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
        <article class="dashboard-card dashboard-table-card"><div class="dashboard-card-heading"><h2>Today's Other Income</h2><a href="index.php?page=admin&amp;section=other-income">View All</a></div><div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>#</th><th>Description</th><th>Amount (Rs.)</th></tr></thead><tbody><?php if (!$dashboard['other_income']): ?><tr><td colspan="3" class="dashboard-empty">No records found</td></tr><?php else: foreach ($dashboard['other_income'] as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= e($row['title']) ?></td><td><?= e(number_format((float) $row['amount'], 2)) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
    </div>

    <article class="dashboard-profit"><div><span class="eyebrow">Profit snapshot</span><h2><?= e($periodLabel) ?> Gross Profit</h2><p>Sales + other income - FIFO parts cost - all expenses</p></div><strong class="<?= $dashboard['gross_profit'] < 0 ? 'is-negative' : '' ?>"><?= e($currency($dashboard['gross_profit'])) ?></strong></article>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const period = <?= json_encode($periodLabel) ?>;
    const kpis = document.querySelectorAll('.dashboard-kpi small');
    [' Sales', ' Payments', ' Pending Payments', ' Ongoing Jobs'].forEach(function (suffix, index) {
        if (kpis[index]) kpis[index].textContent = period + suffix;
    });
    const tableHeadings = document.querySelectorAll('.dashboard-table-card h2');
    [0, 1, 2].forEach(function (index) {
        if (tableHeadings[index]) tableHeadings[index].textContent = period + [' Appointments', ' Expenses', ' Other Income'][index];
    });
    const profitHeading = document.querySelector('.dashboard-profit h2');
    if (profitHeading) profitHeading.textContent = period + ' Gross Profit';
});
</script>
