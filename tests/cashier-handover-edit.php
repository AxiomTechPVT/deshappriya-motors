<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/cashier-register.php';
function current_user(): array { return $GLOBALS['testUser']; }
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['testUser'] = ['id'=>1, 'role'=>'administrator'];
$pdo = database();
$pdo->exec("CREATE TEMPORARY TABLE cashier_registers (
    id BIGINT PRIMARY KEY, accepted_amount DECIMAL(12,2), actual_closing_amount DECIMAL(12,2),
    difference_amount DECIMAL(12,2), expected_closing_amount DECIMAL(12,2), handover_amount DECIMAL(12,2),
    acceptance_note VARCHAR(255), accepted_by BIGINT, accepted_at DATETIME,
    handover_status VARCHAR(30), status VARCHAR(20))");
$pdo->exec("INSERT INTO cashier_registers VALUES
    (1,10000,10000,-4500,14500,10000,NULL,2,NOW(),'accepted','closed'),
    (2,NULL,NULL,NULL,10000,10000,NULL,NULL,NULL,'pending','open')");
cashier_handover_edit(1, ['accepted_amount'=>'14500.00','acceptance_note'=>'Corrected cash count']);
$row = $pdo->query('SELECT * FROM cashier_registers WHERE id=1')->fetch();
check((float)$row['accepted_amount'] === 14500.0 && (float)$row['actual_closing_amount'] === 14500.0 && (float)$row['difference_amount'] === 0.0, 'Received cash and balance must update together.');
check((float)$row['handover_amount'] === 10000.0 && (float)$row['expected_closing_amount'] === 14500.0, 'Original handed over and expected amounts must remain intact.');
check((int)$row['accepted_by'] === 1 && $row['acceptance_note'] === 'Corrected cash count', 'Latest admin and note must be saved.');
foreach (['', '-1', 'abc', 'NaN', '1.234', '10000000000'] as $amount) {
    try { cashier_handover_edit(1, ['accepted_amount'=>$amount]); throw new RuntimeException('Invalid amount accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
foreach ([2,999] as $id) {
    try { cashier_handover_edit($id, ['accepted_amount'=>'100']); throw new RuntimeException('Ineligible record edited.'); }
    catch (InvalidArgumentException $expected) {}
}
$GLOBALS['testUser']['role'] = 'cashier';
try { cashier_handover_edit(1, ['accepted_amount'=>'0']); throw new RuntimeException('Cashier was allowed to edit.'); }
catch (InvalidArgumentException $expected) {}
check((float)$pdo->query('SELECT accepted_amount FROM cashier_registers WHERE id=1')->fetchColumn() === 14500.0, 'Rejected edits must not change cash.');
echo "PASS: admin edit, balance recalculation, preserved handover, amount validation and access restrictions.\n";
