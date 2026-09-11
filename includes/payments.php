<?php

declare(strict_types=1);

function handle_payments_request(): void
{
    invoice_ensure_tables();
    $customerId = max(0, (int)($_GET['customer_id'] ?? 0));
    $search = trim((string)($_GET['search'] ?? ''));
    $status = in_array($_GET['status'] ?? '', ['all','outstanding','due','partial','paid'], true) ? $_GET['status'] : 'outstanding';
    $customers = database()->query('SELECT id,name,customer_code,contact_number FROM customers ORDER BY name')->fetchAll();
    $where = ["i.payment_status <> 'cancelled'"];
    $params = [];
    if ($customerId) { $where[] = 'i.customer_id=:customer'; $params['customer'] = $customerId; }
    if ($search !== '') {
        $where[] = '(i.invoice_no LIKE :invoice OR c.name LIKE :name OR c.contact_number LIKE :phone OR c.customer_code LIKE :code)';
        foreach (['invoice','name','phone','code'] as $key) $params[$key] = '%'.$search.'%';
    }
    if ($status === 'outstanding') $where[] = "i.payment_status IN ('due','partial') AND i.balance_amount > 0";
    elseif ($status !== 'all') { $where[] = 'i.payment_status=:status'; $params['status'] = $status; }
    $statement = database()->prepare('SELECT i.*, c.name AS customer_name, c.contact_number FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id WHERE '.implode(' AND ', $where).' ORDER BY i.id DESC');
    $statement->execute($params);
    $outstanding = $statement->fetchAll();
    $totalDue = array_sum(array_column($outstanding, 'balance_amount'));
    $total = count($outstanding);
    $pages = max(1, (int)ceil($total / 5));
    $currentPage = min($pages, max(1, (int)($_GET['p'] ?? 1)));
    $offset = ($currentPage - 1) * 5;
    $rows = array_slice($outstanding, $offset, 5);
    $paginationUrl = 'index.php?'.http_build_query(['page'=>'admin','section'=>'payments','customer_id'=>$customerId,'search'=>$search,'status'=>$status]);
    $statement = database()->prepare('SELECT p.*,i.invoice_no,c.name AS customer_name FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id LEFT JOIN customers c ON c.id=p.customer_id WHERE (:customer=0 OR p.customer_id=:customer) ORDER BY p.id DESC LIMIT 20');
    $statement->execute(['customer'=>$customerId]);
    $history = $statement->fetchAll();
    $title = 'Customer Payments';
    $section = 'payments';
    $sectionForHeader = 'payments';
    require __DIR__ . '/header.php';
    require __DIR__ . '/../views/payments.php';
    require __DIR__ . '/footer.php';
}
