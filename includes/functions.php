<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token. Please refresh and try again.');
    }
}

function old(string $key): string
{
    return e($_SESSION['old'][$key] ?? '');
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function receipt_settings(): array
{
    static $settings;
    if (is_array($settings)) return $settings;

    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_settings (id TINYINT UNSIGNED NOT NULL, garage_name VARCHAR(160) NOT NULL, tagline VARCHAR(190) NULL, contact_number VARCHAR(40) NULL, secondary_contact_number VARCHAR(40) NULL, email VARCHAR(190) NULL, address TEXT NULL, logo_path VARCHAR(255) NULL, receipt_header TEXT NULL, receipt_footer TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = $pdo->query('SHOW COLUMNS FROM receipt_settings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('secondary_contact_number', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN secondary_contact_number VARCHAR(40) NULL AFTER contact_number');
    if (!in_array('logo_path', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN logo_path VARCHAR(255) NULL AFTER address');
    $settings = $pdo->query('SELECT * FROM receipt_settings WHERE id=1 LIMIT 1')->fetch() ?: [
        'garage_name' => 'Deshappriya Motors', 'tagline' => 'Garage Management System',
        'contact_number' => '077 345 6789', 'secondary_contact_number' => '', 'email' => 'info@deshappriyamotors.lk', 'address' => 'No. 123, Main Street, Kurunegala, Sri Lanka', 'logo_path' => '',
        'receipt_header' => '', 'receipt_footer' => 'Thank you for your business.',
    ];
    if (!isset($settings['id'])) {
        $insert = $pdo->prepare('INSERT INTO receipt_settings (id,garage_name,tagline,contact_number,secondary_contact_number,email,address,logo_path,receipt_header,receipt_footer) VALUES (1,:garage_name,:tagline,:contact_number,:secondary_contact_number,:email,:address,:logo_path,:receipt_header,:receipt_footer)');
        $insert->execute($settings);
        $settings['id'] = 1;
    }
    return $settings;
}

function powered_by_text(): string
{
    return 'Powered By Axiom Rec';
}

function receipt_logo_path(array $settings): string
{
    $path = (string) ($settings['logo_path'] ?? '');
    return preg_match('#^assets/uploads/[A-Za-z0-9._/-]+$#', $path) && is_file(__DIR__ . '/../' . $path) ? $path : '';
}

function admin_dashboard_data(string $period = 'today'): array
{
    $period = in_array($period, ['today', 'week', 'month'], true) ? $period : 'today';
    $today = new DateTimeImmutable('today');
    $rangeStart = $period === 'today' ? $today : ($period === 'week' ? $today->modify('-6 days') : $today->modify('first day of this month'));
    $rangeEnd = $period === 'month' ? $today->modify('last day of this month') : $today;
    $monthStart = $today->modify('first day of this month');
    $nextMonth = $monthStart->modify('+1 month');
    $pdo = database();
    $value = static function (string $sql, array $params = []) use ($pdo): float {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return (float) ($statement->fetchColumn() ?: 0);
    };
    $rows = static function (string $sql, array $params = []) use ($pdo): array {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    };
    $empty = [
        'sales' => 0.0, 'payments' => 0.0, 'pending' => 0.0, 'ongoing' => 0, 'completed' => 0,
        'low_stock' => 0, 'sales_chart' => [], 'income' => ['parts' => 0.0, 'services' => 0.0, 'charges' => 0.0, 'other' => 0.0],
        'job_status' => ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0],
        'recent_jobs' => [], 'recent_invoices' => [], 'low_items' => [], 'appointments' => [], 'expenses' => [],
        'other_income' => [], 'net_profit' => 0.0, 'parts_cost' => 0.0, 'total_expenses' => 0.0,
    ];
    try {
        $dateParams = ['start' => $rangeStart->format('Y-m-d'), 'end' => $rangeEnd->format('Y-m-d'), 'today' => $today->format('Y-m-d')];
        $empty['sales'] = $value("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE invoice_date=:today AND payment_status <> 'cancelled'", ['today' => $dateParams['today']]);
        $empty['payments'] = $value("SELECT COALESCE(SUM(p.amount_applied),0) FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id WHERE DATE(p.payment_date)=:today AND i.payment_status <> 'cancelled'", ['today' => $dateParams['today']]);
        $empty['pending'] = $value("SELECT COALESCE(SUM(GREATEST(balance_amount,0)),0) FROM invoices WHERE payment_status IN ('due','partial')");
        $empty['ongoing'] = (int) $value("SELECT COUNT(*) FROM job_cards WHERE status='ongoing'");
        $empty['completed'] = (int) $value("SELECT COUNT(*) FROM job_cards WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) BETWEEN :start AND :end", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['low_stock'] = (int) $value("SELECT COUNT(*) FROM stock_items WHERE status='active' AND stock_qty <= reorder_level");
        $chartStart = $today->modify('-6 days')->format('Y-m-d');
        $chartRows = $rows("SELECT invoice_date, invoice_type, COALESCE(SUM(total_amount),0) amount FROM invoices WHERE invoice_date BETWEEN :start AND :end AND payment_status <> 'cancelled' GROUP BY invoice_date, invoice_type ORDER BY invoice_date", ['start' => $chartStart, 'end' => $today->format('Y-m-d')]);
        $chart = [];
        for ($i = 6; $i >= 0; $i--) { $date = $today->modify('-' . $i . ' days')->format('Y-m-d'); $chart[$date] = ['date' => $date, 'job_card' => 0.0, 'quick' => 0.0]; }
        foreach ($chartRows as $row) { if (isset($chart[$row['invoice_date']])) $chart[$row['invoice_date']][($row['invoice_type'] ?? '') === 'job_card' ? 'job_card' : 'quick'] = (float) $row['amount']; }
        $empty['sales_chart'] = array_values($chart);
        $incomeRows = $rows("SELECT ii.item_type, COALESCE(SUM(ii.line_total),0) amount FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE i.invoice_date >= :start AND i.invoice_date < :end AND i.payment_status <> 'cancelled' GROUP BY ii.item_type", ['start' => $monthStart->format('Y-m-d'), 'end' => $nextMonth->format('Y-m-d')]);
        foreach ($incomeRows as $row) { if (in_array($row['item_type'], ['stock_part', 'external_part'], true)) $empty['income']['parts'] += (float) $row['amount']; elseif ($row['item_type'] === 'service') $empty['income']['services'] += (float) $row['amount']; }
        $empty['income']['charges'] = $value("SELECT COALESCE(SUM(special_service_charge),0) FROM invoices WHERE invoice_date >= :start AND invoice_date < :end AND payment_status <> 'cancelled'", ['start' => $monthStart->format('Y-m-d'), 'end' => $nextMonth->format('Y-m-d')]);
        $empty['income']['other'] = $value("SELECT COALESCE(SUM(amount),0) FROM other_income WHERE income_date >= :start AND income_date < :end", ['start' => $monthStart->format('Y-m-d'), 'end' => $nextMonth->format('Y-m-d')]);
        foreach ($rows("SELECT status, COUNT(*) total FROM job_cards WHERE DATE(created_at) BETWEEN :start AND :end GROUP BY status", ['start' => $dateParams['start'], 'end' => $dateParams['end']]) as $row) if (isset($empty['job_status'][$row['status']])) $empty['job_status'][$row['status']] = (int) $row['total'];
        $empty['recent_jobs'] = $rows("SELECT jc.id, jc.job_card_no, jc.status, DATE(jc.created_at) created_date, c.name customer_name, v.vehicle_number FROM job_cards jc JOIN customers c ON c.id=jc.customer_id LEFT JOIN vehicles v ON v.id=jc.vehicle_id ORDER BY jc.created_at DESC, jc.id DESC LIMIT 5");
        $empty['recent_invoices'] = $rows("SELECT i.id, i.invoice_no, i.total_amount, i.payment_status, COALESCE(c.name,'Walk-in Customer') customer_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id WHERE i.payment_status <> 'cancelled' ORDER BY i.created_at DESC, i.id DESC LIMIT 5");
        $empty['low_items'] = $rows("SELECT part_code, part_name, stock_qty, reorder_level FROM stock_items WHERE status='active' AND stock_qty <= reorder_level ORDER BY (stock_qty <= 0) DESC, (reorder_level-stock_qty) DESC, part_name LIMIT 5");
        $todayParams = ['today' => $dateParams['today']];
        $empty['appointments'] = $rows("SELECT a.appointment_time, a.status, c.name customer_name, v.vehicle_number FROM appointments a JOIN customers c ON c.id=a.customer_id LEFT JOIN vehicles v ON v.id=a.vehicle_id WHERE a.appointment_date=:today ORDER BY a.appointment_time ASC LIMIT 5", $todayParams);
        $empty['expenses'] = $rows("SELECT expense_no, description, category, total_amount FROM expenses WHERE expense_date=:today AND status <> 'cancelled' ORDER BY id DESC LIMIT 5", $todayParams);
        $empty['other_income'] = $rows("SELECT title, amount FROM other_income WHERE income_date=:today ORDER BY id DESC LIMIT 5", $todayParams);
        $empty['total_expenses'] = $value("SELECT COALESCE(SUM(total_amount),0) FROM expenses WHERE expense_date=:today AND status <> 'cancelled'", $todayParams);
        $empty['parts_cost'] = $value("SELECT COALESCE(SUM(total_cost),0) FROM stock_batch_consumptions WHERE DATE(created_at)=:today AND reference_type IN ('sale','job_card_item')", $todayParams);
        $empty['parts_cost'] += $value("SELECT COALESCE(SUM(unit_cost * quantity),0) FROM expense_external_parts WHERE DATE(created_at)=:today", $todayParams);
        $empty['net_profit'] = $empty['sales'] - $empty['parts_cost'] + $empty['income']['other'] - $empty['total_expenses'];
    } catch (Throwable $exception) {
        // Keep the administrator dashboard usable while an optional module table is being initialized.
    }
    return $empty + ['period' => $period, 'range_start' => $rangeStart->format('Y-m-d'), 'range_end' => $rangeEnd->format('Y-m-d')];
}
