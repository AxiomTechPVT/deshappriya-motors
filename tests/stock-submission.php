<?php
declare(strict_types=1);
// Run: php tests/stock-submission.php (isolated SQLite database).
function database(): PDO {
    static $pdo;
    return $pdo ??= new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function current_user(): array { return ['id'=>1]; }
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
require __DIR__.'/../includes/stock.php';
$pdo = database();
$pdo->exec('CREATE TABLE stock_items (id INTEGER PRIMARY KEY AUTOINCREMENT, creation_token TEXT UNIQUE, part_code TEXT UNIQUE, part_name TEXT, category TEXT, brand TEXT, unit TEXT, buying_price REAL, selling_price REAL, stock_qty REAL, reorder_level REAL, supplier_id INTEGER, status TEXT, notes TEXT)');
$pdo->exec('CREATE TABLE stock_batches (id INTEGER PRIMARY KEY, stock_item_id INTEGER, quantity_received REAL, quantity_remaining REAL, buying_price REAL, selling_price_at_purchase REAL, purchase_date TEXT, batch_reference TEXT)');
$pdo->exec('CREATE TABLE stock_movements (id INTEGER PRIMARY KEY, stock_item_id INTEGER, movement_type TEXT, quantity REAL, quantity_before REAL, quantity_after REAL, unit_cost REAL, reference_type TEXT, reference_id INTEGER, notes TEXT, created_by INTEGER)');
$values = ['part_code'=>'PART-FIRST','part_name'=>'Rolling set','category'=>'Engine Parts','brand'=>'Bajaj','unit'=>'PCS','buying_price'=>200,'selling_price'=>400,'stock_qty'=>20,'reorder_level'=>5,'supplier_id'=>null,'status'=>'active','notes'=>null];
$token = bin2hex(random_bytes(32));
$id = stock_save_new_submission($values, $token);
$values['part_code'] = 'PART-RETRY';
check(stock_save_new_submission($values, $token) === $id, 'A repeated submission must return the original item.');
foreach (['stock_items','stock_batches','stock_movements'] as $table) {
    check((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === 1, "$table must only have one record after retry.");
}
check((float)$pdo->query('SELECT SUM(quantity_remaining) FROM stock_batches')->fetchColumn() === 20.0, 'Opening quantity must not double.');
check(stock_save_new_submission($values, bin2hex(random_bytes(32))) !== $id, 'A separate submission can create a distinct part with the same name.');
try {
    stock_save_new_submission($values, bin2hex(random_bytes(32)));
    throw new RuntimeException('Duplicate manual part code was accepted.');
} catch (PDOException $error) {
    check(!$pdo->inTransaction(), 'Duplicate part code must roll back.');
}
try {
    stock_save_new_submission($values, '');
    throw new RuntimeException('Missing token was accepted.');
} catch (InvalidArgumentException $error) {}
$pdo->exec("CREATE TRIGGER fail_opening BEFORE INSERT ON stock_movements BEGIN SELECT RAISE(ABORT, 'Simulated opening failure'); END");
$retryToken = bin2hex(random_bytes(32));
$values['part_code'] = 'PART-RECOVER';
try {
    stock_save_new_submission($values, $retryToken);
    throw new RuntimeException('Expected opening stock failure.');
} catch (PDOException $error) {}
check(stock_submission_item_id($retryToken) === 0, 'Failed save must not consume the token.');
foreach (['stock_items','stock_batches','stock_movements'] as $table) {
    check((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === 2, "$table must roll back on failure.");
}
$pdo->exec('DROP TRIGGER fail_opening');
check(stock_save_new_submission($values, $retryToken) > 0, 'Retry after a failed save must succeed.');
echo "Stock submission checks passed.\n";
