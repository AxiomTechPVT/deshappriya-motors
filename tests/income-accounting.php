<?php
// Run: php tests/income-accounting.php. All fixtures are in-memory SQLite.
declare(strict_types=1);
require __DIR__ . '/../includes/income-accounting.php';
require __DIR__ . '/../includes/invoices.php';
require __DIR__ . '/../includes/job-cards.php';

function database(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
    }
    return $pdo;
}
function current_user(): array { return ['id'=>7]; }
function ensure_stock_tables(): void {} // Schema setup is replaced by isolated fixtures.
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function fixture_table(string $name, string $columns): void {
    database()->exec('CREATE TABLE ' . $name . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, ' . $columns . ')');
}
fixture_table('other_income', 'income_date, title, category, amount, payment_method, reference_no, notes, created_by');
fixture_table('invoices', 'invoice_no, invoice_type, job_card_id, customer_id, vehicle_id, vehicle_display_number, invoice_date, subtotal, special_service_charge, discount_type, discount_value, discount_amount, total_amount, paid_amount, balance_amount, payment_status, payment_method, notes, created_by');
fixture_table('invoice_items', 'invoice_id, item_type, service_id, stock_item_id, external_part_id, description, quantity, unit_price, cost_amount, line_total');
fixture_table('invoice_payments', 'invoice_id, payment_no, job_card_id, customer_id, payment_method, amount_received, amount_applied, change_given, payment_reference, created_by');
fixture_table('expenses', 'expense_no, expense_date, category, expense_type, description, job_card_id, vehicle_id, payment_terms, payment_method, subtotal, total_amount, paid_amount, balance_amount, payment_date, status, created_by');
fixture_table('expense_external_parts', 'expense_id, job_card_id, vehicle_id, part_name, quantity, unit_cost, selling_price, total_cost');
$pdo = database();
$insert = $pdo->prepare('INSERT INTO other_income (income_date,title,category,amount,payment_method) VALUES (?,?,?,?,?)');
foreach ([
    ['2026-09-15','Scrap sale','Scrap Sale',50,'cash'],
    ['2026-09-15','Miscellaneous',null,25,'bank'],
    ['2026-09-15','Commission','',10,'other'],
    ['2026-09-15','Invoice Profit - INV-1','Invoice Profit',200,'other'],
    ['2026-09-15','External Part Profit - Pad','External Part Profit',200,'other'],
    ['2026-09-15','Invoice Payment - INV-1','Invoice Payment',1400,'cash'],
    ['2026-09-14','Earlier scrap sale','Scrap Sale',70,'cash'],
] as $row) $insert->execute($row);
check(other_income_revenue_total('2026-09-15','2026-09-15') === 85.0, 'Legacy profit/payment records must not inflate other revenue; null/blank categories must remain.');
check(other_income_revenue_total('2026-09-14','2026-09-15') === 155.0, 'Period boundaries must include real revenue only.');
check(other_income_revenue_total('2026-09-16','2026-09-16') === 0.0, 'An empty period must return zero.');
$recent = other_income_revenue_recent('2026-09-15','2026-09-15');
check(array_column($recent, 'title') === ['Commission','Miscellaneous','Scrap sale'], 'Dashboard/report detail lists must match revenue totals.');
$net = 1400 - (2 * 100) + other_income_revenue_total('2026-09-15','2026-09-15') - 30;
check($net === 1255.0, 'Net profit must include invoice margin and genuine other income exactly once.');

$before = (int)$pdo->query('SELECT COUNT(*) FROM other_income')->fetchColumn();
add_external_part_expense(['id'=>1,'vehicle_id'=>0], 'Brake pad', 2, 100, 200);
check((int)$pdo->query('SELECT COUNT(*) FROM other_income')->fetchColumn() === $before, 'External part creation must not post estimated profit as other income.');
check((float)$pdo->query('SELECT total_cost FROM expense_external_parts')->fetchColumn() === 200.0, 'External part cost must still be saved.');

// Exercise the real quick-invoice writer without inventory locking (custom line).
foreach ([0, 400] as $received) {
    $errors = [];
    $id = invoice_create(['invoice_type'=>'quick', 'invoice_date'=>'2026-09-15', 'amount_received'=>$received, 'payment_method'=>'cash', 'items'=>[['type'=>'custom','description'=>'Custom item','quantity'=>2,'unit_price'=>200]]], 'save', $errors);
    check($id !== null && !$errors, 'Quick invoice must save: ' . implode('; ', $errors));
    check((int)$pdo->query("SELECT COUNT(*) FROM other_income WHERE category='Invoice Profit'")->fetchColumn() === 1, 'Quick invoice must not create another profit entry.');
}
check((float)$pdo->query('SELECT SUM(amount_applied) FROM invoice_payments')->fetchColumn() === 400.0, 'Actual invoice payments must remain recorded.');
check((int)$pdo->query("SELECT COUNT(*) FROM other_income WHERE category='Invoice Payment'")->fetchColumn() === 2, 'Payment history must be preserved.');
check(other_income_revenue_total('2026-09-15','2026-09-15') === 85.0, 'New invoice activity must not change genuine other income.');
check((int)$pdo->query("SELECT COUNT(*) FROM other_income WHERE category IN ('Invoice Profit','External Part Profit')")->fetchColumn() === 2, 'Legacy profit records must be preserved, not deleted.');
echo "PASS: legacy exclusions, real other income, dates, profit totals, external part save, paid/unpaid quick invoices, and preserved payment history.\n";

fixture_table('services', 'service_name, price, status');
$pdo->exec("INSERT INTO services (service_name,price,status) VALUES ('Oil change',500,'active')");
$errors = [];
$id = invoice_create(['invoice_type'=>'quick','payment_method'=>'cash','items'=>[
    ['type'=>'service','service_id'=>'__custom__','description'=>'Special repair','quantity'=>2,'unit_price'=>350],
    ['type'=>'service','service_id'=>1,'quantity'=>1,'unit_price'=>1],
]], 'save', $errors);
check($id !== null && !$errors, 'Typed and listed services should save together.');
$savedItems = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY id');
$savedItems->execute(['id'=>$id]);
$rows = $savedItems->fetchAll();
check($rows[0]['item_type'] === 'service' && $rows[0]['service_id'] === null && $rows[0]['description'] === 'Special repair' && (float)$rows[0]['line_total'] === 700.0, 'Typed service must retain its name, service classification and price.');
check((float)$rows[1]['unit_price'] === 500.0, 'Listed service must retain its authoritative catalog price.');
foreach ([['',350],['Repair',-1],['Repair','invalid']] as [$name,$price]) {
    $id = invoice_create(['payment_method'=>'cash','items'=>[['type'=>'service','service_id'=>'__custom__','description'=>$name,'quantity'=>1,'unit_price'=>$price]]], 'save', $errors);
    check($id === null && count($errors) > 0, 'Invalid typed service must be rejected.');
}
echo "PASS: typed services, catalog services, service revenue classification and validation.\n";
