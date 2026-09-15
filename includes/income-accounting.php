<?php
declare(strict_types=1);

// These legacy system categories duplicate invoice revenue/profit or collections.
// Keep their records for history, but never count them as additional revenue.
const OTHER_INCOME_REVENUE_CONDITION = "(category IS NULL OR category NOT IN ('Invoice Profit', 'External Part Profit', 'Invoice Payment'))";

function other_income_revenue_total(string $from, string $to): float
{
    $statement = database()->prepare('SELECT COALESCE(SUM(amount),0) FROM other_income WHERE income_date BETWEEN :from_date AND :to_date AND ' . OTHER_INCOME_REVENUE_CONDITION);
    $statement->execute(['from_date' => $from, 'to_date' => $to]);
    return (float)$statement->fetchColumn();
}

function other_income_revenue_recent(string $from, string $to): array
{
    $statement = database()->prepare('SELECT title, income_date, payment_method, amount FROM other_income WHERE income_date BETWEEN :from_date AND :to_date AND ' . OTHER_INCOME_REVENUE_CONDITION . ' ORDER BY income_date DESC, id DESC LIMIT 5');
    $statement->execute(['from_date' => $from, 'to_date' => $to]);
    return $statement->fetchAll();
}
