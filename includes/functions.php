<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/income-accounting.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function payment_method_label(?string $method): string
{
    return ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Online Transfer',
        'bank_transfer' => 'Online Transfer', 'cheque' => 'Cheque', 'other' => 'Other'][$method ?? ''] ?? '-';
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
    return 'Powered By Axiom Tech 0721284460';
}

function receipt_logo_path(array $settings): string
{
    $path = (string) ($settings['logo_path'] ?? '');
    return preg_match('#^assets/uploads/[A-Za-z0-9._/-]+$#', $path) && is_file(__DIR__ . '/../' . $path) ? $path : '';
}

function dashboard_payment_totals(string $start, string $end): array
{
    $totals = ['cash' => 0.0, 'card' => 0.0, 'bank' => 0.0, 'cheque' => 0.0, 'other' => 0.0];
    $statement = database()->prepare("SELECT p.payment_method, SUM(p.amount_applied) AS amount
        FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id
        WHERE p.payment_date >= :start AND p.payment_date < DATE_ADD(:end, INTERVAL 1 DAY)
        AND i.payment_status <> 'cancelled' GROUP BY p.payment_method");
    $statement->execute(['start' => $start, 'end' => $end]);
    foreach ($statement->fetchAll() as $payment) $totals[$payment['payment_method']] = (float)$payment['amount'];
    return $totals;
}

function admin_dashboard_data(string $period = 'today'): array
{
    $period = in_array($period, ['today', 'week', 'month'], true) ? $period : 'today';
    $today = new DateTimeImmutable('today');
    $rangeStart = $period === 'today' ? $today : ($period === 'week' ? $today->modify('monday this week') : $today->modify('first day of this month'));
    $rangeEnd = $today;
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
        'sales' => 0.0, 'payments' => 0.0, 'payment_methods' => ['cash' => 0.0, 'card' => 0.0, 'bank' => 0.0], 'pending' => 0.0, 'ongoing' => 0, 'completed' => 0,
        'low_stock' => 0, 'discount_total' => 0.0, 'discount_count' => 0, 'sales_chart' => [], 'income' => ['parts' => 0.0, 'services' => 0.0, 'charges' => 0.0, 'other' => 0.0],
        'job_status' => ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0],
        'recent_jobs' => [], 'recent_invoices' => [], 'low_items' => [], 'appointments' => [], 'expenses' => [],
        'other_income' => [], 'net_profit' => 0.0, 'gross_profit' => 0.0, 'parts_profit' => 0.0, 'service_charge_income' => 0.0, 'cashier_settled' => 0.0, 'cashier_remaining' => 0.0, 'parts_cost' => 0.0, 'total_expenses' => 0.0,
    ];
    try {
        if (!function_exists('ensure_stock_tables')) require_once __DIR__ . '/suppliers.php';
        if (!function_exists('ensure_stock_tables')) require_once __DIR__ . '/stock.php';
        if (!function_exists('ensure_job_card_tables')) require_once __DIR__ . '/job-cards.php';
        if (!function_exists('invoice_ensure_tables')) require_once __DIR__ . '/invoices.php';
        if (!function_exists('ensure_other_income_table')) require_once __DIR__ . '/other-income.php';
        if (!function_exists('ensure_expense_tables')) {
            require_once __DIR__ . '/suppliers.php';
            require_once __DIR__ . '/expenses.php';
        }
        invoice_ensure_tables();
        ensure_other_income_table();
        ensure_expense_tables();
        if (!function_exists('ensure_cashier_register_table')) require_once __DIR__ . '/cashier-register.php';
        ensure_cashier_register_table();
        $dateParams = ['start' => $rangeStart->format('Y-m-d'), 'end' => $rangeEnd->format('Y-m-d'), 'today' => $today->format('Y-m-d')];
        $pdo->exec("UPDATE invoices i JOIN job_cards j ON j.id=i.job_card_id SET i.invoice_date=DATE(COALESCE(j.completed_at,j.created_at)) WHERE i.job_card_id IS NOT NULL AND j.status='completed'");
        $empty['sales'] = $value("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE invoice_date BETWEEN :start AND :end AND payment_status <> 'cancelled'", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['payment_methods'] = dashboard_payment_totals($dateParams['start'], $dateParams['end']);
        $empty['payments'] = array_sum($empty['payment_methods']);
        $empty['pending'] = $value("SELECT COALESCE(SUM(GREATEST(balance_amount,0)),0) FROM invoices WHERE invoice_date BETWEEN :start AND :end AND payment_status IN ('due','partial')", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['cashier_settled'] = $value("SELECT COALESCE(SUM(accepted_amount),0) FROM cashier_registers WHERE register_date BETWEEN :start AND :end AND handover_status='accepted'", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['cashier_remaining'] = $value("SELECT COALESCE(SUM(GREATEST(expected_closing_amount - COALESCE(accepted_amount,0),0)),0) FROM cashier_registers WHERE register_date BETWEEN :start AND :end AND handover_status IN ('pending','accepted','rejected')", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['ongoing'] = (int) $value("SELECT COUNT(*) FROM job_cards WHERE status='ongoing' AND DATE(created_at) BETWEEN :start AND :end", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['completed'] = (int) $value("SELECT COUNT(*) FROM job_cards WHERE status='completed' AND DATE(COALESCE(completed_at,created_at)) BETWEEN :start AND :end", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $discounts = $rows("SELECT COALESCE(SUM(discount),0) discount_total, COUNT(*) discount_count FROM job_cards WHERE status='completed' AND discount>0 AND DATE(COALESCE(completed_at,created_at)) BETWEEN :start AND :end", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['discount_total'] = (float)($discounts[0]['discount_total'] ?? 0);
        $empty['discount_count'] = (int)($discounts[0]['discount_count'] ?? 0);
        $empty['low_stock'] = (int) $value("SELECT COUNT(*) FROM stock_items WHERE status='active' AND stock_qty <= reorder_level");
        $chartStart = $rangeStart->format('Y-m-d');
        $chartRows = $rows("SELECT invoice_date, invoice_type, COALESCE(SUM(total_amount),0) amount FROM invoices WHERE invoice_date BETWEEN :start AND :end AND payment_status <> 'cancelled' GROUP BY invoice_date, invoice_type ORDER BY invoice_date", ['start' => $chartStart, 'end' => $rangeEnd->format('Y-m-d')]);
        $chart = [];
        $chartDate = $rangeStart;
        while ($chartDate <= $rangeEnd) { $date = $chartDate->format('Y-m-d'); $chart[$date] = ['date' => $date, 'job_card' => 0.0, 'quick' => 0.0]; $chartDate = $chartDate->modify('+1 day'); }
        foreach ($chartRows as $row) { if (isset($chart[$row['invoice_date']])) $chart[$row['invoice_date']][($row['invoice_type'] ?? '') === 'job_card' ? 'job_card' : 'quick'] = (float) $row['amount']; }
        $empty['sales_chart'] = array_values($chart);
        $incomeRows = $rows("SELECT ii.item_type, COALESCE(SUM(ii.line_total),0) amount FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE i.invoice_date BETWEEN :start AND :end AND i.payment_status <> 'cancelled' GROUP BY ii.item_type", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        foreach ($incomeRows as $row) { if (in_array($row['item_type'], ['stock_part', 'external_part'], true)) $empty['income']['parts'] += (float) $row['amount']; elseif ($row['item_type'] === 'service') $empty['income']['services'] += (float) $row['amount']; }
        $empty['income']['charges'] = $value("SELECT COALESCE(SUM(special_service_charge),0) FROM invoices WHERE invoice_date BETWEEN :start AND :end AND payment_status <> 'cancelled'", ['start' => $dateParams['start'], 'end' => $dateParams['end']]);
        $empty['service_charge_income'] = $empty['income']['charges'];
        $empty['income']['other'] = other_income_revenue_total($dateParams['start'], $dateParams['end']);
        $statusRows = $rows("SELECT SUM(status='pending' AND DATE(created_at) BETWEEN :start AND :end) pending, SUM(status='ongoing' AND DATE(created_at) BETWEEN :start2 AND :end2) ongoing, SUM(status='completed' AND DATE(COALESCE(completed_at,created_at)) BETWEEN :start3 AND :end3) completed, SUM(status='cancelled' AND DATE(COALESCE(updated_at,created_at)) BETWEEN :start4 AND :end4) cancelled FROM job_cards", ['start' => $dateParams['start'], 'end' => $dateParams['end'], 'start2' => $dateParams['start'], 'end2' => $dateParams['end'], 'start3' => $dateParams['start'], 'end3' => $dateParams['end'], 'start4' => $dateParams['start'], 'end4' => $dateParams['end']]);
        if ($statusRows) foreach (array_keys($empty['job_status']) as $status) $empty['job_status'][$status] = (int) ($statusRows[0][$status] ?? 0);
        $empty['recent_jobs'] = $rows("SELECT jc.id, jc.job_card_no, jc.status, DATE(jc.created_at) created_date, c.name customer_name, v.vehicle_number FROM job_cards jc JOIN customers c ON c.id=jc.customer_id LEFT JOIN vehicles v ON v.id=jc.vehicle_id ORDER BY jc.created_at DESC, jc.id DESC LIMIT 5");
        $empty['recent_invoices'] = $rows("SELECT i.id, i.invoice_no, i.total_amount, i.payment_status, COALESCE(c.name,'Walk-in Customer') customer_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id WHERE i.payment_status <> 'cancelled' ORDER BY i.created_at DESC, i.id DESC LIMIT 5");
        $empty['low_items'] = $rows("SELECT part_code, part_name, stock_qty, reorder_level FROM stock_items WHERE status='active' AND stock_qty <= reorder_level ORDER BY (stock_qty <= 0) DESC, (reorder_level-stock_qty) DESC, part_name LIMIT 5");
        $rangeParams = ['start' => $dateParams['start'], 'end' => $dateParams['end']];
        $empty['appointments'] = $rows("SELECT a.appointment_time, a.status, c.name customer_name, v.vehicle_number FROM appointments a JOIN customers c ON c.id=a.customer_id LEFT JOIN vehicles v ON v.id=a.vehicle_id WHERE a.appointment_date BETWEEN :start AND :end ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5", $rangeParams);
        $empty['expenses'] = $rows("SELECT expense_no, description, category, total_amount FROM expenses WHERE expense_date BETWEEN :start AND :end AND status <> 'cancelled' ORDER BY expense_date DESC, id DESC LIMIT 5", $rangeParams);
        $empty['other_income'] = other_income_revenue_recent($dateParams['start'], $dateParams['end']);
        $empty['total_expenses'] = $value("SELECT COALESCE(SUM(total_amount),0) FROM expenses WHERE expense_type='general' AND expense_date BETWEEN :start AND :end AND status <> 'cancelled'", $rangeParams);
        // Invoice cost_amount stores the unit cost, including for existing invoices.
        $empty['parts_cost'] = $value("SELECT COALESCE(SUM(ii.quantity * ii.cost_amount),0) FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE ii.item_type IN ('stock_part','external_part') AND i.invoice_date BETWEEN :start AND :end AND i.payment_status <> 'cancelled'", $rangeParams);
        $empty['parts_profit'] = $empty['income']['parts'] - $empty['parts_cost'];
        $empty['gross_profit'] = $empty['sales'] - $empty['parts_cost'];
        $empty['net_profit'] = $empty['gross_profit'] + $empty['income']['other'] - $empty['total_expenses'];
    } catch (Throwable $exception) {
        // Keep the administrator dashboard usable while an optional module table is being initialized.
    }
    return $empty + ['period' => $period, 'range_start' => $rangeStart->format('Y-m-d'), 'range_end' => $rangeEnd->format('Y-m-d')];
}

function cashier_dashboard_data(int $cashierId): array
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $empty = [
        'date' => $today, 'register' => null, 'expected' => 0.0, 'sales' => 0.0, 'income' => 0.0,
        'expenses' => 0.0, 'advances' => 0.0, 'jobs_today' => 0, 'open_jobs' => 0, 'completed_jobs' => 0,
        'due' => 0.0, 'invoice_count' => 0, 'payment_total' => 0.0, 'payment_count' => 0, 'recent_invoices' => [],
    ];
    try {
        if (!function_exists('ensure_cashier_register_table')) require_once __DIR__ . '/cashier-register.php';
        ensure_cashier_register_table();
        $pdo = database();
        $one = static function (string $sql, array $params = []) use ($pdo): float {
            $statement = $pdo->prepare($sql); $statement->execute($params); return (float) ($statement->fetchColumn() ?: 0);
        };
        $statement = $pdo->prepare('SELECT * FROM cashier_registers WHERE cashier_id=:cashier AND register_date=:date LIMIT 1');
        $statement->execute(['cashier' => $cashierId, 'date' => $today]);
        $empty['register'] = $statement->fetch() ?: null;
        $empty['sales'] = $one("SELECT COALESCE(SUM(p.amount_applied),0) FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id WHERE DATE(p.payment_date)=:date AND p.created_by=:cashier AND i.payment_status<>'cancelled'", ['date' => $today, 'cashier' => $cashierId]);
        $empty['payment_total'] = $empty['sales'];
        $empty['income'] = $one("SELECT COALESCE(SUM(amount),0) FROM other_income WHERE income_date=:date AND created_by=:cashier", ['date' => $today, 'cashier' => $cashierId]);
        $empty['expenses'] = $one("SELECT COALESCE(SUM(total_amount),0) FROM expenses WHERE expense_date=:date AND created_by=:cashier AND status<>'cancelled'", ['date' => $today, 'cashier' => $cashierId]);
        $empty['advances'] = $one("SELECT COALESCE(SUM(a.amount),0) FROM employee_advances a JOIN employees e ON e.id=a.employee_id WHERE a.advance_date=:date", ['date' => $today]);
        if (function_exists('ensure_cashier_advance_table')) {
            $empty['advances'] += $one("SELECT COALESCE(SUM(amount),0) FROM cashier_advance_requests WHERE advance_date=:date AND cashier_id=:cashier AND status='approved' AND disbursement_status='given'", ['date' => $today, 'cashier' => $cashierId]);
        }
        $empty['jobs_today'] = (int) $one('SELECT COUNT(*) FROM job_cards WHERE DATE(created_at)=:date AND created_by=:cashier', ['date' => $today, 'cashier' => $cashierId]);
        $empty['open_jobs'] = (int) $one("SELECT COUNT(*) FROM job_cards WHERE status IN ('pending','ongoing')");
        $empty['completed_jobs'] = (int) $one("SELECT COUNT(*) FROM job_cards WHERE status='completed' AND DATE(COALESCE(completed_at,created_at))=:date", ['date' => $today]);
        $empty['due'] = $one("SELECT COALESCE(SUM(GREATEST(balance_amount,0)),0) FROM invoices WHERE created_by=:cashier AND payment_status IN ('due','partial')", ['cashier' => $cashierId]);
        $empty['invoice_count'] = (int) $one("SELECT COUNT(DISTINCT p.invoice_id) FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id WHERE DATE(p.payment_date)=:date AND p.created_by=:cashier AND i.payment_status<>'cancelled'", ['date' => $today, 'cashier' => $cashierId]);
        $empty['payment_count'] = (int) $one("SELECT COUNT(*) FROM invoice_payments WHERE DATE(payment_date)=:date AND created_by=:cashier", ['date' => $today, 'cashier' => $cashierId]);
        $statement = $pdo->prepare("SELECT i.invoice_no,i.total_amount,i.payment_status,COALESCE(c.name,'Walk-in Customer') customer_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id WHERE i.created_by=:cashier AND i.payment_status<>'cancelled' ORDER BY i.created_at DESC LIMIT 5");
        $statement->execute(['cashier' => $cashierId]); $empty['recent_invoices'] = $statement->fetchAll();
        if ($empty['register']) {
            $transactions = cashier_register_transactions($today, $cashierId);
            $totals = cashier_register_totals($transactions, (float) $empty['register']['opening_amount']);
            $empty['expected'] = (float) $totals['expected'];
        }
    } catch (Throwable $exception) {
        // Keep the cashier workspace available while optional tables are initialized.
    }
    return $empty;
}
