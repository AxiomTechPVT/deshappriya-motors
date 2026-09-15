<?php
$definition = $report['definition'];
$filters = $report['filters'];
$lookups = $report['lookups'];
$columns = $report['columns'];
$rows = $report['rows'];
$cards = $report['cards'];
$bounds = $report['bounds'];
$printMode = $printMode ?? false;
$isSalesReport = $section === 'reports-sales';
$sectionTitle = $definition['title'];
$queryBase = $_GET;
unset($queryBase['export'], $queryBase['mode']);
if (!function_exists('report_cell_display')) {
    function report_cell_display(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value) && preg_match('/^(total_vehicles|total_jobs|total_invoices|items_count|total_cards|pending_cards|ongoing_cards|completed_cards|cancelled_cards|total_items|in_stock|low_stock|out_stock|total_customers|new_customers|active_customers|total_bays|available_bays|occupied_bays|maintenance_bays)$/i', $key)) {
            return number_format((float) $value, 0);
        }
        if (is_numeric($value) && preg_match('/(amount|total|balance|outstanding|cost|price|charge|value|revenue|expense|income|due|paid|change|qty|quantity|average)/i', $key)) {
            return number_format((float) $value, 2);
        }
        return (string) $value;
    }
}
$salesCategoryValues = [];
if ($isSalesReport) {
    foreach ($cards as $card) {
        $salesCategoryValues[$card['label']] = (float) preg_replace('/[^0-9.-]/', '', (string) $card['value']);
    }
    $salesCategories = [
        ['label' => 'Spare Parts Sales', 'value' => $salesCategoryValues['Spare Parts Sales'] ?? 0, 'color' => '#2f80ed'],
        ['label' => 'Service Income', 'value' => $salesCategoryValues['Service Income'] ?? 0, 'color' => '#36b979'],
        ['label' => 'Special Charges', 'value' => $salesCategoryValues['Special Service Charges'] ?? 0, 'color' => '#f79009'],
    ];
    $salesCategoryTotal = max(0.01, array_sum(array_column($salesCategories, 'value')));
    $salesTotalValue = $salesCategoryValues['Total Sales'] ?? 0;
    $salesDonutStops = [];
    $salesStop = 0;
    foreach ($salesCategories as $category) {
        $salesStop += ($category['value'] / $salesCategoryTotal) * 100;
        $salesDonutStops[] = $category['color'] . ' ' . $salesStop . '%';
    }
}
?>
<?php if ($isSalesReport): ?><div class="alert alert-info">Total Discount = Invoice Discounts + Part Price Reductions. Part price reductions are already included in selling prices and are not deducted again. Older items without a saved list price are excluded from part price reductions.</div><?php endif; ?>
<div class="admin-page-heading report-page-heading">
    <div>
        <div class="breadcrumb-line">Admin / Reports / <?= e($sectionTitle) ?></div>
        <h1 class="report-title"><span class="report-title-icon">#</span><?= e($sectionTitle) ?></h1>
        <p><?= $isSalesReport ? 'View sales performance, service income, spare parts sales and other revenue details.' : e($definition['subtitle']) ?></p>
    </div>
    <?php if (!$printMode): ?>
        <div class="report-actions no-print">
            <a class="btn report-action report-action-pdf" href="index.php?page=admin&amp;section=<?= e($section) ?>&amp;<?= e(report_query_string(['mode' => 'print'])) ?>" target="_blank"><span class="report-action-icon">DOC</span> Export PDF</a>
            <a class="btn report-action report-action-excel" href="index.php?page=admin&amp;section=<?= e($section) ?>&amp;<?= e(report_query_string(['export' => 'excel'])) ?>"><span class="report-action-icon">XLS</span> Export Excel</a>
            <a class="btn report-action report-action-print" href="index.php?page=admin&amp;section=<?= e($section) ?>&amp;<?= e(report_query_string(['mode' => 'print'])) ?>" target="_blank"><span class="report-action-icon">PRN</span> Print</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($isSalesReport): ?>
<section class="admin-panel report-filter-panel report-sales-filter-panel no-print">
    <form method="get" class="report-filter-form report-sales-filter-form">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="reports-sales">
        <div class="report-date-tabs" role="tablist" aria-label="Date range">
            <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom Date'] as $dateMode => $dateLabel): ?>
                <button type="button" class="report-date-tab <?= $filters['date_mode'] === $dateMode ? 'active' : '' ?>" data-date-mode="<?= e($dateMode) ?>"><?= e($dateLabel) ?></button>
            <?php endforeach; ?>
        </div>
        <label class="report-sales-date-field <?= $filters['date_mode'] === 'custom' ? '' : 'd-none' ?>" data-custom-date>
            From Date
            <input class="form-control" type="date" name="from_date" value="<?= e($filters['from_date']) ?>">
        </label>
        <label class="report-sales-date-field <?= $filters['date_mode'] === 'custom' ? '' : 'd-none' ?>" data-custom-date>
            To Date
            <input class="form-control" type="date" name="to_date" value="<?= e($filters['to_date']) ?>">
        </label>
        <input type="hidden" name="date_mode" value="<?= e($filters['date_mode']) ?>" data-date-mode-input>
        <label>Invoice Type
            <select class="form-select" name="invoice_type">
                <option value="">All Types</option>
                <option value="job_card" <?= $filters['invoice_type'] === 'job_card' ? 'selected' : '' ?>>Job Card</option>
                <option value="quick" <?= $filters['invoice_type'] === 'quick' ? 'selected' : '' ?>>Quick Invoice</option>
            </select>
        </label>
        <label>Customer
            <select class="form-select" name="customer_id">
                <option value="0">All Customers</option>
                <?php foreach ($lookups['customers'] as $customer): ?>
                    <option value="<?= (int) $customer['id'] ?>" <?= $filters['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Payment Status
            <select class="form-select" name="payment_status">
                <option value="">All Status</option>
                <option value="paid" <?= $filters['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="partial" <?= $filters['payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="due" <?= $filters['payment_status'] === 'due' ? 'selected' : '' ?>>Due</option>
            </select>
        </label>
        <div class="report-filter-actions">
            <button class="btn btn-primary" type="submit">Apply Filters</button>
            <a class="btn btn-light" href="index.php?page=admin&amp;section=reports-sales">Reset</a>
        </div>
    </form>
</section>
<?php else: ?>
<section class="admin-panel report-filter-panel no-print">
    <form method="get" class="report-filter-form">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="<?= e($section) ?>">
        <label>
            Date Filter
            <select class="form-select" name="date_mode">
                <option value="today" <?= $filters['date_mode'] === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week" <?= $filters['date_mode'] === 'week' ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $filters['date_mode'] === 'month' ? 'selected' : '' ?>>This Month</option>
                <option value="custom" <?= $filters['date_mode'] === 'custom' ? 'selected' : '' ?>>Custom Date</option>
            </select>
        </label>
        <label class="<?= $filters['date_mode'] === 'custom' ? '' : 'd-none' ?>" data-custom-date>
            From Date
            <input class="form-control" type="date" name="from_date" value="<?= e($filters['from_date']) ?>">
        </label>
        <label class="<?= $filters['date_mode'] === 'custom' ? '' : 'd-none' ?>" data-custom-date>
            To Date
            <input class="form-control" type="date" name="to_date" value="<?= e($filters['to_date']) ?>">
        </label>
        <?php if (in_array($section, ['reports-sales', 'reports-invoices'], true)): ?>
            <label>
                Invoice Type
                <select class="form-select" name="invoice_type">
                    <option value="">All</option>
                    <option value="job_card" <?= $filters['invoice_type'] === 'job_card' ? 'selected' : '' ?>>Job Card</option>
                    <option value="quick" <?= $filters['invoice_type'] === 'quick' ? 'selected' : '' ?>>Quick Invoice</option>
                </select>
            </label>
            <label>
                Customer
                <select class="form-select" name="customer_id">
                    <option value="0">All Customers</option>
                    <?php foreach ($lookups['customers'] as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= $filters['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Payment Status
                <select class="form-select" name="payment_status">
                    <option value="">All</option>
                    <option value="paid" <?= $filters['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $filters['payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="due" <?= $filters['payment_status'] === 'due' ? 'selected' : '' ?>>Due</option>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-job-cards'): ?>
            <label>
                Job Status
                <select class="form-select" name="job_status">
                    <option value="">All</option>
                    <option value="pending" <?= $filters['job_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="ongoing" <?= $filters['job_status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="completed" <?= $filters['job_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filters['job_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </label>
            <label>
                Customer
                <select class="form-select" name="customer_id">
                    <option value="0">All Customers</option>
                    <?php foreach ($lookups['customers'] as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= $filters['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Vehicle
                <select class="form-select" name="vehicle_id">
                    <option value="0">All Vehicles</option>
                    <?php foreach ($lookups['vehicles'] as $vehicle): ?>
                        <option value="<?= (int) $vehicle['id'] ?>" <?= $filters['vehicle_id'] === (int) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['vehicle_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Mechanic
                <select class="form-select" name="mechanic_id">
                    <option value="0">All Mechanics</option>
                    <?php foreach ($lookups['mechanics'] as $mechanic): ?>
                        <option value="<?= (int) $mechanic['id'] ?>" <?= $filters['mechanic_id'] === (int) $mechanic['id'] ? 'selected' : '' ?>><?= e($mechanic['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Bay
                <select class="form-select" name="bay_id">
                    <option value="0">All Bays</option>
                    <?php foreach ($lookups['bays'] as $bay): ?>
                        <option value="<?= (int) $bay['id'] ?>" <?= $filters['bay_id'] === (int) $bay['id'] ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-expenses'): ?>
            <label>
                Expense Category
                <select class="form-select" name="expense_category">
                    <option value="">All Categories</option>
                    <?php foreach ($lookups['categories'] as $category): ?>
                        <option value="<?= e($category['name']) ?>" <?= $filters['expense_category'] === $category['name'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Supplier
                <select class="form-select" name="supplier_id">
                    <option value="0">All Suppliers</option>
                    <?php foreach ($lookups['suppliers'] as $supplier): ?>
                        <option value="<?= (int) $supplier['id'] ?>" <?= $filters['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Payment Status
                <select class="form-select" name="payment_status">
                    <option value="">All</option>
                    <option value="paid" <?= $filters['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $filters['payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="due" <?= $filters['payment_status'] === 'due' ? 'selected' : '' ?>>Due</option>
                    <option value="pending" <?= $filters['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-stock'): ?>
            <label>
                View
                <select class="form-select" name="view">
                    <?php foreach (['current' => 'Current Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock', 'movements' => 'Stock Movements', 'purchases' => 'Stock Purchases', 'parts_used' => 'Parts Used', 'parts_sold' => 'Parts Sold'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['view'] ?? 'current') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Stock Item
                <select class="form-select" name="stock_item_id">
                    <option value="0">All Items</option>
                    <?php foreach ($lookups['stock_items'] as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= $filters['stock_item_id'] === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['part_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-customers' || $section === 'reports-vehicles' || $section === 'reports-payments'): ?>
            <label>
                Customer
                <select class="form-select" name="customer_id">
                    <option value="0">All Customers</option>
                    <?php foreach ($lookups['customers'] as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= $filters['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-vehicles'): ?>
            <label>
                Make / Model
                <input class="form-control" type="text" name="make_model" value="<?= e($filters['make_model']) ?>" placeholder="Toyota, Axio, etc.">
            </label>
            <label>
                Vehicle
                <select class="form-select" name="vehicle_id">
                    <option value="0">All Vehicles</option>
                    <?php foreach ($lookups['vehicles'] as $vehicle): ?>
                        <option value="<?= (int) $vehicle['id'] ?>" <?= $filters['vehicle_id'] === (int) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['vehicle_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-mechanics'): ?>
            <label>
                Mechanic
                <select class="form-select" name="mechanic_id">
                    <option value="0">All Mechanics</option>
                    <?php foreach ($lookups['mechanics'] as $mechanic): ?>
                        <option value="<?= (int) $mechanic['id'] ?>" <?= $filters['mechanic_id'] === (int) $mechanic['id'] ? 'selected' : '' ?>><?= e($mechanic['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Job Status
                <select class="form-select" name="job_status">
                    <option value="">All</option>
                    <option value="pending" <?= $filters['job_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="ongoing" <?= $filters['job_status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="completed" <?= $filters['job_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filters['job_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-bays'): ?>
            <label>
                Bay
                <select class="form-select" name="bay_id">
                    <option value="0">All Bays</option>
                    <?php foreach ($lookups['bays'] as $bay): ?>
                        <option value="<?= (int) $bay['id'] ?>" <?= $filters['bay_id'] === (int) $bay['id'] ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="maintenance" <?= $filters['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-payments'): ?>
            <label>
                Payment Method
                <select class="form-select" name="payment_method">
                    <option value="">All</option>
                    <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Online Transfer', 'cheque' => 'Cheque', 'other' => 'Other'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['payment_method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Invoice
                <input class="form-control" type="number" min="1" name="invoice_id" value="<?= e((string) $filters['invoice_id']) ?>" placeholder="Invoice ID">
            </label>
            <label>
                Received By
                <select class="form-select" name="received_by">
                    <option value="0">All Users</option>
                    <?php foreach ($lookups['users'] as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= $filters['received_by'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($section === 'reports-customers'): ?>
            <label>
                Outstanding Status
                <select class="form-select" name="outstanding_status">
                    <option value="">All</option>
                    <option value="with_outstanding" <?= $filters['outstanding_status'] === 'with_outstanding' ? 'selected' : '' ?>>With Outstanding</option>
                    <option value="clear" <?= $filters['outstanding_status'] === 'clear' ? 'selected' : '' ?>>Clear</option>
                </select>
            </label>
            <label>
                Customer
                <select class="form-select" name="customer_id">
                    <option value="0">All Customers</option>
                    <?php foreach ($lookups['customers'] as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= $filters['customer_id'] === (int) $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <div class="report-filter-actions">
            <a class="btn btn-light" href="index.php?page=admin&amp;section=<?= e($section) ?>">Reset</a>
            <button class="btn btn-primary" type="submit">Apply Filters</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if (in_array($section, ['reports-stock'], true)): ?>
    <div class="report-tabs no-print">
        <?php foreach (['current' => 'Current Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock', 'movements' => 'Movements', 'purchases' => 'Purchases', 'parts_used' => 'Parts Used', 'parts_sold' => 'Parts Sold'] as $value => $label): ?>
            <a href="index.php?page=admin&amp;section=<?= e($section) ?>&amp;<?= e(report_query_string(['view' => $value, 'report_page' => 1])) ?>" class="<?= ($filters['view'] ?? 'current') === $value ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 report-cards <?= $isSalesReport ? 'report-sales-cards' : '' ?>">
    <?php foreach ($cards as $card): ?>
        <div class="col-sm-6 col-xl-<?= count($cards) >= 6 ? '2' : '3' ?>">
            <div class="admin-kpi report-kpi report-tone-<?= e($card['tone'] ?: 'blue') ?>">
                <?php if ($isSalesReport): ?><span class="report-kpi-icon">*</span><?php endif; ?>
                <div><span><?= e($card['label']) ?></span>
                <strong><?= ($card['currency'] ?? true) ? 'Rs. ' : '' ?><?= e($card['value']) ?></strong>
                <?php if ($isSalesReport): ?><small>Current selected period</small><?php endif; ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($isSalesReport): ?>
    <div class="report-sales-visuals">
        <section class="admin-panel report-chart-panel report-sales-trend-panel">
            <div class="panel-heading">
                <h2><span class="report-panel-icon">~</span> Sales Trend</h2>
                <select class="form-select report-chart-select" aria-label="Chart interval"><option>Daily</option><option>Weekly</option></select>
            </div>
            <div class="report-chart-legend"><span><i class="legend-dot legend-blue"></i>Sales</span><span><i class="legend-dot legend-green"></i>Profit</span></div>
            <?= $report['chart'] ?: '<div class="chart-placeholder"><span>No chart data available.</span></div>' ?>
        </section>
        <section class="admin-panel report-category-panel">
            <div class="panel-heading"><h2><span class="report-panel-icon">#</span> Sales by Category</h2></div>
            <div class="report-category-content">
                <div class="report-donut" style="--donut-stops: <?= e(implode(', ', $salesDonutStops)) ?>"><strong>Rs. <?= e(report_money($salesTotalValue)) ?></strong><span>Total Sales</span></div>
                <div class="report-category-list">
                    <?php foreach ($salesCategories as $category): $percentage = ($category['value'] / $salesCategoryTotal) * 100; ?>
                        <div class="report-category-row"><span><i style="background:<?= e($category['color']) ?>"></i><?= e($category['label']) ?></span><b><?= number_format($percentage, 1) ?>% <em>Rs. <?= e(report_money($category['value'])) ?></em></b></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
<?php elseif (!empty($report['chart'])): ?>
    <section class="admin-panel report-chart-panel">
        <div class="panel-heading">
            <h2><?= e($section === 'reports-profit-loss' || $section === 'reports-today-profit' ? 'Revenue, Parts Cost & Gross Profit' : 'Trend Chart') ?></h2>
        </div>
        <div class="report-chart-wrap">
            <?= $report['chart'] ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($report['extra']['breakdown'])): ?>
    <section class="admin-panel report-breakdown-panel">
        <div class="row g-3">
            <?php foreach ($report['extra']['breakdown'] as $bucket): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="report-breakdown-card">
                        <strong><?= e($bucket['section']) ?></strong>
                        <?php foreach ($bucket['items'] as $item): ?>
                            <div><span><?= e($item['label']) ?></span><b><?= e(report_money($item['value'])) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($report['extra']['mini_tables'])): ?>
    <div class="row g-4">
        <?php foreach ($report['extra']['mini_tables'] as $mini): ?>
            <div class="col-lg-4">
                <section class="admin-panel report-mini-table">
                    <div class="panel-heading"><h2><?= e($mini['title']) ?></h2></div>
                    <div class="table-responsive">
                        <table class="table admin-table">
                            <thead>
                                <tr>
                                    <?php foreach ($mini['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$mini['rows']): ?>
                                    <tr><td colspan="<?= count($mini['columns']) ?>" class="empty-table">No records found.</td></tr>
                                <?php else: foreach ($mini['rows'] as $miniRow): ?>
                                    <tr>
                                        <?php foreach (array_keys($mini['columns']) as $key): ?><td><?= e(report_cell_display($key, $miniRow[$key] ?? '')) ?></td><?php endforeach; ?>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="admin-panel report-table-panel <?= $isSalesReport ? 'report-sales-details' : '' ?>">
    <div class="panel-heading">
        <h2><?= $isSalesReport ? '<span class="report-panel-icon">+</span> Sales Details' : 'Detailed Report' ?></h2>
        <span class="muted-label"><?= number_format((int) $report['total_rows']) ?> records</span>
    </div>
    <?php if ($isSalesReport): ?><div class="report-table-search"><span>?</span><input type="search" placeholder="Search by invoice no, customer or vehicle..." data-report-search></div><?php endif; ?>
    <div class="table-responsive">
        <table class="table admin-table report-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= count($columns) ?>" class="empty-table">No report data found for the selected filters.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($columns) as $key): ?>
                            <td><?= e(report_cell_display($key, $row[$key] ?? '')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$printMode && $report['total_rows'] > $filters['per_page']): ?>
        <?php $totalPages = (int) ceil($report['total_rows'] / $filters['per_page']); ?>
        <div class="report-pagination no-print">
            <span>Showing page <?= (int) $filters['page'] ?> of <?= $totalPages ?></span>
            <div>
                <?php for ($pageNumber = 1; $pageNumber <= min($totalPages, 8); $pageNumber++): ?>
                    <a class="<?= $pageNumber === $filters['page'] ? 'active' : '' ?>" href="index.php?page=admin&amp;section=<?= e($section) ?>&amp;<?= e(report_query_string(['report_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if (!$printMode): ?>
<script>
document.querySelectorAll('[data-custom-date]').forEach(function (node) {
    var select = document.querySelector('select[name="date_mode"]');
    var hiddenInput = document.querySelector('[data-date-mode-input]');
    var refresh = function () {
        var show = select ? select.value === 'custom' : (hiddenInput && hiddenInput.value === 'custom');
        node.classList.toggle('d-none', !show);
    };
    if (select) {
        select.addEventListener('change', refresh);
        refresh();
    }
});
document.querySelectorAll('[data-date-mode]').forEach(function (button) {
    button.addEventListener('click', function () {
        var mode = button.getAttribute('data-date-mode');
        var input = document.querySelector('[data-date-mode-input]');
        if (input) input.value = mode;
        document.querySelectorAll('[data-date-mode]').forEach(function (item) { item.classList.toggle('active', item === button); });
        document.querySelectorAll('[data-custom-date]').forEach(function (node) { node.classList.toggle('d-none', mode !== 'custom'); });
    });
});
var reportSearch = document.querySelector('[data-report-search]');
if (reportSearch) {
    reportSearch.addEventListener('input', function () {
        var query = reportSearch.value.toLowerCase().trim();
        document.querySelectorAll('.report-sales-details tbody tr').forEach(function (row) {
            row.style.display = !query || row.textContent.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
        });
    });
}
</script>
<?php endif; ?>
