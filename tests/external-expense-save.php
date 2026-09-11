<?php
// CLI regression test; fixtures live only in temporary database tables.
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/expenses.php';
function ensure_suppliers_table(): void {}
function current_user(): array { return ['id' => 7, 'role' => 'cashier']; }
function verify_csrf(): void {}
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return 'test'; }
function flash(string $type, string $message): void {
    if ($type !== 'success') throw new RuntimeException($message);
}
function redirect(string $url): void {
    $expense = database()->query('SELECT * FROM expenses')->fetch();
    $part = database()->query('SELECT * FROM expense_external_parts')->fetch();
    if (!$expense || !$part || $expense['category'] !== 'External Spare Part Purchase'
        || $expense['description'] !== 'Brake pad' || (int) $expense['job_card_id'] !== 8
        || (float) $expense['paid_amount'] !== 100.0 || (float) $part['total_cost'] !== 100.0
        || (int) $part['expense_id'] !== (int) $expense['id']) {
        throw new RuntimeException('External expense was not saved correctly.');
    }
    echo "PASS: external expense saves without hidden general fields, with correct job card, part and payment.\n";
    exit(0);
}
$pdo = database();
foreach (['expenses', 'expense_items', 'expense_categories', 'expense_external_parts'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE test_$table LIKE $table");
    $pdo->exec("ALTER TABLE test_$table RENAME TO $table");
}
$pdo->exec('CREATE TEMPORARY TABLE job_cards (id BIGINT PRIMARY KEY, job_card_no VARCHAR(30))');
$pdo->exec("INSERT INTO job_cards VALUES (8, 'JC-2609-00008')");
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
$_POST = ['expense_type'=>'external_part', 'expense_date'=>'2026-09-11',
    'job_card_id'=>'JC-2609-00008', 'supplier_id_external'=>1,
    'external_part_name'=>'Brake pad', 'external_quantity'=>'1', 'external_unit_cost'=>'100',
    'external_selling_price'=>'199.95', 'payment_method'=>'cash', 'payment_terms'=>'cash', 'tax_rate'=>'0'];
if (($argv[1] ?? '') === 'render') {
    $input = expense_input_extended();
    $section = 'expenses-add'; $id = 0; $errors = [];
    $categories = ['Electricity Bill']; $suppliers = [['id'=>1,'name'=>'Test supplier']];
    require __DIR__ . '/../views/expense-form.php';
    exit;
}
if (expense_validate(expense_input_extended())) throw new RuntimeException('External expense validation failed.');
handle_expense_request('expenses-add');
throw new RuntimeException('Save did not redirect to the expense list.');
