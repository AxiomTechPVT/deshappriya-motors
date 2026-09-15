<?php
// Run: php tests/parts-profit.php. Uses only an in-memory SQLite database.
declare(strict_types=1);
require __DIR__ . '/../includes/invoices.php';

function database(): PDO {
    static $pdo;
    return $pdo ??= new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function job_card_record(int $id): array {
    return ['items' => [['id'=>1, 'item_type'=>'part', 'stock_item_id'=>1, 'item_name'=>'Brake pad', 'quantity'=>2, 'unit_price'=>200]], 'service_charge'=>1000];
}
$pdo = database();
$pdo->exec('CREATE TABLE stock_movements (id INTEGER PRIMARY KEY, reference_type TEXT, reference_id INTEGER, unit_cost NUMERIC)');
$pdo->exec("INSERT INTO stock_movements VALUES (1,'job_card_item',1,100)");
$pdo->exec('CREATE TABLE expense_external_parts (id INTEGER, job_card_id INTEGER, part_name TEXT, quantity NUMERIC, selling_price NUMERIC, unit_cost NUMERIC)');
$job = invoice_job_data(1);
check($job['invoice_items'][0]['cost_amount'] === 100.0, 'Invoice snapshot must preserve the unit cost, not the extended cost.');
$pdo->exec('CREATE TABLE invoices (id INTEGER PRIMARY KEY, invoice_date TEXT, payment_status TEXT)');
$pdo->exec('CREATE TABLE invoice_items (invoice_id INTEGER, item_type TEXT, quantity NUMERIC, cost_amount NUMERIC)');
$pdo->exec("INSERT INTO invoices VALUES (1,'2026-09-15','paid'), (2,'2026-09-15','cancelled'), (3,'2026-09-14','paid')");
$insert = $pdo->prepare('INSERT INTO invoice_items VALUES (1,?,?,?)');
$part = $job['invoice_items'][0];
$insert->execute([$part['type'], $part['quantity'], $part['cost_amount']]);
$pdo->exec("INSERT INTO invoice_items VALUES (2,'stock_part',10,100), (3,'stock_part',10,100), (1,'service',5,500)");

// Execute the actual dashboard/report cost queries to detect regressions in either consumer.
$queries = [];
foreach (['functions.php', 'reports.php'] as $file) {
    $source = file_get_contents(__DIR__ . '/../includes/' . $file);
    preg_match('/([\x22\x27])(SELECT COALESCE\(SUM\(ii\.[^\r\n]*?cost_amount\).*?)\1/s', $source, $match);
    check(isset($match[2]), 'Missing cost query in ' . $file);
    $queries[$file] = $pdo->prepare($match[2]);
}
function assert_cost(float $expected, ?float $partsRevenue = null, ?float $sales = null): void {
    global $queries;
    foreach ($queries as $file => $query) {
        $params = $file === 'functions.php' ? ['start'=>'2026-09-15','end'=>'2026-09-15'] : ['from_date'=>'2026-09-15','to_date'=>'2026-09-15'];
        $query->execute($params);
        $cost = (float)$query->fetchColumn();
        check($cost === $expected, $file . ': incorrect extended parts cost.');
        if ($partsRevenue !== null) check($partsRevenue - $cost === 200.0, $file . ': parts profit must be Rs.200.');
        if ($sales !== null) check($sales - $cost === 1200.0, $file . ': gross profit must be Rs.1,200.');
    }
}
assert_cost(200.0, 400.0, 1400.0);
$insert->execute(['external_part', 3, 40]);
$insert->execute(['stock_part', 0.5, 80]);
$insert->execute(['stock_part', 1, 25]);
$insert->execute(['stock_part', 2, 0]);
assert_cost(385.0);
$pdo->exec('DELETE FROM invoice_items');
assert_cost(0.0);
echo "PASS: invoice unit-cost snapshot; dashboard and profit report quantity costs; external, fractional, single, zero-cost, cancelled and out-of-period items.\n";
