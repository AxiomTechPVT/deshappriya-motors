<?php
declare(strict_types=1);
require __DIR__.'/stock-submission.php';
$pdo->exec('ALTER TABLE stock_batches ADD stock_purchase_item_id INTEGER');
$pdo->exec('ALTER TABLE stock_batches ADD supplier_id INTEGER');
$pdo->exec('CREATE TABLE stock_purchases (id INTEGER PRIMARY KEY, purchase_no TEXT UNIQUE, supplier_id INTEGER, purchase_date TEXT, invoice_no TEXT, payment_status TEXT, paid_amount REAL, balance_amount REAL, total_amount REAL, status TEXT, notes TEXT, created_by INTEGER)');
$pdo->exec('CREATE TABLE stock_purchase_items (id INTEGER PRIMARY KEY, purchase_id INTEGER, stock_item_id INTEGER, quantity REAL, unit_cost REAL, amount REAL, buying_price REAL, selling_price REAL, line_total REAL)');
$pdo->exec('CREATE TABLE stock_purchase_payments (id INTEGER PRIMARY KEY, purchase_id INTEGER, amount REAL, payment_method TEXT, paid_at TEXT, created_by INTEGER, initial_record INTEGER, UNIQUE(purchase_id,initial_record))');
$source = ['record_purchase'=>'1','purchase_date'=>'2026-09-15','payment_status'=>'paid','payment_method'=>'cash'];
check(stock_new_purchase_input([],20,200) === null, 'Opening stock must remain optional.');
foreach (['cash','card','bank_transfer'] as $method) foreach (['paid'=>4000.0,'partial'=>1500.0,'due'=>0.0] as $status=>$expectedPaid) {
    $source['payment_status'] = $status; $source['payment_method'] = $method;
    $source['purchase_paid_amount'] = '1500';
    $data = stock_new_purchase_input($source,20,200);
    $values['part_code'] = 'BUY-'.$method.'-'.$status;
    $token = bin2hex(random_bytes(32));
    $id = stock_save_new_submission($values,$token,$data);
    $purchase = $pdo->query('SELECT * FROM stock_purchases ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check((float)$purchase['total_amount'] === 4000.0 && (float)$purchase['paid_amount'] === $expectedPaid && (float)$purchase['balance_amount'] === 4000-$expectedPaid, 'Purchase totals must match payment status.');
    $batch = $pdo->query("SELECT * FROM stock_batches WHERE stock_item_id=$id")->fetchAll(PDO::FETCH_ASSOC);
    check(count($batch) === 1 && (float)$batch[0]['quantity_remaining'] === 20.0 && $batch[0]['stock_purchase_item_id'] !== null, 'Create one linked batch without a second opening batch.');
    $payment = $pdo->query('SELECT * FROM stock_purchase_payments ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check((float)$payment['amount'] === $expectedPaid && $payment['payment_method'] === $method && (int)$payment['created_by'] === 1, 'Payment must record amount, method and actor.');
    check(str_contains(stock_new_item_destination($id),'stock-purchase-receipt&id='.$purchase['id']), 'New purchase should open its receipt.');
    $counts = [];
    foreach (['stock_items','stock_batches','stock_movements','stock_purchases','stock_purchase_items','stock_purchase_payments'] as $table) $counts[$table] = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    check(stock_save_new_submission($values,$token,$data) === $id, 'Retry must return the same purchased item.');
    foreach ($counts as $table=>$count) check($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $count, "$table must not duplicate on retry.");
    $purchase['supplier_name'] = 'Supplier <test>';
    $purchaseItems = [$values + ['unit_cost'=>200,'amount'=>4000,'quantity'=>20]];
    $purchasePayments = [$payment + ['payer_name'=>'Test Cashier']];
    ob_start(); require __DIR__.'/../views/stock-purchase-receipt.php'; $html = ob_get_clean();
    check(str_contains($html,'Supplier &lt;test&gt;'), 'Receipt must escape supplier details.');
    check(str_contains($html,'4,000.00') && str_contains($html,'Print Receipt'), 'Receipt must show totals and print control.');
    check(str_contains($html,'No payment recorded') === ($status === 'due'), 'Due must not look like a paid receipt.');
    if ($status !== 'due') check(str_contains($html,['cash'=>'Cash','card'=>'Card','bank_transfer'=>'Online Transfer'][$method]), 'Receipt must show payment method.');
}
foreach ([['purchase_date'=>'2026-02-30'],['payment_status'=>'invalid'],['payment_method'=>'invalid'],['payment_status'=>'partial','purchase_paid_amount'=>'4001'],['payment_status'=>'partial','purchase_paid_amount'=>'oops']] as $invalid) {
    try { stock_new_purchase_input(array_replace($source,$invalid),20,200); throw new RuntimeException('Invalid purchase was accepted.'); }
    catch (InvalidArgumentException $error) {}
}
$pdo->exec("CREATE TRIGGER fail_payment BEFORE INSERT ON stock_purchase_payments BEGIN SELECT RAISE(ABORT, 'Payment failure'); END");
$values['part_code'] = 'BUY-FAIL'; $failedToken = bin2hex(random_bytes(32));
try { stock_save_new_submission($values,$failedToken,$data); throw new RuntimeException('Expected payment failure.'); }
catch (PDOException $error) {}
foreach ($counts as $table=>$count) check($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $count, "$table must roll back when payment fails.");
check(stock_submission_item_id($failedToken) === 0, 'Failed purchase must allow retry.');
echo "PASS: purchase payment methods/statuses, receipts, duplicate prevention, validation and rollback.\n";
function e(string $value): string { return htmlspecialchars($value,ENT_QUOTES,'UTF-8'); }
function receipt_settings(): array { return ['garage_name'=>'Test Garage']; }
function powered_by_text(): string { return 'Powered By Axiom Tech 0721284460'; }
