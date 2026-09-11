<?php

declare(strict_types=1);

function ensure_stock_payment_table(): void
{
    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_purchase_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        purchase_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
        paid_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        initial_record TINYINT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY stock_payment_initial_unique (purchase_id, initial_record),
        KEY stock_payment_purchase_index (purchase_id),
        KEY stock_payment_cashier_date_index (created_by, paid_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Older purchases recorded only a cumulative paid amount, with no method or payer history.
    // Preserve that amount as an initial cash payment on the purchase date.
    $pdo->exec("INSERT IGNORE INTO stock_purchase_payments (purchase_id, amount, payment_method, paid_at, created_by, initial_record)
        SELECT p.id, p.paid_amount, 'cash', TIMESTAMP(p.purchase_date, TIME(p.created_at)), p.created_by, 1
        FROM stock_purchases p
        WHERE NOT EXISTS (SELECT 1 FROM stock_purchase_payments sp WHERE sp.purchase_id = p.id)");
}

function stock_record_payment(PDO $pdo, int $purchaseId, float $amount, string $method, string $paidAt, bool $initial = false): void
{
    if (!in_array($method, ['cash', 'bank_transfer', 'card', 'cheque'], true)) {
        throw new InvalidArgumentException('Select a valid payment method.');
    }
    $statement = $pdo->prepare('INSERT INTO stock_purchase_payments (purchase_id, amount, payment_method, paid_at, created_by, initial_record) VALUES (:purchase, :amount, :method, :paid_at, :cashier, :initial)');
    $statement->execute(['purchase' => $purchaseId, 'amount' => $amount, 'method' => $method, 'paid_at' => $paidAt, 'cashier' => current_user()['id'] ?? null, 'initial' => $initial ? 1 : null]);
}
