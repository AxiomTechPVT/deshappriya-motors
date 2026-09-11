<?php
// Run with PHP CLI. All fixtures use connection-local temporary tables.
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/stock.php';
require __DIR__ . '/../includes/cashier-register.php';

function current_user(): array { return ['id' => 7]; }
function cashier_advance_requests_for_date(string $date, int $cashierId): array { return []; }
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$pdo = database();
$pdo->exec("CREATE TEMPORARY TABLE stock_purchases (
    id BIGINT PRIMARY KEY, purchase_no VARCHAR(30), purchase_date DATE,
    created_at DATETIME, created_by BIGINT, paid_amount DECIMAL(12,2),
    balance_amount DECIMAL(12,2), total_amount DECIMAL(12,2),
    payment_status VARCHAR(20), status VARCHAR(20)) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE stock_purchase_payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, purchase_id BIGINT, amount DECIMAL(12,2),
    payment_method VARCHAR(30), paid_at DATETIME, created_by BIGINT,
    initial_record TINYINT NULL, UNIQUE KEY (purchase_id, initial_record)) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE invoices (id BIGINT, invoice_no VARCHAR(30), job_card_id BIGINT)");
$pdo->exec("CREATE TEMPORARY TABLE invoice_payments (id BIGINT, invoice_id BIGINT, payment_no VARCHAR(30), payment_date DATETIME, amount_applied DECIMAL(12,2), payment_method VARCHAR(30), created_by BIGINT)");
$pdo->exec("CREATE TEMPORARY TABLE job_cards (id BIGINT, job_card_no VARCHAR(30))");
$pdo->exec("CREATE TEMPORARY TABLE job_card_payments (id BIGINT, job_card_id BIGINT, receipt_no VARCHAR(30), paid_at DATETIME, amount DECIMAL(12,2), payment_method VARCHAR(30), created_by BIGINT)");
$pdo->exec("CREATE TEMPORARY TABLE other_income (id BIGINT, reference_no VARCHAR(30), created_at DATETIME, amount DECIMAL(12,2), title VARCHAR(100), income_date DATE, payment_method VARCHAR(30), created_by BIGINT, category VARCHAR(100))");
$pdo->exec("CREATE TEMPORARY TABLE expenses (id BIGINT, expense_no VARCHAR(30), created_at DATETIME, paid_amount DECIMAL(12,2), description VARCHAR(100), expense_date DATE, payment_method VARCHAR(30), status VARCHAR(20), created_by BIGINT)");
$pdo->exec("CREATE TEMPORARY TABLE employees (id BIGINT, name VARCHAR(100))");
$pdo->exec("CREATE TEMPORARY TABLE employee_advances (id BIGINT, created_at DATETIME, amount DECIMAL(12,2), employee_id BIGINT, advance_date DATE)");
$pdo->exec("INSERT INTO stock_purchases VALUES
    (1,'PUR-1','2026-09-10','2026-09-10 09:00:00',7,3000,2000,5000,'partial','received'),
    (2,'PUR-2','2026-09-10','2026-09-10 09:00:00',8,1000,0,1000,'paid','received'),
    (3,'PUR-3','2026-09-10','2026-09-10 09:00:00',7,1000,0,1000,'paid','cancelled'),
    (4,'PUR-4','2026-09-10','2026-09-10 09:00:00',7,0,2000,2000,'due','received')");
ensure_stock_payment_table();
ensure_stock_payment_table();
check((int) $pdo->query('SELECT COUNT(*) FROM stock_purchase_payments')->fetchColumn() === 4, 'Legacy payments must migrate only once.');
$rows = cashier_register_transactions('2026-09-10', 7);
check(count($rows) === 1 && $rows[0]['amount'] === 3000.0, 'Exclude other cashiers, cancelled and unpaid purchases.');
check(cashier_register_totals($rows, 10000)['expected'] === 7000.0, 'Stock cash must reduce expected handover.');
stock_record_payment($pdo, 4, 500, 'bank_transfer', '2026-09-11 10:00:00');
stock_record_payment($pdo, 4, 500, 'card', '2026-09-11 10:01:00');
stock_record_payment($pdo, 4, 500, 'cheque', '2026-09-11 10:02:00');
check(cashier_register_transactions('2026-09-11', 7) === [], 'Non-cash payments must not reduce the drawer.');
$errors = [];
check(stock_purchase_payment(1, 1000, $errors), 'Cash balance payment should succeed.');
$todayRows = cashier_register_transactions(date('Y-m-d'), 7);
$todayCash = array_sum(array_column($todayRows, 'amount'));
// The original purchase date may coincide with the test runner date.
$expectedCash = date('Y-m-d') === '2026-09-10' ? 4000.0 : 1000.0;
check($todayCash === $expectedCash, 'Balance payment must belong to the payment day.');
check(!stock_purchase_payment(1, 2000, $errors), 'Overpayment must be rejected.');
check(!stock_purchase_payment(1, 100, $errors, 'invalid'), 'Invalid method must be rejected.');
check((float) $pdo->query('SELECT paid_amount FROM stock_purchases WHERE id=1')->fetchColumn() === 4000.0, 'Failed payments must not change the purchase.');
$pdo->exec('UPDATE stock_purchases SET created_by=8 WHERE id=4');
check(stock_purchase_payment(4, 100, $errors), 'A different cashier can pay an existing purchase.');
$last = $pdo->query('SELECT created_by FROM stock_purchase_payments ORDER BY id DESC LIMIT 1')->fetchColumn();
check((int) $last === 7, 'Payment must belong to its payer, not the purchase creator.');
$pdo->exec('ALTER TABLE stock_purchases MODIFY id BIGINT NOT NULL AUTO_INCREMENT, ADD supplier_id BIGINT NULL, ADD invoice_no VARCHAR(80) NULL, ADD notes TEXT NULL');
$pdo->exec('CREATE TEMPORARY TABLE stock_items (id BIGINT PRIMARY KEY, stock_qty DECIMAL(12,2), buying_price DECIMAL(12,2), selling_price DECIMAL(12,2), supplier_id BIGINT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE stock_purchase_items (id BIGINT AUTO_INCREMENT PRIMARY KEY, purchase_id BIGINT, stock_item_id BIGINT, quantity DECIMAL(12,2), unit_cost DECIMAL(12,2), amount DECIMAL(12,2), buying_price DECIMAL(12,2), selling_price DECIMAL(12,2), line_total DECIMAL(12,2)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE stock_batches (id BIGINT AUTO_INCREMENT PRIMARY KEY, stock_item_id BIGINT, stock_purchase_item_id BIGINT, quantity_received DECIMAL(12,2), quantity_remaining DECIMAL(12,2), buying_price DECIMAL(12,2), selling_price_at_purchase DECIMAL(12,2), purchase_date DATE, supplier_id BIGINT NULL, batch_reference VARCHAR(80)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE stock_movements (id BIGINT AUTO_INCREMENT PRIMARY KEY, stock_item_id BIGINT, movement_type VARCHAR(30), quantity DECIMAL(12,2), quantity_before DECIMAL(12,2), quantity_after DECIMAL(12,2), unit_cost DECIMAL(12,2), reference_type VARCHAR(30), reference_id BIGINT, notes TEXT, created_by BIGINT) ENGINE=InnoDB');
$pdo->exec('INSERT INTO stock_items VALUES (1,10,100,150,NULL)');
$input = ['stock_item_id'=>1, 'quantity'=>'10', 'buying_price'=>'100', 'selling_price'=>'150', 'supplier_id'=>0, 'purchase_date'=>'2026-09-12', 'invoice_no'=>'', 'payment_status'=>'partial', 'paid_amount'=>'250', 'payment_method'=>'cash', 'notes'=>''];
check(restock_save($input, $errors), 'Partial cash restock must save successfully.');
$restockRows = cashier_register_transactions('2026-09-12', 7);
$restockRow = array_values(array_filter($restockRows, static fn(array $row): bool => $row['amount'] === 250.0));
check(count($restockRow) === 1, 'Only the paid portion of a restock must leave the drawer.');
$input['payment_method'] = 'bank_transfer';
check(restock_save($input, $errors), 'Bank restock must save successfully.');
check(count(cashier_register_transactions('2026-09-12', 7)) === count($restockRows), 'Bank restock must not change cash movement.');
$input['payment_method'] = 'invalid';
check(!restock_save($input, $errors), 'Invalid restock method must roll back.');
check((float) $pdo->query('SELECT stock_qty FROM stock_items WHERE id=1')->fetchColumn() === 30.0, 'Failed restock must not change stock quantities.');
echo "PASS: stock cash deductions, legacy migration, payment methods, date/payer attribution, and payment rollback.\n";
