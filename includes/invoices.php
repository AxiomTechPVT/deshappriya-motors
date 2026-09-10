<?php

declare(strict_types=1);

function invoice_ensure_tables(): void
{
    static $ready = false;
    if ($ready) return;
    if (function_exists('ensure_stock_tables')) ensure_stock_tables();
    if (function_exists('ensure_job_card_tables')) ensure_job_card_tables();
    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_no VARCHAR(30) NOT NULL,
        invoice_type ENUM('job_card','quick') NOT NULL DEFAULT 'quick',
        job_card_id BIGINT UNSIGNED NULL,
        customer_id BIGINT UNSIGNED NULL,
        vehicle_id BIGINT UNSIGNED NULL,
        invoice_date DATE NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        special_service_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_type ENUM('fixed','percentage') NULL,
        discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_status ENUM('paid','partial','due','cancelled') NOT NULL DEFAULT 'due',
        payment_method ENUM('cash','card','bank','cheque','other') NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY invoices_no_unique (invoice_no),
        UNIQUE KEY invoices_job_card_unique (job_card_id),
        KEY invoices_date_index (invoice_date),
        KEY invoices_customer_index (customer_id),
        KEY invoices_status_index (payment_status),
        CONSTRAINT invoices_job_card_fk FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL,
        CONSTRAINT invoices_customer_fk FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        CONSTRAINT invoices_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
        CONSTRAINT invoices_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id BIGINT UNSIGNED NOT NULL,
        item_type ENUM('service','stock_part','external_part','custom') NOT NULL DEFAULT 'custom',
        service_id BIGINT UNSIGNED NULL,
        stock_item_id BIGINT UNSIGNED NULL,
        external_part_id BIGINT UNSIGNED NULL,
        description VARCHAR(190) NOT NULL,
        quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        cost_amount DECIMAL(12,2) NULL,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY invoice_items_invoice_index (invoice_id),
        CONSTRAINT invoice_items_invoice_fk FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT invoice_items_service_fk FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
        CONSTRAINT invoice_items_stock_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items(id) ON DELETE SET NULL,
        CONSTRAINT invoice_items_external_fk FOREIGN KEY (external_part_id) REFERENCES expense_external_parts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id BIGINT UNSIGNED NOT NULL,
        payment_no VARCHAR(40) NOT NULL,
        job_card_id BIGINT UNSIGNED NULL,
        customer_id BIGINT UNSIGNED NULL,
        payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        payment_method ENUM('cash','card','bank','cheque','other') NOT NULL DEFAULT 'cash',
        amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_applied DECIMAL(12,2) NOT NULL DEFAULT 0,
        change_given DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_reference VARCHAR(100) NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY invoice_payments_no_unique (payment_no),
        KEY invoice_payments_invoice_index (invoice_id),
        CONSTRAINT invoice_payments_invoice_fk FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT invoice_payments_job_fk FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL,
        CONSTRAINT invoice_payments_customer_fk FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        CONSTRAINT invoice_payments_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$pdo->query("SHOW COLUMNS FROM invoices LIKE 'vehicle_display_number'")->fetch()) $pdo->exec("ALTER TABLE invoices ADD vehicle_display_number VARCHAR(40) NULL AFTER vehicle_id");
    invoice_sync_completed_jobs($pdo);
    $ready = true;
}

function invoice_status(float $paid, float $total): string
{
    $balance = max(0, round($total - $paid, 2));
    return $balance <= 0.009 ? 'paid' : ($paid > 0.009 ? 'partial' : 'due');
}

function invoice_record(int $id): ?array
{
    $statement = database()->prepare('SELECT i.*,u.name AS created_by_name,c.name AS customer_name,c.contact_number,c.email,c.address,COALESCE(v.vehicle_number,i.vehicle_display_number) AS vehicle_number,v.make,v.model,v.vehicle_type,v.current_mileage,j.job_card_no FROM invoices i LEFT JOIN users u ON u.id=i.created_by LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN vehicles v ON v.id=i.vehicle_id LEFT JOIN job_cards j ON j.id=i.job_card_id WHERE i.id=:id LIMIT 1');
    $statement->execute(['id' => $id]);
    $invoice = $statement->fetch();
    if (!$invoice) return null;
    $items = database()->prepare('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY id');
    $items->execute(['id' => $id]);
    $invoice['items'] = $items->fetchAll();
    $payments = database()->prepare('SELECT * FROM invoice_payments WHERE invoice_id=:id ORDER BY id');
    $payments->execute(['id' => $id]);
    $invoice['payments'] = $payments->fetchAll();
    return $invoice;
}

function invoice_rows(array $filters = []): array
{
    $where = ['i.payment_status<>"cancelled"'];
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $where[] = '(i.invoice_no LIKE :search OR c.name LIKE :search OR COALESCE(v.vehicle_number,i.vehicle_display_number) LIKE :search OR j.job_card_no LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (in_array($filters['type'] ?? '', ['job_card', 'quick'], true)) { $where[] = 'i.invoice_type=:type'; $params['type'] = $filters['type']; }
    if (in_array($filters['status'] ?? '', ['paid', 'partial', 'due'], true)) { $where[] = 'i.payment_status=:status'; $params['status'] = $filters['status']; }
    if ((int)($filters['created_by'] ?? 0) > 0) { $where[] = 'i.created_by=:created_by'; $params['created_by'] = (int)$filters['created_by']; }
    if (($filters['date_from'] ?? '') !== '') { $where[] = 'i.invoice_date>=:date_from'; $params['date_from'] = $filters['date_from']; }
    if (($filters['date_to'] ?? '') !== '') { $where[] = 'i.invoice_date<=:date_to'; $params['date_to'] = $filters['date_to']; }
    $query = 'SELECT i.*,u.name AS created_by_name,c.name AS customer_name,COALESCE(v.vehicle_number,i.vehicle_display_number) AS vehicle_number,j.job_card_no FROM invoices i LEFT JOIN users u ON u.id=i.created_by LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN vehicles v ON v.id=i.vehicle_id LEFT JOIN job_cards j ON j.id=i.job_card_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC';
    $statement = database()->prepare($query); $statement->execute($params);
    return $statement->fetchAll();
}

function invoice_job_data(int $jobId): ?array
{
    $job = function_exists('job_card_record') ? job_card_record($jobId) : null;
    if (!$job) return null;
    $pdo = database();
    $items = [];
    foreach ($job['items'] as $item) {
        if (($item['item_type'] ?? '') === 'manual') continue;
        $type = ($item['item_type'] ?? '') === 'service' ? 'service' : 'stock_part';
        $cost = null;
        if ($type === 'stock_part' && !empty($item['stock_item_id'])) {
            $costStatement = $pdo->prepare("SELECT unit_cost FROM stock_movements WHERE reference_type='job_card_item' AND reference_id=:item ORDER BY id DESC LIMIT 1");
            $costStatement->execute(['item' => (int)$item['id']]);
            $cost = $costStatement->fetchColumn();
            if ($cost === false) {
                $stock = $pdo->prepare('SELECT buying_price FROM stock_items WHERE id=:id'); $stock->execute(['id' => (int)$item['stock_item_id']]);
                $cost = $stock->fetchColumn();
            }
        }
        $items[] = ['type'=>$type,'service_id'=>$type==='service'?(int)($item['service_id']??0):0,'stock_item_id'=>$type==='stock_part'?(int)($item['stock_item_id']??0):0,'external_part_id'=>0,'description'=>$item['item_name'],'quantity'=>(float)$item['quantity'],'unit_price'=>(float)$item['unit_price'],'cost_amount'=>$cost === null ? null : (float)$cost];
    }
    $external = $pdo->prepare('SELECT id,part_name,quantity,selling_price,unit_cost FROM expense_external_parts WHERE job_card_id=:job ORDER BY id');
    $external->execute(['job' => $jobId]);
    foreach ($external->fetchAll() as $part) {
        $items[] = ['type'=>'external_part','service_id'=>0,'stock_item_id'=>0,'external_part_id'=>(int)$part['id'],'description'=>$part['part_name'],'quantity'=>(float)$part['quantity'],'unit_price'=>(float)$part['selling_price'],'cost_amount'=>(float)$part['unit_cost']];
    }
    $job['invoice_items'] = $items;
    $job['invoice_charge'] = (float)($job['service_charge'] ?? 0);
    return $job;
}

function invoice_sync_completed_jobs(PDO $pdo): void
{
    $jobs = $pdo->query("SELECT id FROM job_cards WHERE status='completed' AND id NOT IN (SELECT job_card_id FROM invoices WHERE job_card_id IS NOT NULL)")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($jobs as $jobId) {
        $job = invoice_job_data((int)$jobId);
        if (!$job) continue;
        $subtotal = 0.0;
        foreach ($job['invoice_items'] as $item) $subtotal += round((float)$item['quantity'] * (float)$item['unit_price'], 2);
        $charge = invoice_money($job['invoice_charge']);
        $total = round($subtotal + $charge, 2);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO invoices (invoice_no,invoice_type,job_card_id,customer_id,vehicle_id,invoice_date,subtotal,special_service_charge,total_amount,balance_amount,payment_status,created_by) VALUES (:no,"job_card",:job,:customer,:vehicle,:date,:subtotal,:charge,:total,:total,"due",:user)')->execute(['no'=>'TMP-SYNC-'.bin2hex(random_bytes(4)),'job'=>$job['id'],'customer'=>$job['customer_id'],'vehicle'=>$job['vehicle_id'] ?: null,'date'=>date('Y-m-d', strtotime((string)$job['created_at'])),'subtotal'=>$subtotal,'charge'=>$charge,'total'=>$total,'user'=>$job['created_by'] ?: null]);
            $invoiceId = (int)$pdo->lastInsertId();
            $invoiceNo = 'INV-' . str_pad((string)$invoiceId, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE invoices SET invoice_no=:no WHERE id=:id')->execute(['no'=>$invoiceNo,'id'=>$invoiceId]);
            $insert = $pdo->prepare('INSERT INTO invoice_items (invoice_id,item_type,service_id,stock_item_id,external_part_id,description,quantity,unit_price,cost_amount,line_total) VALUES (:invoice,:type,:service,:stock,:external,:description,:quantity,:price,:cost,:total)');
            foreach ($job['invoice_items'] as $item) $insert->execute(['invoice'=>$invoiceId,'type'=>$item['type'],'service'=>$item['service_id'] ?: null,'stock'=>$item['stock_item_id'] ?: null,'external'=>$item['external_part_id'] ?: null,'description'=>$item['description'],'quantity'=>$item['quantity'],'price'=>$item['unit_price'],'cost'=>$item['cost_amount'],'total'=>round((float)$item['quantity'] * (float)$item['unit_price'], 2)]);
            $payments = $pdo->prepare('SELECT * FROM job_card_payments WHERE job_card_id=:job ORDER BY id');
            $payments->execute(['job'=>$job['id']]);
            $paid = 0.0;
            foreach ($payments->fetchAll() as $oldPayment) {
                $received = (float)($oldPayment['amount_received'] ?? $oldPayment['amount']);
                $applied = min(max(0, $total - $paid), max(0, (float)$oldPayment['amount'] ?: $received));
                if ($applied <= 0 && $received <= 0) continue;
                $paymentNo = 'LEGACY-' . $oldPayment['id'] . '-' . $invoiceId;
                $pdo->prepare('INSERT INTO invoice_payments (invoice_id,payment_no,job_card_id,customer_id,payment_date,payment_method,amount_received,amount_applied,change_given,created_by) VALUES (:invoice,:no,:job,:customer,:date,:method,:received,:applied,:change,:user)')->execute(['invoice'=>$invoiceId,'no'=>$paymentNo,'job'=>$job['id'],'customer'=>$job['customer_id'],'date'=>$oldPayment['paid_at'],'method'=>$oldPayment['payment_method'],'received'=>$received,'applied'=>$applied,'change'=>max(0, $received - $applied),'user'=>$oldPayment['created_by'] ?: null]);
                $paid += $applied;
            }
            $balance = max(0, round($total - $paid, 2));
            $pdo->prepare('UPDATE invoices SET paid_amount=:paid,balance_amount=:balance,payment_status=:status,payment_method=:method WHERE id=:id')->execute(['paid'=>$paid,'balance'=>$balance,'status'=>invoice_status($paid,$total),'method'=>$paid > 0 ? ($job['payment_method'] ?: null) : null,'id'=>$invoiceId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

function invoice_money(mixed $value): float
{
    return round(max(0, (float)$value), 2);
}

function invoice_payment_values(array $source, float $total, float $alreadyPaid = 0): array
{
    $method = in_array($source['payment_method'] ?? 'cash', ['cash','card','bank','cheque','other'], true) ? $source['payment_method'] : 'cash';
    $received = invoice_money($source['amount_received'] ?? 0);
    $due = max(0, round($total - $alreadyPaid, 2));
    if ($received > 0 && $method !== 'cash' && $received > $due + 0.009) throw new RuntimeException('Card, bank, cheque or other payment cannot exceed the outstanding balance.');
    $applied = min($received, $due);
    return ['method'=>$method,'received'=>$received,'applied'=>$applied,'change'=>$method==='cash'?max(0, round($received-$applied, 2)):0,'reference'=>trim((string)($source['payment_reference'] ?? ''))];
}

function invoice_insert_payment(PDO $pdo, array $invoice, array $payment): string
{
    if ($payment['received'] <= 0) return '';
    $paymentNo = 'PAY-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $pdo->prepare('INSERT INTO invoice_payments (invoice_id,payment_no,job_card_id,customer_id,payment_method,amount_received,amount_applied,change_given,payment_reference,created_by) VALUES (:invoice,:payment,:job,:customer,:method,:received,:applied,:change,:reference,:user)')->execute(['invoice'=>$invoice['id'],'payment'=>$paymentNo,'job'=>$invoice['job_card_id']?:null,'customer'=>$invoice['customer_id']?:null,'method'=>$payment['method'],'received'=>$payment['received'],'applied'=>$payment['applied'],'change'=>$payment['change'],'reference'=>$payment['reference']?:null,'user'=>current_user()['id']??null]);
    return $paymentNo;
}

function invoice_create(array $source, string $saveMode, array &$errors): ?int
{
    $errors = [];
    $type = ($source['invoice_type'] ?? 'quick') === 'job_card' ? 'job_card' : 'quick';
    $jobId = max(0, (int)($source['job_card_id'] ?? 0));
    $job = $type === 'job_card' ? invoice_job_data($jobId) : null;
    if ($type === 'job_card' && !$job) { $errors[] = 'The selected job card was not found.'; return null; }
    if ($type === 'job_card') {
        $existing = database()->prepare('SELECT id FROM invoices WHERE job_card_id=:job LIMIT 1');
        $existing->execute(['job' => $jobId]);
        if ($existing->fetchColumn()) { $errors[] = 'A final invoice already exists for this job card.'; return null; }
        $customerId = (int)$job['customer_id'];
        $vehicleId = (int)($job['vehicle_id'] ?? 0);
        $vehicleDisplayNumber = $job['vehicle_number'] ?? null;
        $items = $job['invoice_items'];
        $charge = invoice_money($job['invoice_charge']);
    } else {
        $customerId = max(0, (int)($source['customer_id'] ?? 0));
        $vehicleId = max(0, (int)($source['vehicle_id'] ?? 0));
        $vehicleDisplayNumber = trim((string)($source['vehicle_number'] ?? ''));
        $items = [];
        if ($customerId > 0 && $vehicleId > 0) {
            $check = database()->prepare('SELECT id FROM vehicles WHERE id=:vehicle AND customer_id=:customer');
            $check->execute(['vehicle' => $vehicleId, 'customer' => $customerId]);
            if (!$check->fetchColumn()) { $errors[] = 'The selected vehicle does not belong to the selected customer.'; return null; }
        }
        if ($vehicleId > 0) $vehicleDisplayNumber = null;
        $charge = invoice_money($source['special_service_charge'] ?? 0);
        foreach ((array)($source['items'] ?? []) as $row) {
            $itemType = in_array($row['type'] ?? '', ['service','stock_part','custom'], true) ? $row['type'] : 'custom';
            $quantity = invoice_money($row['quantity'] ?? 0);
            if ($quantity <= 0) continue;
            $serviceId = max(0, (int)($row['service_id'] ?? 0));
            $stockId = max(0, (int)($row['stock_item_id'] ?? 0));
            $description = trim((string)($row['description'] ?? ''));
            $price = 0.0;
            $cost = null;
            if ($itemType === 'service') {
                $statement = database()->prepare('SELECT id,service_name,price FROM services WHERE id=:id AND status="active"');
                $statement->execute(['id' => $serviceId]);
                $service = $statement->fetch();
                if (!$service) { $errors[] = 'One selected service is no longer active.'; continue; }
                $description = $service['service_name'];
                $price = (float)$service['price'];
            } elseif ($itemType === 'stock_part') {
                $statement = database()->prepare('SELECT id,part_name,selling_price,buying_price,stock_qty FROM stock_items WHERE id=:id AND status="active"');
                $statement->execute(['id' => $stockId]);
                $stock = $statement->fetch();
                if (!$stock) { $errors[] = 'One selected stock part was not found.'; continue; }
                $description = $stock['part_name'];
                $price = (float)$stock['selling_price'];
                $cost = (float)$stock['buying_price'];
            } else {
                $price = invoice_money($row['unit_price'] ?? 0);
                if ($description === '') { $errors[] = 'Custom item description is required.'; continue; }
            }
            $items[] = [
                'type' => $itemType,
                'service_id' => $serviceId,
                'stock_item_id' => $stockId,
                'external_part_id' => 0,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => invoice_money($price),
                'cost_amount' => $cost,
            ];
        }
        if ($errors) return null;
    }
    if (!$items && $charge <= 0) { $errors[] = 'Add at least one invoice item or special service charge.'; return null; }
    $subtotal = 0.0;
    foreach ($items as &$item) {
        $item['line_total'] = round($item['quantity'] * $item['unit_price'], 2);
        $subtotal += $item['line_total'];
    }
    unset($item);
    $discountType = in_array($source['discount_type'] ?? '', ['fixed','percentage'], true) ? $source['discount_type'] : null;
    $discountValue = invoice_money($source['discount_value'] ?? 0); $base = $subtotal + $charge;
    $discountAmount = $discountType === 'percentage' ? min($base, round($base*$discountValue/100,2)) : min($base, $discountValue);
    $total = max(0, round($base-$discountAmount,2));
    try { $payment = invoice_payment_values($source, $total); } catch (Throwable $e) { $errors[] = $e->getMessage(); return null; }
    $pdo = database();
    try {
        ensure_stock_tables();
        $pdo->beginTransaction();
        $placeholder = 'TMP-' . bin2hex(random_bytes(5));
        $pdo->prepare('INSERT INTO invoices (invoice_no,invoice_type,job_card_id,customer_id,vehicle_id,vehicle_display_number,invoice_date,subtotal,special_service_charge,discount_type,discount_value,discount_amount,total_amount,paid_amount,balance_amount,payment_status,payment_method,notes,created_by) VALUES (:no,:type,:job,:customer,:vehicle,:vehicle_number,:date,:subtotal,:charge,:dtype,:dvalue,:damount,:total,0,:total,:status,:method,:notes,:user)')->execute([
            'no'=>$placeholder, 'type'=>$type, 'job'=>$type === 'job_card' ? $jobId : null, 'customer'=>$customerId ?: null, 'vehicle'=>$vehicleId ?: null, 'vehicle_number'=>$vehicleDisplayNumber ?: null,
            'date'=>trim((string)($source['invoice_date'] ?? date('Y-m-d'))), 'subtotal'=>$subtotal, 'charge'=>$charge, 'dtype'=>$discountType,
            'dvalue'=>$discountValue, 'damount'=>$discountAmount, 'total'=>$total, 'status'=>invoice_status(0, $total),
            'method'=>$payment['received'] > 0 ? $payment['method'] : null, 'notes'=>trim((string)($source['notes'] ?? '')) ?: null, 'user'=>current_user()['id'] ?? null,
        ]);
        $invoiceId = (int)$pdo->lastInsertId();
        $invoiceNo = 'INV-' . str_pad((string)$invoiceId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE invoices SET invoice_no=:no WHERE id=:id')->execute(['no'=>$invoiceNo, 'id'=>$invoiceId]);
        $insertItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id,item_type,service_id,stock_item_id,external_part_id,description,quantity,unit_price,cost_amount,line_total) VALUES (:invoice,:type,:service,:stock,:external,:description,:quantity,:price,:cost,:total)');
        $stockTotals = [];
        foreach ($items as $item) {
            $insertItem->execute(['invoice'=>$invoiceId, 'type'=>$item['type'], 'service'=>$item['service_id'] ?: null, 'stock'=>$item['stock_item_id'] ?: null, 'external'=>$item['external_part_id'] ?: null, 'description'=>$item['description'], 'quantity'=>$item['quantity'], 'price'=>$item['unit_price'], 'cost'=>$item['cost_amount'], 'total'=>$item['line_total']]);
            if ($item['type'] === 'stock_part') $stockTotals[$item['stock_item_id']] = ($stockTotals[$item['stock_item_id']] ?? 0) + $item['quantity'];
        }
        if ($type === 'quick') {
            $profit = $charge;
            foreach ($items as $item) $profit += max(0, ((float)$item['unit_price'] - (float)($item['cost_amount'] ?? 0)) * (float)$item['quantity']);
            if ($profit > 0) {
                $pdo->prepare('INSERT INTO other_income (income_date,title,category,amount,payment_method,reference_no,notes,created_by) VALUES (CURDATE(),:title,:category,:amount,"other",:reference,:notes,:user)')->execute(['title'=>'Invoice Profit - '.$invoiceNo,'category'=>'Invoice Profit','amount'=>round($profit,2),'reference'=>$invoiceNo,'notes'=>'Estimated profit recorded from quick invoice '.$invoiceNo.'.','user'=>current_user()['id']??null]);
            }
        }
        if ($type === 'quick') {
            foreach ($stockTotals as $stockId => $quantity) {
                $stockStatement = $pdo->prepare('SELECT stock_qty FROM stock_items WHERE id=:id FOR UPDATE');
                $stockStatement->execute(['id'=>$stockId]);
                if ($quantity > (float)$stockStatement->fetchColumn() + 0.00001) throw new RuntimeException('Insufficient stock for one or more selected parts.');
            $userId = isset(current_user()['id']) ? (int) current_user()['id'] : null;
            consume_stock_fifo((int)$stockId, (float)$quantity, 'sale', $invoiceId, $userId, 'Quick invoice ' . $invoiceNo, $pdo);
            }
        }
        $invoice = ['id'=>$invoiceId, 'job_card_id'=>$type === 'job_card' ? $jobId : null, 'customer_id'=>$customerId ?: null];
        $paid = $payment['applied']; $balance = max(0, round($total - $paid, 2));
        $pdo->prepare('UPDATE invoices SET paid_amount=:paid,balance_amount=:balance,payment_status=:status WHERE id=:id')->execute(['paid'=>$paid, 'balance'=>$balance, 'status'=>invoice_status($paid, $total), 'id'=>$invoiceId]);
        $paymentNo = invoice_insert_payment($pdo, $invoice, $payment);
        if ($payment['applied'] > 0) {
            $incomeMethod = $payment['method'] === 'cheque' ? 'other' : $payment['method'];
            $pdo->prepare('INSERT INTO other_income (income_date,title,category,amount,payment_method,reference_no,notes,created_by) VALUES (CURDATE(),:title,:category,:amount,:method,:reference,:notes,:user)')->execute(['title'=>'Invoice Payment - '.$invoiceNo,'category'=>'Invoice Payment','amount'=>$payment['applied'],'method'=>$incomeMethod,'reference'=>$paymentNo,'notes'=>'Payment received for invoice '.$invoiceNo.'.','user'=>current_user()['id']??null]);
        }
        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Invoice could not be saved. Please try again.'; return null; }
}

function handle_invoice_request(string $section): void
{
    invoice_ensure_tables();
    $id=max(0,(int)($_GET['id']??$_POST['invoice_id']??0));
    if ($section==='invoices' || $section==='invoices-mine' || $section==='invoices-quick') {
        $errors = [];
        if (in_array($section, ['invoices', 'invoices-mine', 'invoices-quick'], true) && $_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            $invoiceId=invoice_create($_POST,($_POST['save_mode']??'save'),$errors);
            if($invoiceId){flash('success','Invoice saved successfully.');redirect('index.php?page=admin&section='.($section==='invoices-mine'?'invoices-mine':'invoices'));}
        }
        $filters=['search'=>trim((string)($_GET['search']??'')),'type'=>$_GET['type']??'','status'=>$_GET['status']??'','date_from'=>trim((string)($_GET['date_from']??'')),'date_to'=>trim((string)($_GET['date_to']??'')),'created_by'=>((current_user()['role']??'')==='cashier'||$section==='invoices-mine')?(int)(current_user()['id']??0):0];
        $rows=invoice_rows($filters);
        $services=database()->query('SELECT id,service_name,price FROM services WHERE status="active" ORDER BY service_name')->fetchAll();
        $parts=database()->query('SELECT id,part_code,part_name,brand,selling_price,stock_qty FROM stock_items WHERE status="active" ORDER BY part_name')->fetchAll();
        $customers=database()->query('SELECT id,name,contact_number FROM customers WHERE status="active" ORDER BY name')->fetchAll();
        $vehicles=database()->query('SELECT id,customer_id,vehicle_number,make,model FROM vehicles WHERE status="active" ORDER BY vehicle_number')->fetchAll();
        $title='Invoices';$sectionForHeader=$section;
        require __DIR__.'/../includes/header.php';
        if($section==='invoices-quick') require __DIR__.'/../views/invoice-form.php'; else require __DIR__.'/../views/invoices.php';
        require __DIR__.'/../includes/footer.php';return;
    }
    if ($section==='invoices-jobcard') { if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$errors=[];$invoiceId=invoice_create($_POST,($_POST['save_mode']??'save'),$errors);if($invoiceId){flash('success','Job card invoice saved successfully.');redirect('index.php?page=admin&section='.($_POST['save_mode']==='print'?'invoices-thermal':'invoices-view').'&id='.$invoiceId);}}$job=invoice_job_data($id);if(!$job){http_response_code(404);exit('Job card not found.');}$existing=database()->prepare('SELECT id FROM invoices WHERE job_card_id=:id');$existing->execute(['id'=>$id]);if($existing->fetchColumn()){redirect('index.php?page=admin&section=invoices');}$title='Create Job Card Invoice';$sectionForHeader='invoices-jobcard';require __DIR__.'/../includes/header.php';require __DIR__.'/../views/invoice-jobcard-form.php';require __DIR__.'/../includes/footer.php';return; }
    if ($section==='invoices-view' || $section==='invoices-thermal') { $invoice=invoice_record($id);if(!$invoice){http_response_code(404);exit('Invoice not found.');}if(((current_user()['role']??'')==='cashier')&&(int)$invoice['created_by']!==(int)(current_user()['id']??0)){http_response_code(403);exit('You can only access invoices created by you.');}$title='Invoice '.$invoice['invoice_no'];$sectionForHeader='invoices';if($section==='invoices-thermal'){require __DIR__.'/../views/invoice-thermal.php';return;}require __DIR__.'/../includes/header.php';require __DIR__.'/../views/invoice-view.php';require __DIR__.'/../includes/footer.php';return; }
    if ($section==='invoices-payment' && $_SERVER['REQUEST_METHOD']==='POST') { verify_csrf();$invoice=invoice_record($id);if(!$invoice){http_response_code(404);exit('Invoice not found.');}$errors=[];try{$payment=invoice_payment_values($_POST,(float)$invoice['total_amount'],(float)$invoice['paid_amount']);if($payment['received']<=0)throw new RuntimeException('Enter a payment amount.');$pdo=database();$pdo->beginTransaction();$paymentNo=invoice_insert_payment($pdo,$invoice,$payment);$paymentId=(int)$pdo->lastInsertId();$paid=(float)$invoice['paid_amount']+$payment['applied'];$balance=max(0,round((float)$invoice['total_amount']-$paid,2));$pdo->prepare('UPDATE invoices SET paid_amount=:paid,balance_amount=:balance,payment_status=:status,payment_method=:method WHERE id=:id')->execute(['paid'=>$paid,'balance'=>$balance,'status'=>invoice_status($paid,(float)$invoice['total_amount']),'method'=>$payment['method'],'id'=>$id]);$incomeMethod=$payment['method']==='cheque'?'other':$payment['method'];$pdo->prepare('INSERT INTO other_income (income_date,title,category,amount,payment_method,reference_no,notes,created_by) VALUES (CURDATE(),:title,:category,:amount,:method,:reference,:notes,:user)')->execute(['title'=>'Invoice Payment - '.$invoice['invoice_no'],'category'=>'Invoice Payment','amount'=>$payment['applied'],'method'=>$incomeMethod,'reference'=>$paymentNo,'notes'=>'Payment received for invoice '.$invoice['invoice_no'].'.','user'=>current_user()['id']??null]);$pdo->commit();flash('success','Payment recorded and receipt created.');redirect('index.php?page=admin&section=invoices-receipt&id='.$paymentId);}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());redirect('index.php?page=admin&section=invoices-view&id='.$id);} }
    if ($section==='invoices-receipt') {
        $statement = database()->prepare('SELECT p.*,i.invoice_no,i.invoice_type,i.invoice_date,i.total_amount,i.paid_amount,i.balance_amount,c.name AS customer_name,c.contact_number,COALESCE(v.vehicle_number,i.vehicle_display_number) AS vehicle_number,v.make,v.model FROM invoice_payments p JOIN invoices i ON i.id=p.invoice_id LEFT JOIN customers c ON c.id=p.customer_id LEFT JOIN vehicles v ON v.id=p.vehicle_id WHERE p.id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $payment = $statement->fetch();
        if (!$payment) { http_response_code(404); exit('Payment receipt not found.'); }
        $items = database()->prepare('SELECT description,quantity,unit_price,line_total FROM invoice_items WHERE invoice_id=:invoice ORDER BY id');
        $items->execute(['invoice' => (int)$payment['invoice_id']]);
        $receiptItems = $items->fetchAll();
        $receiptSettings = receipt_settings();
        $title = 'Payment Receipt ' . $payment['payment_no'];
        $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php';
        require __DIR__ . '/../views/invoice-receipt.php';
        require __DIR__ . '/../includes/footer.php';
        return;
    }
    redirect('index.php?page=admin&section=invoices');
}
