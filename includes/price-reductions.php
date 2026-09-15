<?php
declare(strict_types=1);

function ensure_price_snapshot_column(string $table): void
{
    static $ready = [];
    if (!in_array($table, ['job_card_items', 'invoice_items'], true)) throw new InvalidArgumentException('Invalid price snapshot table.');
    if (isset($ready[$table])) return;
    $pdo = database();
    if (!$pdo->query("SHOW COLUMNS FROM {$table} LIKE 'list_unit_price'")->fetch()) {
        // NULL means the historical list price is unknown; never infer it from today's stock price.
        $pdo->exec("ALTER TABLE {$table} ADD list_unit_price DECIMAL(12,2) NULL AFTER unit_price");
    }
    $ready[$table] = true;
}

function stock_list_price_snapshot(int $stockId): ?float
{
    if ($stockId < 1) return null;
    $statement = database()->prepare('SELECT selling_price FROM stock_items WHERE id=:id');
    $statement->execute(['id'=>$stockId]);
    $price = $statement->fetchColumn();
    return $price === false ? null : (float)$price;
}

function item_price_reduction(array $item): float
{
    if (!isset($item['list_unit_price'])) return 0.0;
    return round(max(0, (float)$item['list_unit_price'] - (float)$item['unit_price']) * (float)$item['quantity'], 2);
}

function price_reduction_sql(string $alias): string
{
    if (!in_array($alias, ['ii', 'ji'], true)) throw new InvalidArgumentException('Invalid item alias.');
    return "CASE WHEN {$alias}.list_unit_price > {$alias}.unit_price THEN ROUND(({$alias}.list_unit_price - {$alias}.unit_price) * {$alias}.quantity, 2) ELSE 0 END";
}
