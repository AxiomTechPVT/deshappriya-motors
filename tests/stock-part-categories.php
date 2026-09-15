<?php
// Run with PHP CLI; pass --sqlite for an isolated in-memory database.
// MySQL fixtures only use a connection-local temporary table.
declare(strict_types=1);
if (in_array('--sqlite', $argv, true)) {
    function database(): PDO {
        static $pdo;
        return $pdo ??= new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
} else {
    require __DIR__ . '/../config/database.php';
}
require __DIR__ . '/../includes/stock.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return 'test-token'; }

$pdo = database();
$pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
    ? 'CREATE TEMPORARY TABLE stock_items (id INTEGER PRIMARY KEY AUTOINCREMENT, part_code VARCHAR(60) NOT NULL UNIQUE, category VARCHAR(100) COLLATE NOCASE)'
    : "CREATE TEMPORARY TABLE stock_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_code VARCHAR(60) NOT NULL UNIQUE,
    category VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$insert = $pdo->prepare('INSERT INTO stock_items (part_code,category) VALUES (?,?)');
$insert->execute(['MANUAL-001', 'filters']);
$insert->execute(['MANUAL-002', 'Custom Garage Category']);
$insert->execute(['MANUAL-003', 'Custom Garage Category']);
$insert->execute(['MANUAL-004', '  ']);
$categories = stock_categories();
check(in_array('Brake Parts', $categories, true), 'Default garage categories should be available.');
check(count(array_keys($categories, 'Custom Garage Category', true)) === 1, 'Saved custom categories should appear once.');
check(count(array_filter($categories, fn($category) => strtolower($category) === 'filters')) === 1, 'Default and saved categories should not duplicate by case.');
check(!in_array('', $categories, true), 'Blank categories must be excluded.');

$input = stock_input(['part_name' => 'New brake pad', 'category' => ' New category ']);
check($input['part_code'] === '', 'The form may submit a blank part number.');
$input['part_code'] = stock_generate_part_code();
check(validate_stock($input) === [], 'An automatically numbered new part should validate.');
$insert->execute([$input['part_code'], $input['category']]);
check(in_array('New category', stock_categories(), true), 'A newly saved category must be available on the next list load.');
check(in_array('This part code already exists.', validate_stock($input), true), 'Duplicate part numbers must still be rejected.');
check(validate_stock($input, (int)$pdo->lastInsertId()) === [], 'An existing item must retain its own part number.');
for ($i = 0; $i < 100; $i++) {
    $code = stock_generate_part_code();
    check((bool)preg_match('/^PART-[A-F0-9]{12}$/', $code), 'Generated codes should have a consistent format.');
    $insert->execute([$code, null]);
}

$errors = [];
$id = 0;
$stockOptions = [];
$suppliers = [];
foreach (['stock-add', 'stock-edit'] as $section) {
    ob_start();
    require __DIR__ . '/../views/stock-form.php';
    $html = ob_get_clean();
    preg_match('/<input[^>]*id="stock-part-code"[^>]*>/', $html, $codeInput);
    check(isset($codeInput[0]), 'Part number input must render.');
    check((bool)preg_match('/\srequired(?:\s|>)/', $codeInput[0]) === ($section === 'stock-edit'), 'Only editing should require a part number in the browser.');
    check(str_contains($html, 'list="stock-categories"'), 'Category input should offer saved suggestions while allowing typing.');
    check(str_contains($html, '<option value="New category">'), 'The form must render newly saved category suggestions.');
}
echo "PASS: generated part numbers, duplicate validation, category defaults and saved suggestions, add/edit form requirements.\n";
