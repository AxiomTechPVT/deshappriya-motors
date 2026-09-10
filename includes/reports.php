<?php

declare(strict_types=1);

function report_definitions(): array
{
    return [
        'reports-sales' => [
            'title' => 'Sales Reports',
            'subtitle' => 'Garage sales performance from invoices and their line items.',
        ],
        'reports-job-cards' => [
            'title' => 'Job Card Reports',
            'subtitle' => 'Track job card workload, status, and invoice value.',
        ],
        'reports-invoices' => [
            'title' => 'Invoice Reports',
            'subtitle' => 'Monitor invoice totals, payments, dues, and statuses.',
        ],
        'reports-expenses' => [
            'title' => 'Expense Reports',
            'subtitle' => 'Review operating expenses and external part purchases.',
        ],
        'reports-stock' => [
            'title' => 'Stock Reports',
            'subtitle' => 'Inspect current stock, movements, purchases, and FIFO usage.',
        ],
        'reports-customers' => [
            'title' => 'Customer Reports',
            'subtitle' => 'See customer activity, spending, and outstanding balances.',
        ],
        'reports-vehicles' => [
            'title' => 'Vehicle Reports',
            'subtitle' => 'Understand service history and totals for each vehicle.',
        ],
        'reports-mechanics' => [
            'title' => 'Mechanic Reports',
            'subtitle' => 'Measure assigned jobs, completion time, and service value.',
        ],
        'reports-bays' => [
            'title' => 'Bay Reports',
            'subtitle' => 'Monitor bay utilisation and job activity.',
        ],
        'reports-payments' => [
            'title' => 'Payment Reports',
            'subtitle' => 'Track payment receipts and applied collections.',
        ],
        'reports-profit-loss' => [
            'title' => 'Profit & Loss',
            'subtitle' => 'Detailed profit report using invoice revenue, FIFO cost, and expenses.',
        ],
        'reports-today-profit' => [
            'title' => 'Today Profit',
            'subtitle' => 'Quick daily profit view for the current business date.',
        ],
    ];
}

function report_date_mode(string $section): string
{
    return $section === 'reports-today-profit' ? 'today' : 'month';
}

function report_date_bounds(string $mode, ?string $from, ?string $to): array
{
    $today = date('Y-m-d');
    if ($mode === 'today') {
        return ['from' => $today, 'to' => $today, 'label' => 'Today'];
    }

    if ($mode === 'week') {
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = $today;
        return ['from' => $start, 'to' => $end, 'label' => 'This Week'];
    }

    if ($mode === 'custom' && $from !== '' && $to !== '') {
        return ['from' => $from, 'to' => $to, 'label' => $from . ' to ' . $to];
    }

    $start = date('Y-m-01');
    $end = $today;
    return ['from' => $start, 'to' => $end, 'label' => 'This Month'];
}

function report_money(float|int|string|null $value): string
{
    return number_format((float) $value, 2);
}

function report_money_value(float|int|string|null $value): float
{
    return round((float) $value, 2);
}

function report_duration_display(float|int|string|null $minutes): string
{
    $totalMinutes = max(0, (float) $minutes);
    if ($totalMinutes <= 0) {
        return '0 min';
    }

    $hours = (int) floor($totalMinutes / 60);
    $remainingMinutes = (int) floor($totalMinutes - ($hours * 60));
    $seconds = (int) round(($totalMinutes - floor($totalMinutes)) * 60);

    if ($seconds === 60) {
        $remainingMinutes++;
        $seconds = 0;
    }
    if ($remainingMinutes === 60) {
        $hours++;
        $remainingMinutes = 0;
    }

    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' hr';
    }
    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . ' min';
    }
    if ($hours === 0 && $remainingMinutes === 0 && $seconds > 0) {
        $parts[] = $seconds . ' sec';
    }

    return implode(' ', $parts);
}

function report_sql_value(string $sql, array $params = []): float
{
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return report_money_value($statement->fetchColumn() ?: 0);
}

function report_sql_rows(string $sql, array $params = []): array
{
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function report_count_rows(string $sql, array $params = []): int
{
    $statement = database()->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS report_count');
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function report_page_rows(string $sql, array $params, int $page, int $perPage): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $statement = database()->prepare($sql . ' LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) {
        $statement->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function report_query_string(array $override = []): string
{
    $params = array_merge($_GET, $override);
    return http_build_query($params);
}

function report_filters_from_request(string $section): array
{
    $mode = trim((string) ($_GET['date_mode'] ?? report_date_mode($section)));
    if (!in_array($mode, ['today', 'week', 'month', 'custom'], true)) {
        $mode = report_date_mode($section);
    }

    $perPage = (int) ($_GET['per_page'] ?? 25);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 25;
    }

    return [
        'date_mode' => $mode,
        'from_date' => trim((string) ($_GET['from_date'] ?? '')),
        'to_date' => trim((string) ($_GET['to_date'] ?? '')),
        'per_page' => $perPage,
        'page' => max(1, (int) ($_GET['report_page'] ?? 1)),
        'customer_id' => max(0, (int) ($_GET['customer_id'] ?? 0)),
        'vehicle_id' => max(0, (int) ($_GET['vehicle_id'] ?? 0)),
        'mechanic_id' => max(0, (int) ($_GET['mechanic_id'] ?? 0)),
        'bay_id' => max(0, (int) ($_GET['bay_id'] ?? 0)),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'invoice_type' => trim((string) ($_GET['invoice_type'] ?? '')),
        'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
        'payment_method' => trim((string) ($_GET['payment_method'] ?? '')),
        'expense_category' => trim((string) ($_GET['expense_category'] ?? '')),
        'stock_view' => trim((string) ($_GET['stock_view'] ?? 'current')),
        'supplier_id' => max(0, (int) ($_GET['supplier_id'] ?? 0)),
        'job_status' => trim((string) ($_GET['job_status'] ?? '')),
        'outstanding_status' => trim((string) ($_GET['outstanding_status'] ?? '')),
        'make_model' => trim((string) ($_GET['make_model'] ?? '')),
        'received_by' => max(0, (int) ($_GET['received_by'] ?? 0)),
        'invoice_id' => max(0, (int) ($_GET['invoice_id'] ?? 0)),
        'stock_item_id' => max(0, (int) ($_GET['stock_item_id'] ?? 0)),
        'view' => trim((string) ($_GET['view'] ?? 'current')),
    ];
}

function report_lookup_options(): array
{
    static $options;
    if (is_array($options)) {
        return $options;
    }

    $options = [
        'customers' => report_sql_rows('SELECT id, name FROM customers ORDER BY name'),
        'vehicles' => report_sql_rows('SELECT id, vehicle_number FROM vehicles ORDER BY vehicle_number'),
        'mechanics' => report_sql_rows('SELECT id, name FROM employees ORDER BY name'),
        'bays' => report_sql_rows('SELECT id, bay_name FROM bays ORDER BY bay_name'),
        'suppliers' => report_sql_rows('SELECT id, name FROM suppliers ORDER BY name'),
        'users' => report_sql_rows('SELECT id, name FROM users ORDER BY name'),
        'categories' => report_sql_rows("SELECT DISTINCT category AS name FROM expenses WHERE category IS NOT NULL AND category<>'' ORDER BY category"),
        'stock_items' => report_sql_rows('SELECT id, part_code, part_name FROM stock_items ORDER BY part_name'),
    ];

    return $options;
}

function report_svg_line(array $points, string $stroke = '#155eef', string $fill = '#dbeafe'): string
{
    if (!$points) {
        return '<div class="chart-placeholder"><span>No chart data available.</span></div>';
    }

    $width = 720;
    $height = 260;
    $padding = 28;
    $values = array_map(static fn($row) => (float) $row['value'], $points);
    $max = max(1, max($values));
    $count = count($points);
    $step = $count > 1 ? ($width - ($padding * 2)) / ($count - 1) : 0;
    $coords = [];
    $poly = [];
    foreach ($points as $index => $point) {
        $x = $padding + ($step * $index);
        $y = $height - $padding - (((float) $point['value'] / $max) * ($height - ($padding * 2)));
        $coords[] = ['x' => $x, 'y' => $y, 'label' => (string) $point['label'], 'value' => (float) $point['value']];
        $poly[] = $x . ',' . $y;
    }
    $area = implode(' ', array_merge([$padding . ',' . ($height - $padding)], $poly, [($width - $padding) . ',' . ($height - $padding)]));
    ob_start();
    ?>
    <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="report-chart-svg" role="img" aria-label="Trend chart">
        <defs>
            <linearGradient id="reportTrendFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="<?= e($fill) ?>" stop-opacity="0.9"></stop>
                <stop offset="100%" stop-color="<?= e($fill) ?>" stop-opacity="0.1"></stop>
            </linearGradient>
        </defs>
        <rect x="0" y="0" width="<?= $width ?>" height="<?= $height ?>" rx="18" fill="#fff"></rect>
        <path d="M <?= $area ?>" fill="url(#reportTrendFill)" opacity=".85"></path>
        <polyline points="<?= e(implode(' ', $poly)) ?>" fill="none" stroke="<?= e($stroke) ?>" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
        <?php foreach ($coords as $dot): ?>
            <circle cx="<?= $dot['x'] ?>" cy="<?= $dot['y'] ?>" r="5.5" fill="<?= e($stroke) ?>"></circle>
            <text x="<?= $dot['x'] ?>" y="<?= $dot['y'] - 14 ?>" text-anchor="middle" fill="#475467" font-size="12"><?= e(report_money($dot['value'])) ?></text>
            <text x="<?= $dot['x'] ?>" y="<?= $height - 8 ?>" text-anchor="middle" fill="#667085" font-size="11"><?= e($dot['label']) ?></text>
        <?php endforeach; ?>
    </svg>
    <?php
    return (string) ob_get_clean();
}

function report_svg_bars(array $bars): string
{
    if (!$bars) {
        return '<div class="chart-placeholder"><span>No chart data available.</span></div>';
    }

    $width = 720;
    $height = 260;
    $padding = 28;
    $max = max(1, max(array_map(static fn($bar) => abs((float) $bar['value']), $bars)));
    $barWidth = max(26, (int) (($width - ($padding * 2)) / max(1, count($bars) * 1.45)));
    $gap = ($width - ($padding * 2) - ($barWidth * count($bars))) / max(1, count($bars) - 1);
    if ($gap < 18) {
        $gap = 18;
    }
    ob_start();
    ?>
    <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="report-chart-svg" role="img" aria-label="Income and expense chart">
        <rect x="0" y="0" width="<?= $width ?>" height="<?= $height ?>" rx="18" fill="#fff"></rect>
        <?php foreach ($bars as $index => $bar): ?>
            <?php
            $value = (float) $bar['value'];
            $barHeight = max(2, (($height - ($padding * 2)) * abs($value)) / $max);
            $x = $padding + ($index * ($barWidth + $gap));
            $y = $height - $padding - $barHeight;
            $fill = $bar['color'] ?? '#155eef';
            ?>
            <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $barWidth ?>" height="<?= $barHeight ?>" rx="8" fill="<?= e($fill) ?>"></rect>
            <text x="<?= $x + ($barWidth / 2) ?>" y="<?= $y - 10 ?>" text-anchor="middle" fill="#475467" font-size="12"><?= e(report_money($value)) ?></text>
            <text x="<?= $x + ($barWidth / 2) ?>" y="<?= $height - 8 ?>" text-anchor="middle" fill="#667085" font-size="11"><?= e((string) $bar['label']) ?></text>
        <?php endforeach; ?>
    </svg>
    <?php
    return (string) ob_get_clean();
}

function report_export_tsv(string $filename, array $columns, array $rows): never
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo implode("\t", $columns) . "\n";
    foreach ($rows as $row) {
        $line = [];
        foreach (array_keys($columns) as $key) {
            $line[] = str_replace(["\t", "\r", "\n"], ' ', (string) ($row[$key] ?? ''));
        }
        echo implode("\t", $line) . "\n";
    }
    exit;
}

function report_apply_date_filter(string $column, array &$where, array &$params, array $bounds): void
{
    $where[] = $column . ' BETWEEN :date_from AND :date_to';
    $params['date_from'] = $bounds['from'];
    $params['date_to'] = $bounds['to'];
}

function report_summary_card(string $label, mixed $value, string $tone = ''): array
{
    return ['label' => $label, 'value' => report_money((float) $value), 'tone' => $tone, 'currency' => true];
}

function report_count_card(string $label, mixed $value, string $tone = ''): array
{
    return ['label' => $label, 'value' => number_format((float) $value, 0), 'tone' => $tone, 'currency' => false];
}

function report_build(string $section, array $filters, bool $exportAll = false): array
{
    $defs = report_definitions();
    if (!isset($defs[$section])) {
        return [];
    }

    $bounds = report_date_bounds($filters['date_mode'], $filters['from_date'], $filters['to_date']);
    $lookups = report_lookup_options();
    $limit = $exportAll ? 1000000 : $filters['per_page'];
    $page = $exportAll ? 1 : $filters['page'];
    $baseWhere = [];
    $params = [];
    $chart = '';
    $columns = [];
    $rows = [];
    $totalRows = 0;
    $cards = [];
    $extra = [];

    if ($section === 'reports-sales') {
        $baseWhere = ['i.payment_status <> "cancelled"'];
        report_apply_date_filter('i.invoice_date', $baseWhere, $params, $bounds);
        if ($filters['invoice_type'] !== '') {
            $baseWhere[] = 'i.invoice_type = :invoice_type';
            $params['invoice_type'] = $filters['invoice_type'];
        }
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'i.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['payment_status'] !== '') {
            $baseWhere[] = 'i.payment_status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }

        $salesTotals = report_sql_rows(
            'SELECT
                COALESCE(SUM(i.total_amount),0) AS total_sales,
                COALESCE(SUM(CASE WHEN i.invoice_type="job_card" THEN i.total_amount ELSE 0 END),0) AS job_card_sales,
                COALESCE(SUM(CASE WHEN i.invoice_type="quick" THEN i.total_amount ELSE 0 END),0) AS quick_sales,
                COALESCE(SUM(i.special_service_charge),0) AS special_charge,
                COALESCE(SUM(i.discount_amount),0) AS total_discount
            FROM invoices i
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $salesItemTotals = report_sql_rows(
            'SELECT
                COALESCE(SUM(CASE WHEN ii.item_type IN ("stock_part","external_part") THEN ii.line_total ELSE 0 END),0) AS parts_sales,
                COALESCE(SUM(CASE WHEN ii.item_type="service" THEN ii.line_total ELSE 0 END),0) AS service_income
            FROM invoices i
            JOIN invoice_items ii ON ii.invoice_id = i.id
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $receiptWhere = ['j.status = "completed"', 'p.id = (SELECT MAX(p2.id) FROM job_card_payments p2 WHERE p2.job_card_id = j.id)', 'NOT EXISTS (SELECT 1 FROM invoices i2 WHERE i2.job_card_id = j.id AND i2.payment_status <> "cancelled")'];
        $receiptParams = [];
        report_apply_date_filter('DATE(p.paid_at)', $receiptWhere, $receiptParams, $bounds);
        if ($filters['invoice_type'] !== '' && $filters['invoice_type'] !== 'job_card') {
            $receiptWhere[] = '1 = 0';
        }
        if ($filters['customer_id'] > 0) {
            $receiptWhere[] = 'j.customer_id = :receipt_customer_id';
            $receiptParams['receipt_customer_id'] = $filters['customer_id'];
        }
        if ($filters['payment_status'] !== '') {
            $receiptWhere[] = '(CASE WHEN j.balance_amount <= 0.009 THEN "paid" ELSE "partial" END) = :receipt_payment_status';
            $receiptParams['receipt_payment_status'] = $filters['payment_status'];
        }
        $receiptTotals = report_sql_rows(
            'SELECT COALESCE(SUM(j.total_amount),0) AS receipt_sales FROM job_card_payments p JOIN job_cards j ON j.id = p.job_card_id WHERE ' . implode(' AND ', $receiptWhere),
            $receiptParams
        )[0] ?? [];
        $receiptRows = report_sql_rows(
            'SELECT DATE(p.paid_at) AS invoice_date, p.receipt_no AS invoice_no, "job_card" AS invoice_type,
                    0 AS special_service_charge, 0 AS discount_amount, j.total_amount AS total_amount,
                    CASE WHEN j.balance_amount <= 0.009 THEN "paid" ELSE "partial" END AS payment_status,
                    COALESCE(c.name, "Walk-in Customer") AS customer_name,
                    COALESCE(v.vehicle_number, "-") AS vehicle_number,
                    COALESCE((SELECT SUM(amount) FROM job_card_items ji WHERE ji.job_card_id = j.id AND ji.item_type IN ("part", "manual")), 0) AS parts_amount,
                    COALESCE((SELECT SUM(amount) FROM job_card_items ji WHERE ji.job_card_id = j.id AND ji.item_type = "service"), 0) + COALESCE((SELECT amount FROM job_card_service_charges sc WHERE sc.job_card_id = j.id), j.service_charge, 0) AS service_amount
             FROM job_card_payments p
             JOIN job_cards j ON j.id = p.job_card_id
             JOIN customers c ON c.id = j.customer_id
             LEFT JOIN vehicles v ON v.id = j.vehicle_id
             WHERE ' . implode(' AND ', $receiptWhere) . '
             ORDER BY p.paid_at DESC, p.id DESC',
            $receiptParams
        );
        $completedJobWhere = ['j.status = "completed"', 'j.completed_at IS NOT NULL', 'NOT EXISTS (SELECT 1 FROM job_card_payments p2 WHERE p2.job_card_id = j.id)'];
        $completedJobParams = [];
        report_apply_date_filter('DATE(j.completed_at)', $completedJobWhere, $completedJobParams, $bounds);
        if ($filters['invoice_type'] !== '' && $filters['invoice_type'] !== 'job_card') $completedJobWhere[] = '1 = 0';
        if ($filters['customer_id'] > 0) {
            $completedJobWhere[] = 'j.customer_id = :completed_customer_id';
            $completedJobParams['completed_customer_id'] = $filters['customer_id'];
        }
        if ($filters['payment_status'] !== '') {
            $completedJobWhere[] = '(CASE WHEN j.balance_amount <= 0.009 THEN "paid" WHEN j.paid_amount > 0 THEN "partial" ELSE "due" END) = :completed_payment_status';
            $completedJobParams['completed_payment_status'] = $filters['payment_status'];
        }
        $completedJobRows = report_sql_rows(
            'SELECT DATE(j.completed_at) AS invoice_date, j.job_card_no AS invoice_no, "job_card" AS invoice_type,
                    0 AS special_service_charge, 0 AS discount_amount, j.total_amount,
                    CASE WHEN j.balance_amount <= 0.009 THEN "paid" WHEN j.paid_amount > 0 THEN "partial" ELSE "due" END AS payment_status,
                    COALESCE(c.name, "Walk-in Customer") AS customer_name,
                    COALESCE(v.vehicle_number, "-") AS vehicle_number,
                    COALESCE((SELECT SUM(amount) FROM job_card_items ji WHERE ji.job_card_id = j.id AND ji.item_type IN ("part", "manual")), 0) AS parts_amount,
                    COALESCE((SELECT SUM(amount) FROM job_card_items ji WHERE ji.job_card_id = j.id AND ji.item_type = "service"), 0) + COALESCE((SELECT amount FROM job_card_service_charges sc WHERE sc.job_card_id = j.id), j.service_charge, 0) AS service_amount
             FROM job_cards j
             JOIN customers c ON c.id = j.customer_id
             LEFT JOIN vehicles v ON v.id = j.vehicle_id
             WHERE ' . implode(' AND ', $completedJobWhere) . '
             ORDER BY j.completed_at DESC, j.id DESC',
            $completedJobParams
        );
        $completedJobTotal = array_sum(array_map(static fn(array $row): float => (float)$row['total_amount'], $completedJobRows));
        $receiptSales = (float)($receiptTotals['receipt_sales'] ?? 0) + $completedJobTotal;
        $salesTotals['total_sales'] = (float)($salesTotals['total_sales'] ?? 0) + $receiptSales;
        $salesTotals['job_card_sales'] = (float)($salesTotals['job_card_sales'] ?? 0) + $receiptSales;
        $receiptRows = array_merge($receiptRows, $completedJobRows);
        $salesItemTotals['parts_sales'] = (float)($salesItemTotals['parts_sales'] ?? 0) + array_sum(array_map(static fn(array $row): float => (float)$row['parts_amount'], $receiptRows));
        $salesItemTotals['service_income'] = (float)($salesItemTotals['service_income'] ?? 0) + array_sum(array_map(static fn(array $row): float => (float)$row['service_amount'], $receiptRows));
        $cards = [
            report_summary_card('Total Sales', $salesTotals['total_sales'] ?? 0, 'blue'),
            report_summary_card('Job Card Sales', $salesTotals['job_card_sales'] ?? 0, 'green'),
            report_summary_card('Quick Invoice Sales', $salesTotals['quick_sales'] ?? 0, 'orange'),
            report_summary_card('Spare Parts Sales', $salesItemTotals['parts_sales'] ?? 0, 'purple'),
            report_summary_card('Service Income', $salesItemTotals['service_income'] ?? 0, 'navy'),
            report_summary_card('Special Service Charges', $salesTotals['special_charge'] ?? 0, 'teal'),
            report_summary_card('Total Discount', $salesTotals['total_discount'] ?? 0, 'red'),
        ];
        $trend = report_sql_rows(
            'SELECT DATE(i.invoice_date) AS label, COALESCE(SUM(i.total_amount),0) AS value
             FROM invoices i
             WHERE ' . implode(' AND ', $baseWhere) . '
             GROUP BY DATE(i.invoice_date)
             ORDER BY DATE(i.invoice_date)',
            $params
        );
        foreach ($receiptRows as $receiptRow) {
            $trend[] = ['label' => $receiptRow['invoice_date'], 'value' => $receiptRow['total_amount']];
        }
        $trendByDate = [];
        foreach ($trend as $trendRow) {
            $trendByDate[$trendRow['label']] = ($trendByDate[$trendRow['label']] ?? 0) + (float)$trendRow['value'];
        }
        $trend = [];
        foreach ($trendByDate as $label => $value) $trend[] = ['label' => $label, 'value' => $value];
        usort($trend, static fn(array $a, array $b): int => strcmp((string)$a['label'], (string)$b['label']));
        $chart = report_svg_line($trend);
        $columns = [
            'invoice_date' => 'Date',
            'invoice_no' => 'Invoice No',
            'invoice_type_label' => 'Invoice Type',
            'customer_name' => 'Customer',
            'vehicle_number' => 'Vehicle',
            'parts_amount' => 'Parts Amount',
            'service_amount' => 'Service Amount',
            'special_service_charge' => 'Special Charge',
            'discount_amount' => 'Discount',
            'total_amount' => 'Total',
            'payment_status_label' => 'Payment Status',
        ];
        $sql = 'SELECT
                i.id, i.invoice_date, i.invoice_no, i.invoice_type, i.special_service_charge, i.discount_amount, i.total_amount, i.payment_status,
                COALESCE(c.name, "Walk-in Customer") AS customer_name,
                COALESCE(v.vehicle_number, "-") AS vehicle_number,
                SUM(CASE WHEN ii.item_type IN ("stock_part","external_part") THEN ii.line_total ELSE 0 END) AS parts_amount,
                SUM(CASE WHEN ii.item_type = "service" THEN ii.line_total ELSE 0 END) AS service_amount
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            LEFT JOIN vehicles v ON v.id = i.vehicle_id
            LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
            WHERE ' . implode(' AND ', $baseWhere) . '
            GROUP BY i.id';
        $totalRows = report_count_rows($sql, $params) + count($receiptRows);
        $rows = report_page_rows(
            $sql . ' ORDER BY i.invoice_date DESC, i.id DESC',
            $params,
            $page,
            $limit
        );
        foreach ($rows as &$row) {
            $row['invoice_type_label'] = $row['invoice_type'] === 'job_card' ? 'Job Card' : 'Quick Invoice';
            $row['payment_status_label'] = ucfirst((string) $row['payment_status']);
        }
        unset($row);
        $rows = array_merge($rows, $receiptRows);
        foreach ($rows as &$row) {
            $row['invoice_type_label'] = $row['invoice_type'] === 'job_card' ? 'Job Card' : 'Quick Invoice';
            $row['payment_status_label'] = ucfirst((string) $row['payment_status']);
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => strcmp((string)$b['invoice_date'], (string)$a['invoice_date']));
    } elseif ($section === 'reports-job-cards') {
        $baseWhere = ['1=1'];
        report_apply_date_filter('DATE(j.created_at)', $baseWhere, $params, $bounds);
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'j.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['vehicle_id'] > 0) {
            $baseWhere[] = 'j.vehicle_id = :vehicle_id';
            $params['vehicle_id'] = $filters['vehicle_id'];
        }
        if ($filters['mechanic_id'] > 0) {
            $baseWhere[] = 'j.mechanic_id = :mechanic_id';
            $params['mechanic_id'] = $filters['mechanic_id'];
        }
        if ($filters['bay_id'] > 0) {
            $baseWhere[] = 'j.bay_name = (SELECT bay_name FROM bays WHERE id = :bay_id LIMIT 1)';
            $params['bay_id'] = $filters['bay_id'];
        }
        if ($filters['job_status'] !== '') {
            $baseWhere[] = 'j.status = :job_status';
            $params['job_status'] = $filters['job_status'];
        }
        $summary = report_sql_rows(
            'SELECT
                COUNT(*) AS total_cards,
                SUM(j.status="pending") AS pending_cards,
                SUM(j.status="ongoing") AS ongoing_cards,
                SUM(j.status="completed") AS completed_cards,
                SUM(j.status="cancelled") AS cancelled_cards
            FROM job_cards j
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $cards = [
            report_count_card('Total Job Cards', $summary['total_cards'] ?? 0, 'blue'),
            report_count_card('Pending', $summary['pending_cards'] ?? 0, 'orange'),
            report_count_card('Ongoing', $summary['ongoing_cards'] ?? 0, 'navy'),
            report_count_card('Completed', $summary['completed_cards'] ?? 0, 'green'),
            report_count_card('Cancelled', $summary['cancelled_cards'] ?? 0, 'red'),
        ];
        $columns = [
            'job_card_no' => 'Job Card No',
            'created_at' => 'Date',
            'customer_name' => 'Customer',
            'vehicle_number' => 'Vehicle',
            'mechanic_name' => 'Mechanic',
            'bay_name' => 'Bay',
            'status_label' => 'Status',
            'completed_date' => 'Completed Date',
            'invoice_total' => 'Invoice Total',
        ];
        $sql = 'SELECT
                j.id, j.job_card_no, j.created_at, j.bay_name, j.status,
                COALESCE(c.name, "") AS customer_name,
                COALESCE(v.vehicle_number, "-") AS vehicle_number,
                COALESCE(e.name, "-") AS mechanic_name,
                COALESCE(i.total_amount, 0) AS invoice_total,
                j.completed_at AS completed_date
            FROM job_cards j
            JOIN customers c ON c.id = j.customer_id
            LEFT JOIN vehicles v ON v.id = j.vehicle_id
            LEFT JOIN employees e ON e.id = j.mechanic_id
            LEFT JOIN invoices i ON i.job_card_id = j.id
            WHERE ' . implode(' AND ', $baseWhere);
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY j.created_at DESC, j.id DESC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['status_label'] = ucfirst((string) $row['status']);
            $row['created_at'] = date('Y-m-d', strtotime((string) $row['created_at']));
            $row['completed_date'] = $row['completed_date'] ? date('Y-m-d', strtotime((string) $row['completed_date'])) : '-';
        }
        unset($row);
    } elseif ($section === 'reports-invoices') {
        $baseWhere = ['i.payment_status <> "cancelled"'];
        report_apply_date_filter('i.invoice_date', $baseWhere, $params, $bounds);
        if ($filters['invoice_type'] !== '') {
            $baseWhere[] = 'i.invoice_type = :invoice_type';
            $params['invoice_type'] = $filters['invoice_type'];
        }
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'i.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['payment_status'] !== '') {
            $baseWhere[] = 'i.payment_status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }
        $summary = report_sql_rows(
            'SELECT
                COUNT(*) AS total_invoices,
                SUM(i.payment_status = "paid") AS paid_invoices,
                SUM(i.payment_status = "partial") AS partial_invoices,
                SUM(i.payment_status = "due") AS due_invoices,
                COALESCE(SUM(i.total_amount),0) AS total_invoice_value,
                COALESCE(SUM(i.balance_amount),0) AS total_outstanding
            FROM invoices i
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $cards = [
            report_count_card('Total Invoices', $summary['total_invoices'] ?? 0, 'blue'),
            report_count_card('Paid Invoices', $summary['paid_invoices'] ?? 0, 'green'),
            report_count_card('Partial Invoices', $summary['partial_invoices'] ?? 0, 'orange'),
            report_count_card('Due Invoices', $summary['due_invoices'] ?? 0, 'red'),
            report_summary_card('Total Invoice Value', $summary['total_invoice_value'] ?? 0, 'navy'),
            report_summary_card('Total Outstanding', $summary['total_outstanding'] ?? 0, 'purple'),
        ];
        $columns = [
            'invoice_no' => 'Invoice No',
            'invoice_date' => 'Date',
            'invoice_type_label' => 'Type',
            'job_card_no' => 'Job Card',
            'customer_name' => 'Customer',
            'vehicle_number' => 'Vehicle',
            'total_amount' => 'Total',
            'paid_amount' => 'Paid',
            'balance_amount' => 'Due',
            'payment_status_label' => 'Payment Status',
        ];
        $sql = 'SELECT
                i.id, i.invoice_no, i.invoice_date, i.invoice_type, i.job_card_id, i.total_amount, i.paid_amount, i.balance_amount, i.payment_status,
                COALESCE(j.job_card_no, "-") AS job_card_no,
                COALESCE(c.name, "Walk-in Customer") AS customer_name,
                COALESCE(v.vehicle_number, "-") AS vehicle_number
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            LEFT JOIN vehicles v ON v.id = i.vehicle_id
            LEFT JOIN job_cards j ON j.id = i.job_card_id
            WHERE ' . implode(' AND ', $baseWhere);
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY i.invoice_date DESC, i.id DESC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['invoice_type_label'] = $row['invoice_type'] === 'job_card' ? 'Job Card' : 'Quick Invoice';
            $row['payment_status_label'] = ucfirst((string) $row['payment_status']);
        }
        unset($row);
    } elseif ($section === 'reports-expenses') {
        $baseWhere = ['e.status <> "cancelled"'];
        report_apply_date_filter('e.expense_date', $baseWhere, $params, $bounds);
        if ($filters['expense_category'] !== '') {
            $baseWhere[] = 'e.category = :expense_category';
            $params['expense_category'] = $filters['expense_category'];
        }
        if ($filters['supplier_id'] > 0) {
            $baseWhere[] = 'e.supplier_id = :supplier_id';
            $params['supplier_id'] = $filters['supplier_id'];
        }
        if ($filters['payment_status'] !== '') {
            $baseWhere[] = 'e.status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }
        if ($filters['job_status'] !== '') {
            $baseWhere[] = 'e.expense_type = :expense_type_filter';
            $params['expense_type_filter'] = $filters['job_status'] === 'external_part' ? 'external_part' : 'general';
        }
        $summary = report_sql_rows(
            'SELECT
                COALESCE(SUM(e.total_amount),0) AS total_expenses,
                COALESCE(SUM(CASE WHEN e.expense_type = "general" THEN e.total_amount ELSE 0 END),0) AS general_expenses,
                COALESCE(SUM(CASE WHEN e.expense_type = "external_part" THEN e.total_amount ELSE 0 END),0) AS external_part_expenses,
                COALESCE(SUM(CASE WHEN e.status IN ("paid","partial") THEN e.paid_amount ELSE 0 END),0) AS paid_expenses,
                COALESCE(SUM(CASE WHEN e.status IN ("due","partial") THEN e.balance_amount ELSE 0 END),0) AS outstanding_expense_payments
            FROM expenses e
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $cards = [
            report_summary_card('Total Expenses', $summary['total_expenses'] ?? 0, 'blue'),
            report_summary_card('General Expenses', $summary['general_expenses'] ?? 0, 'green'),
            report_summary_card('External Spare Part Expenses', $summary['external_part_expenses'] ?? 0, 'orange'),
            report_summary_card('Paid Expenses', $summary['paid_expenses'] ?? 0, 'navy'),
            report_summary_card('Outstanding Expense Payments', $summary['outstanding_expense_payments'] ?? 0, 'red'),
        ];
        $columns = [
            'expense_no' => 'Expense No',
            'expense_date' => 'Date',
            'expense_type_label' => 'Expense Type',
            'category' => 'Category',
            'description' => 'Description',
            'supplier_name' => 'Supplier',
            'job_card_no' => 'Job Card',
            'total_amount' => 'Amount',
            'paid_amount' => 'Paid',
            'balance_amount' => 'Due',
            'status_label' => 'Payment Status',
        ];
        $sql = 'SELECT
                e.id, e.expense_no, e.expense_date, e.expense_type, e.category, e.description, e.total_amount, e.paid_amount, e.balance_amount, e.status,
                COALESCE(s.name, e.vendor_supplier_name, "-") AS supplier_name,
                COALESCE(j.job_card_no, "-") AS job_card_no
            FROM expenses e
            LEFT JOIN suppliers s ON s.id = e.supplier_id
            LEFT JOIN job_cards j ON j.id = e.job_card_id
            WHERE ' . implode(' AND ', $baseWhere);
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY e.expense_date DESC, e.id DESC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['expense_type_label'] = $row['expense_type'] === 'external_part' ? 'External Part' : 'General';
            $row['status_label'] = ucfirst((string) $row['status']);
        }
        unset($row);
    } elseif ($section === 'reports-stock') {
        $view = in_array($filters['view'], ['current', 'low', 'out', 'movements', 'purchases', 'parts_used', 'parts_sold'], true) ? $filters['view'] : 'current';
        $filters['view'] = $view;
        $summaryRow = report_sql_rows(
            'SELECT
                COUNT(*) AS total_items,
                SUM(stock_qty > reorder_level) AS in_stock,
                SUM(stock_qty > 0 AND stock_qty <= reorder_level) AS low_stock,
                SUM(stock_qty <= 0) AS out_stock
            FROM stock_items
            WHERE status = "active"'
        )[0] ?? [];
        $summaryRow['stock_value'] = report_sql_value(
            'SELECT COALESCE(SUM(quantity_remaining * buying_price),0) FROM stock_batches WHERE quantity_remaining > 0'
        );
        $cards = [
            report_count_card('Total Stock Items', $summaryRow['total_items'] ?? 0, 'blue'),
            report_count_card('In Stock', $summaryRow['in_stock'] ?? 0, 'green'),
            report_count_card('Low Stock', $summaryRow['low_stock'] ?? 0, 'orange'),
            report_count_card('Out of Stock', $summaryRow['out_stock'] ?? 0, 'red'),
            report_summary_card('Current Stock Value', $summaryRow['stock_value'] ?? 0, 'purple'),
        ];
        if ($view === 'movements') {
            $columns = ['created_at' => 'Date / Time', 'part_code' => 'Part Code', 'part_name' => 'Part Name', 'movement_type' => 'Movement', 'quantity' => 'Qty', 'quantity_before' => 'Before', 'quantity_after' => 'After', 'unit_cost' => 'Unit Cost', 'reference_type' => 'Reference Type', 'reference_id' => 'Reference ID', 'user_name' => 'Created By'];
            $sql = 'SELECT sm.id, sm.created_at, si.part_code, si.part_name, sm.movement_type, sm.quantity, sm.quantity_before, sm.quantity_after, sm.unit_cost, sm.reference_type, sm.reference_id, COALESCE(u.name, "-") AS user_name
                FROM stock_movements sm
                JOIN stock_items si ON si.id = sm.stock_item_id
                LEFT JOIN users u ON u.id = sm.created_by
                WHERE sm.created_at BETWEEN :date_from AND :date_to';
            $params = ['date_from' => $bounds['from'], 'date_to' => $bounds['to'] . ' 23:59:59'];
        } elseif ($view === 'purchases') {
            $columns = ['purchase_no' => 'Purchase No', 'purchase_date' => 'Date', 'supplier_name' => 'Supplier', 'invoice_no' => 'Invoice No', 'payment_status_label' => 'Status', 'items_count' => 'Items', 'total_amount' => 'Total', 'paid_amount' => 'Paid', 'balance_amount' => 'Due'];
            $sql = 'SELECT sp.id, sp.purchase_no, sp.purchase_date, sp.invoice_no, sp.payment_status, sp.total_amount, sp.paid_amount, sp.balance_amount, COUNT(pi.id) AS items_count, COALESCE(s.name, "-") AS supplier_name
                FROM stock_purchases sp
                LEFT JOIN suppliers s ON s.id = sp.supplier_id
                LEFT JOIN stock_purchase_items pi ON pi.purchase_id = sp.id
                WHERE sp.purchase_date BETWEEN :date_from AND :date_to
                GROUP BY sp.id';
            $params = ['date_from' => $bounds['from'], 'date_to' => $bounds['to']];
        } elseif ($view === 'parts_used' || $view === 'parts_sold') {
            $referenceType = $view === 'parts_used' ? 'job_card_item' : 'sale';
            $columns = ['created_at' => 'Date / Time', 'part_code' => 'Part Code', 'part_name' => 'Part Name', 'reference_no' => 'Reference', 'quantity' => 'Qty', 'unit_cost' => 'Unit Cost', 'total_cost' => 'Total Cost'];
            $sql = 'SELECT sbc.id, sbc.created_at, si.part_code, si.part_name, sbc.quantity, sbc.unit_cost, sbc.total_cost,
                    COALESCE(jc.job_card_no, i.invoice_no, "-") AS reference_no
                FROM stock_batch_consumptions sbc
                JOIN stock_items si ON si.id = sbc.stock_item_id
                LEFT JOIN job_card_items jci ON jci.id = sbc.reference_id AND sbc.reference_type = "job_card_item"
                LEFT JOIN job_cards jc ON jc.id = jci.job_card_id
                LEFT JOIN invoices i ON i.id = sbc.reference_id AND sbc.reference_type = "sale"
                WHERE sbc.reference_type = :reference_type AND sbc.created_at BETWEEN :date_from AND :date_to';
            $params = ['reference_type' => $referenceType, 'date_from' => $bounds['from'], 'date_to' => $bounds['to'] . ' 23:59:59'];
        } else {
            $columns = ['part_code' => 'Part Code', 'part_name' => 'Part Name', 'brand' => 'Brand', 'available_qty' => 'Available Qty', 'reorder_level' => 'Reorder Level', 'average_cost' => 'Average Cost', 'selling_price' => 'Selling Price', 'stock_value' => 'Stock Value', 'status_label' => 'Status'];
            $stockWhere = ['si.status = "active"'];
            if ($view === 'low') {
                $stockWhere[] = 'si.stock_qty > 0 AND si.stock_qty <= si.reorder_level';
            } elseif ($view === 'out') {
                $stockWhere[] = 'si.stock_qty <= 0';
            }
            if ($filters['stock_item_id'] > 0) {
                $stockWhere[] = 'si.id = :stock_item_id';
                $params['stock_item_id'] = $filters['stock_item_id'];
            }
            $sql = 'SELECT
                    si.id, si.part_code, si.part_name, si.brand, si.stock_qty AS available_qty, si.reorder_level,
                    COALESCE((SELECT SUM(sb.quantity_remaining * sb.buying_price) / NULLIF(SUM(sb.quantity_remaining),0) FROM stock_batches sb WHERE sb.stock_item_id = si.id AND sb.quantity_remaining > 0), si.buying_price) AS average_cost,
                    si.selling_price,
                    (si.stock_qty * COALESCE((SELECT SUM(sb.quantity_remaining * sb.buying_price) / NULLIF(SUM(sb.quantity_remaining),0) FROM stock_batches sb WHERE sb.stock_item_id = si.id AND sb.quantity_remaining > 0), si.buying_price)) AS stock_value
                FROM stock_items si
                WHERE ' . implode(' AND ', $stockWhere);
        }
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY 1 DESC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['status_label'] = isset($row['available_qty']) ? (($row['available_qty'] <= 0) ? 'Out of Stock' : (((float) $row['available_qty'] <= (float) ($row['reorder_level'] ?? 0)) ? 'Low Stock' : 'In Stock')) : '';
            if (isset($row['movement_type'])) {
                $row['movement_type'] = ucwords(str_replace('_', ' ', (string) $row['movement_type']));
            }
            if (isset($row['payment_status'])) {
                $row['payment_status_label'] = ucfirst((string) $row['payment_status']);
            }
            if (isset($row['purchase_date'])) {
                $row['purchase_date'] = date('Y-m-d', strtotime((string) $row['purchase_date']));
            }
        }
        unset($row);
    } elseif ($section === 'reports-customers') {
        $baseWhere = ['1=1'];
        report_apply_date_filter('DATE(c.created_at)', $baseWhere, $params, $bounds);
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'c.id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['outstanding_status'] === 'with_outstanding') {
            $baseWhere[] = 'COALESCE(inv.outstanding, 0) > 0';
        } elseif ($filters['outstanding_status'] === 'clear') {
            $baseWhere[] = 'COALESCE(inv.outstanding, 0) <= 0';
        }
        $summaryParams = $params;
        unset($summaryParams['date_from'], $summaryParams['date_to']);
        $summary = report_sql_rows(
            'SELECT
                COUNT(*) AS total_customers,
                SUM(c.status = "active") AS active_customers,
                SUM(COALESCE(inv.outstanding, 0) > 0) AS customers_with_outstanding
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, SUM(balance_amount) AS outstanding
                FROM invoices
                WHERE payment_status <> "cancelled"
                GROUP BY customer_id
            ) inv ON inv.customer_id = c.id
            WHERE ' . implode(' AND ', array_filter($baseWhere, static fn($item) => !str_starts_with($item, 'DATE(c.created_at)'))),
            $summaryParams
        )[0] ?? [];
        $summary['new_customers'] = report_sql_value(
            'SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN :date_from AND :date_to',
            ['date_from' => $bounds['from'], 'date_to' => $bounds['to']]
        );
        $cards = [
            report_count_card('Total Customers', $summary['total_customers'] ?? 0, 'blue'),
            report_count_card('New Customers', $summary['new_customers'] ?? 0, 'green'),
            report_count_card('Active Customers', $summary['active_customers'] ?? 0, 'orange'),
            report_count_card('Customers With Outstanding', $summary['customers_with_outstanding'] ?? 0, 'red'),
        ];
        $columns = ['customer_name' => 'Customer', 'contact_number' => 'Phone', 'total_vehicles' => 'Total Vehicles', 'total_jobs' => 'Total Jobs', 'total_invoices' => 'Total Invoices', 'total_spending' => 'Total Spending', 'outstanding' => 'Outstanding'];
        $sql = 'SELECT
                c.id, c.name AS customer_name, c.contact_number,
                COALESCE(v.total_vehicles, 0) AS total_vehicles,
                COALESCE(j.total_jobs, 0) AS total_jobs,
                COALESCE(i.total_invoices, 0) AS total_invoices,
                COALESCE(i.total_spending, 0) AS total_spending,
                COALESCE(i.outstanding, 0) AS outstanding
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, COUNT(*) AS total_vehicles
                FROM vehicles
                GROUP BY customer_id
            ) v ON v.customer_id = c.id
            LEFT JOIN (
                SELECT customer_id, COUNT(*) AS total_jobs
                FROM job_cards
                GROUP BY customer_id
            ) j ON j.customer_id = c.id
            LEFT JOIN (
                SELECT customer_id, COUNT(*) AS total_invoices, SUM(total_amount) AS total_spending, SUM(balance_amount) AS outstanding
                FROM invoices
                WHERE payment_status <> "cancelled"
                GROUP BY customer_id
            ) i ON i.customer_id = c.id
            WHERE ' . implode(' AND ', $baseWhere) . '
            GROUP BY c.id';
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY c.name ASC', $params, $page, $limit);
    } elseif ($section === 'reports-vehicles') {
        $baseWhere = ['1=1'];
        report_apply_date_filter('DATE(v.created_at)', $baseWhere, $params, $bounds);
        if ($filters['vehicle_id'] > 0) {
            $baseWhere[] = 'v.id = :vehicle_id';
            $params['vehicle_id'] = $filters['vehicle_id'];
        }
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'v.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['make_model'] !== '') {
            $baseWhere[] = '(v.make LIKE :make_model OR v.model LIKE :make_model)';
            $params['make_model'] = '%' . $filters['make_model'] . '%';
        }
        $summaryParams = $params;
        unset($summaryParams['date_from'], $summaryParams['date_to']);
        $summary = report_sql_rows(
            'SELECT
                COUNT(*) AS total_vehicles,
                SUM(v.status = "active") AS active_vehicles,
                SUM(COALESCE(inv.outstanding, 0) > 0) AS vehicles_with_outstanding,
                SUM(COALESCE(jc.total_jobs, 0) > 0) AS vehicles_in_service
            FROM vehicles v
            LEFT JOIN (
                SELECT vehicle_id, COUNT(*) AS total_jobs
                FROM job_cards
                GROUP BY vehicle_id
            ) jc ON jc.vehicle_id = v.id
            LEFT JOIN (
                SELECT vehicle_id, SUM(balance_amount) AS outstanding
                FROM invoices
                WHERE payment_status <> "cancelled"
                GROUP BY vehicle_id
            ) inv ON inv.vehicle_id = v.id
            WHERE ' . implode(' AND ', array_filter($baseWhere, static fn($item) => !str_starts_with($item, 'DATE(v.created_at)'))),
            $summaryParams
        )[0] ?? [];
        $cards = [
            report_count_card('Total Vehicles', $summary['total_vehicles'] ?? 0, 'blue'),
            report_count_card('Active Vehicles', $summary['active_vehicles'] ?? 0, 'green'),
            report_count_card('Vehicles In Service', $summary['vehicles_in_service'] ?? 0, 'orange'),
            report_count_card('Current Outstanding', $summary['vehicles_with_outstanding'] ?? 0, 'red'),
        ];
        $columns = ['vehicle_number' => 'Vehicle Number', 'customer_name' => 'Customer', 'make_model' => 'Make / Model', 'total_job_cards' => 'Total Job Cards', 'last_service_date' => 'Last Service Date', 'total_charges' => 'Total Charges', 'current_outstanding' => 'Current Outstanding'];
        $sql = 'SELECT
                v.id, v.vehicle_number, c.name AS customer_name,
                TRIM(CONCAT_WS(" ", COALESCE(v.make, ""), COALESCE(v.model, ""))) AS make_model,
                COALESCE(j.total_job_cards, 0) AS total_job_cards,
                j.last_service_date AS last_service_date,
                COALESCE(i.total_charges, 0) AS total_charges,
                COALESCE(i.current_outstanding, 0) AS current_outstanding
            FROM vehicles v
            JOIN customers c ON c.id = v.customer_id
            LEFT JOIN (
                SELECT vehicle_id, COUNT(*) AS total_job_cards, MAX(CASE WHEN status = "completed" THEN COALESCE(completed_at, updated_at) END) AS last_service_date
                FROM job_cards
                GROUP BY vehicle_id
            ) j ON j.vehicle_id = v.id
            LEFT JOIN (
                SELECT vehicle_id, SUM(total_amount) AS total_charges, SUM(balance_amount) AS current_outstanding
                FROM invoices
                WHERE payment_status <> "cancelled"
                GROUP BY vehicle_id
            ) i ON i.vehicle_id = v.id
            WHERE ' . implode(' AND ', $baseWhere) . '
            GROUP BY v.id';
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY v.vehicle_number ASC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['last_service_date'] = $row['last_service_date'] ? date('Y-m-d', strtotime((string) $row['last_service_date'])) : '-';
        }
        unset($row);
    } elseif ($section === 'reports-mechanics') {
        $baseWhere = ['1=1'];
        report_apply_date_filter('DATE(j.created_at)', $baseWhere, $params, $bounds);
        if ($filters['mechanic_id'] > 0) {
            $baseWhere[] = 'e.id = :mechanic_id';
            $params['mechanic_id'] = $filters['mechanic_id'];
        }
        if ($filters['job_status'] !== '') {
            $baseWhere[] = 'j.status = :job_status';
            $params['job_status'] = $filters['job_status'];
        }
        $summaryParams = $params;
        unset($summaryParams['date_from'], $summaryParams['date_to']);
        $summary = report_sql_rows(
            'SELECT
                COUNT(DISTINCT e.id) AS total_mechanics,
                SUM(j.mechanic_id IS NOT NULL) AS assigned_jobs,
                SUM(j.status = "completed") AS completed_jobs,
                SUM(j.status = "ongoing") AS ongoing_jobs,
                COALESCE(AVG(CASE WHEN perf.end_time > perf.start_time THEN TIMESTAMPDIFF(SECOND, perf.start_time, perf.end_time) / 60 ELSE NULL END), 0) AS average_completion_time
            FROM employees e
            LEFT JOIN job_cards j ON j.mechanic_id = e.id
            LEFT JOIN job_card_performance perf ON perf.mechanic_id = e.id AND perf.job_card_id = j.id
            WHERE e.status = "active" AND ' . implode(' AND ', array_filter($baseWhere, static fn($item) => !str_starts_with($item, 'DATE(j.created_at)'))),
            $summaryParams
        )[0] ?? [];
        $cards = [
            report_count_card('Total Mechanics', $summary['total_mechanics'] ?? 0, 'blue'),
            report_count_card('Assigned Jobs', $summary['assigned_jobs'] ?? 0, 'green'),
            report_count_card('Completed Jobs', $summary['completed_jobs'] ?? 0, 'orange'),
            report_count_card('Ongoing Jobs', $summary['ongoing_jobs'] ?? 0, 'red'),
            ['label' => 'Average Completion Time', 'value' => report_duration_display($summary['average_completion_time'] ?? 0), 'tone' => 'purple', 'currency' => false],
        ];
        $columns = ['employee_code' => 'Code', 'name' => 'Mechanic', 'role_position' => 'Role', 'assigned_jobs' => 'Assigned Jobs', 'completed_jobs' => 'Completed Jobs', 'ongoing_jobs' => 'Ongoing Jobs', 'average_completion_time' => 'Average Completion Time', 'service_job_value' => 'Service / Job Value'];
        $sql = 'SELECT
                e.id, e.employee_code, e.name, e.role_position,
                COUNT(DISTINCT j.id) AS assigned_jobs,
                SUM(j.status = "completed") AS completed_jobs,
                SUM(j.status = "ongoing") AS ongoing_jobs,
                COALESCE(AVG(CASE WHEN perf.end_time > perf.start_time THEN TIMESTAMPDIFF(SECOND, perf.start_time, perf.end_time) / 60 ELSE NULL END), 0) AS average_completion_time,
                COALESCE(SUM(j.total_amount), 0) AS service_job_value
            FROM employees e
            LEFT JOIN job_cards j ON j.mechanic_id = e.id
            LEFT JOIN job_card_performance perf ON perf.mechanic_id = e.id AND perf.job_card_id = j.id
            WHERE e.status = "active" AND ' . implode(' AND ', $baseWhere) . '
            GROUP BY e.id';
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY e.name ASC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['average_completion_time'] = report_duration_display($row['average_completion_time'] ?? 0);
        }
        unset($row);
    } elseif ($section === 'reports-bays') {
        $baseWhere = ['1=1'];
        if ($filters['bay_id'] > 0) {
            $baseWhere[] = 'b.id = :bay_id';
            $params['bay_id'] = $filters['bay_id'];
        }
        if ($filters['status'] !== '') {
            $baseWhere[] = 'b.status = :status';
            $params['status'] = $filters['status'];
        }
        $summary = report_sql_rows(
            'SELECT
                COUNT(*) AS total_bays,
                SUM(b.status = "active") AS available_bays,
                SUM(b.status = "maintenance") AS maintenance_bays,
                SUM(b.status = "active" AND EXISTS (SELECT 1 FROM job_cards j WHERE j.bay_name = b.bay_name AND j.status = "ongoing")) AS occupied_bays
            FROM bays b
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $cards = [
            report_count_card('Total Bays', $summary['total_bays'] ?? 0, 'blue'),
            report_count_card('Available', $summary['available_bays'] ?? 0, 'green'),
            report_count_card('Occupied', $summary['occupied_bays'] ?? 0, 'orange'),
            report_count_card('Maintenance', $summary['maintenance_bays'] ?? 0, 'red'),
        ];
        $columns = ['bay_name' => 'Bay', 'total_jobs' => 'Total Jobs', 'completed_jobs' => 'Completed Jobs', 'ongoing_jobs' => 'Ongoing Jobs', 'average_job_time' => 'Average Job Time', 'status_label' => 'Current Status'];
        $sql = 'SELECT
                b.id, b.bay_name, b.status,
                COUNT(DISTINCT j.id) AS total_jobs,
                SUM(j.status = "completed") AS completed_jobs,
                SUM(j.status = "ongoing") AS ongoing_jobs,
                COALESCE(AVG(CASE WHEN perf.end_time > perf.start_time THEN TIMESTAMPDIFF(SECOND, perf.start_time, perf.end_time) / 60 ELSE NULL END), 0) AS average_job_time
            FROM bays b
            LEFT JOIN job_cards j ON j.bay_name = b.bay_name
            LEFT JOIN job_card_performance perf ON perf.job_card_id = j.id
            WHERE ' . implode(' AND ', $baseWhere) . '
            GROUP BY b.id';
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY b.bay_name ASC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['status_label'] = ucfirst((string) $row['status']);
            $row['average_job_time'] = report_duration_display($row['average_job_time'] ?? 0);
        }
        unset($row);
    } elseif ($section === 'reports-payments') {
        $baseWhere = ['1=1'];
        report_apply_date_filter('DATE(p.payment_date)', $baseWhere, $params, $bounds);
        if ($filters['payment_method'] !== '') {
            $baseWhere[] = 'p.payment_method = :payment_method';
            $params['payment_method'] = $filters['payment_method'];
        }
        if ($filters['customer_id'] > 0) {
            $baseWhere[] = 'p.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }
        if ($filters['invoice_id'] > 0) {
            $baseWhere[] = 'p.invoice_id = :invoice_id';
            $params['invoice_id'] = $filters['invoice_id'];
        }
        if ($filters['received_by'] > 0) {
            $baseWhere[] = 'p.created_by = :received_by';
            $params['received_by'] = $filters['received_by'];
        }
        $summary = report_sql_rows(
            'SELECT
                COALESCE(SUM(p.amount_applied),0) AS total_collected,
                COALESCE(SUM(CASE WHEN p.payment_method = "cash" THEN p.amount_applied ELSE 0 END),0) AS cash_collection,
                COALESCE(SUM(CASE WHEN p.payment_method = "card" THEN p.amount_applied ELSE 0 END),0) AS card_collection,
                COALESCE(SUM(CASE WHEN p.payment_method = "bank" THEN p.amount_applied ELSE 0 END),0) AS bank_collection,
                COALESCE(SUM(CASE WHEN p.payment_method = "cheque" THEN p.amount_applied ELSE 0 END),0) AS cheque_collection,
                COALESCE(SUM(i.balance_amount),0) AS outstanding_amount
            FROM invoice_payments p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE ' . implode(' AND ', $baseWhere),
            $params
        )[0] ?? [];
        $cards = [
            report_summary_card('Total Collected', $summary['total_collected'] ?? 0, 'blue'),
            report_summary_card('Cash Collection', $summary['cash_collection'] ?? 0, 'green'),
            report_summary_card('Card Collection', $summary['card_collection'] ?? 0, 'orange'),
            report_summary_card('Bank Transfer Collection', $summary['bank_collection'] ?? 0, 'purple'),
            report_summary_card('Cheque Collection', $summary['cheque_collection'] ?? 0, 'navy'),
            report_summary_card('Outstanding Amount', $summary['outstanding_amount'] ?? 0, 'red'),
        ];
        $columns = ['receipt_no' => 'Receipt No', 'payment_date' => 'Date / Time', 'invoice_no' => 'Invoice No', 'customer_name' => 'Customer', 'payment_method' => 'Payment Method', 'amount_received' => 'Amount Received', 'amount_applied' => 'Amount Applied', 'change_given' => 'Change Given', 'remaining_balance' => 'Remaining Balance', 'received_by' => 'Received By'];
        $sql = 'SELECT
                p.id, p.payment_no AS receipt_no, p.payment_date, i.invoice_no, COALESCE(c.name, "Walk-in Customer") AS customer_name, p.payment_method,
                p.amount_received, p.amount_applied, p.change_given, i.balance_amount AS remaining_balance,
                COALESCE(u.name, "-") AS received_by
            FROM invoice_payments p
            JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN customers c ON c.id = p.customer_id
            LEFT JOIN users u ON u.id = p.created_by
            WHERE ' . implode(' AND ', $baseWhere);
        $totalRows = report_count_rows($sql, $params);
        $rows = report_page_rows($sql . ' ORDER BY p.payment_date DESC, p.id DESC', $params, $page, $limit);
        foreach ($rows as &$row) {
            $row['payment_date'] = date('Y-m-d H:i', strtotime((string) $row['payment_date']));
            $row['payment_method'] = ucfirst((string) $row['payment_method']);
        }
        unset($row);
    } elseif ($section === 'reports-profit-loss' || $section === 'reports-today-profit') {
        $profitBounds = $section === 'reports-today-profit'
            ? ['from' => date('Y-m-d'), 'to' => date('Y-m-d')]
            : $bounds;
        $dateFrom = $profitBounds['from'];
        $dateTo = $profitBounds['to'];
        $sales = report_sql_value(
            'SELECT COALESCE(SUM(total_amount),0)
             FROM invoices
             WHERE payment_status <> "cancelled" AND invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $jobSales = report_sql_value(
            'SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE payment_status <> "cancelled" AND invoice_type = "job_card" AND invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $quickSales = report_sql_value(
            'SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE payment_status <> "cancelled" AND invoice_type = "quick" AND invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $partsRevenue = report_sql_value(
            'SELECT COALESCE(SUM(line_total),0)
             FROM invoice_items ii
             JOIN invoices i ON i.id = ii.invoice_id
             WHERE i.payment_status <> "cancelled" AND ii.item_type IN ("stock_part","external_part") AND i.invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $serviceRevenue = report_sql_value(
            'SELECT COALESCE(SUM(line_total),0)
             FROM invoice_items ii
             JOIN invoices i ON i.id = ii.invoice_id
             WHERE i.payment_status <> "cancelled" AND ii.item_type = "service" AND i.invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        ) + report_sql_value(
            'SELECT COALESCE(SUM(special_service_charge),0) FROM invoices WHERE payment_status <> "cancelled" AND invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $otherIncome = report_sql_value(
            'SELECT COALESCE(SUM(amount),0) FROM other_income WHERE income_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $fifoCost = report_sql_value(
            'SELECT COALESCE(SUM(ii.cost_amount),0)
             FROM invoice_items ii
             JOIN invoices i ON i.id = ii.invoice_id
             WHERE ii.item_type IN ("stock_part","external_part")
               AND i.payment_status <> "cancelled"
               AND i.invoice_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $operatingExpenses = report_sql_value(
            'SELECT COALESCE(SUM(total_amount),0)
             FROM expenses
             WHERE expense_type = "general" AND status <> "cancelled" AND expense_date BETWEEN :from_date AND :to_date',
            ['from_date' => $dateFrom, 'to_date' => $dateTo]
        );
        $totalIncome = $sales + $otherIncome;
        $totalExpenses = $operatingExpenses;
        $grossProfit = $sales - $fifoCost;
        $netProfit = $grossProfit + $otherIncome - $operatingExpenses;
        $cards = [
            report_summary_card('Sales Revenue', $sales, 'blue'),
            report_summary_card('Cost of Parts', $fifoCost, 'red'),
            report_summary_card('Gross Profit', $grossProfit, 'green'),
            report_summary_card('Other Income', $otherIncome, 'orange'),
            report_summary_card('Total Expenses', $totalExpenses, 'purple'),
            report_summary_card('Net Profit', $netProfit, 'navy'),
        ];
        $chart = report_svg_bars([
            ['label' => 'Sales Revenue', 'value' => $sales, 'color' => '#155eef'],
            ['label' => 'Cost of Parts', 'value' => $fifoCost, 'color' => '#f04438'],
            ['label' => 'Gross Profit', 'value' => $grossProfit, 'color' => '#12b76a'],
        ]);
        $columns = [
            'label' => 'Category',
            'amount' => 'Amount'
        ];
        $rows = [
            ['label' => 'Job Card Sales', 'amount' => report_money($jobSales)],
            ['label' => 'Quick Invoice Sales', 'amount' => report_money($quickSales)],
            ['label' => 'Spare Parts Revenue', 'amount' => report_money($partsRevenue)],
            ['label' => 'Service / Special Service Revenue', 'amount' => report_money($serviceRevenue)],
            ['label' => 'Other Income', 'amount' => report_money($otherIncome)],
            ['label' => 'Parts Cost', 'amount' => report_money($fifoCost)],
            ['label' => 'General Expenses', 'amount' => report_money($operatingExpenses)],
            ['label' => 'Total Income', 'amount' => report_money($totalIncome)],
            ['label' => 'Total Expenses', 'amount' => report_money($totalExpenses)],
            ['label' => 'Gross Profit', 'amount' => report_money($grossProfit)],
            ['label' => 'Net Profit', 'amount' => report_money($netProfit)],
        ];
        $totalRows = count($rows);
        $limit = 100;
        $extra['breakdown'] = [
            ['section' => 'Sales', 'items' => [
                ['label' => 'Job Card Sales', 'value' => $jobSales],
                ['label' => 'Quick Invoice Sales', 'value' => $quickSales],
                ['label' => 'Spare Parts Revenue', 'value' => $partsRevenue],
                ['label' => 'Service / Special Service Revenue', 'value' => $serviceRevenue],
            ]],
            ['section' => 'Other Income', 'items' => [['label' => 'Other Income', 'value' => $otherIncome]]],
            ['section' => 'Cost', 'items' => [['label' => 'Parts Cost', 'value' => $fifoCost]]],
            ['section' => 'Expenses', 'items' => [
                ['label' => 'General Expenses', 'value' => $operatingExpenses],
                ['label' => 'Total Expenses', 'value' => $totalExpenses],
            ]],
            ['section' => 'Profit', 'items' => [
                ['label' => 'Gross Profit', 'value' => $grossProfit],
                ['label' => 'Net Profit', 'value' => $netProfit],
            ]],
        ];
        if ($section === 'reports-today-profit') {
            $extra['mini_tables'] = [
                [
                    'title' => "Today's Invoices",
                    'columns' => ['invoice_no' => 'Invoice No', 'invoice_date' => 'Date', 'customer_name' => 'Customer', 'total_amount' => 'Total', 'payment_status_label' => 'Status'],
                    'rows' => report_sql_rows(
                        'SELECT i.invoice_no, i.invoice_date, COALESCE(c.name, "Walk-in Customer") AS customer_name, i.total_amount, i.payment_status AS payment_status_label
                         FROM invoices i
                         LEFT JOIN customers c ON c.id = i.customer_id
                         WHERE i.payment_status <> "cancelled" AND i.invoice_date = :today
                         ORDER BY i.id DESC
                         LIMIT 5',
                        ['today' => date('Y-m-d')]
                    ),
                ],
                [
                    'title' => "Today's Expenses",
                    'columns' => ['expense_no' => 'Expense No', 'expense_date' => 'Date', 'category' => 'Category', 'total_amount' => 'Amount', 'status_label' => 'Status'],
                    'rows' => report_sql_rows(
                        'SELECT expense_no, expense_date, category, total_amount, status AS status_label
                         FROM expenses
                         WHERE expense_date = :today
                         ORDER BY id DESC
                         LIMIT 5',
                        ['today' => date('Y-m-d')]
                    ),
                ],
                [
                    'title' => "Today's Other Income",
                    'columns' => ['title' => 'Title', 'income_date' => 'Date', 'payment_method' => 'Method', 'amount' => 'Amount'],
                    'rows' => report_sql_rows(
                        'SELECT title, income_date, payment_method, amount
                         FROM other_income
                         WHERE income_date = :today
                         ORDER BY id DESC
                         LIMIT 5',
                        ['today' => date('Y-m-d')]
                    ),
                ],
            ];
        }
    }

    return [
        'definition' => $defs[$section],
        'filters' => $filters,
        'bounds' => $bounds,
        'cards' => $cards,
        'chart' => $chart,
        'columns' => $columns,
        'rows' => $rows,
        'total_rows' => $totalRows,
        'extra' => $extra,
        'lookups' => $lookups,
        'section' => $section,
        'export_columns' => array_values($columns),
    ];
}

function handle_report_request(string $section): void
{
    $defs = report_definitions();
    if (!isset($defs[$section])) {
        http_response_code(404);
        exit('Report not found.');
    }

    $filters = report_filters_from_request($section);
    $isExport = ($_GET['export'] ?? '') === 'excel';
    $isPrint = in_array(($_GET['mode'] ?? ''), ['print', 'pdf'], true);
    $report = report_build($section, $filters, $isExport || $isPrint);
    if ($isExport) {
        report_export_tsv(str_replace(' ', '-', strtolower($defs[$section]['title'])) . '.xls', $report['columns'], $report['rows']);
    }

    $title = $defs[$section]['title'];
    $sectionForHeader = $section;
    if ($isPrint) {
        require __DIR__ . '/../views/reports-print.php';
        return;
    }

    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/reports.php';
    require __DIR__ . '/../includes/footer.php';
}
