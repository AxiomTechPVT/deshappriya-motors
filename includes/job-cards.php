<?php

declare(strict_types=1);

require_once __DIR__ . '/price-reductions.php';
require_once __DIR__ . '/job-discounts.php';

function ensure_job_card_tables(): void
{
    static $ready = false;
    if ($ready) return;
    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_cards (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,job_card_no VARCHAR(30) NOT NULL,customer_id BIGINT UNSIGNED NOT NULL,vehicle_id BIGINT UNSIGNED NULL,bay_name VARCHAR(80) NULL,mechanic_id BIGINT UNSIGNED NULL,complaint TEXT NULL,requested_work TEXT NULL,notes TEXT NULL,expected_delivery_date DATE NULL,priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',status ENUM('pending','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,discount DECIMAL(12,2) NOT NULL DEFAULT 0,total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,payment_method ENUM('cash','card','bank','other') NULL,created_by BIGINT UNSIGNED NULL,started_at DATETIME NULL,completed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY job_cards_no_unique(job_card_no),KEY job_cards_customer_index(customer_id),KEY job_cards_vehicle_index(vehicle_id),KEY job_cards_status_index(status),CONSTRAINT job_cards_customer_fk FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,CONSTRAINT job_cards_vehicle_fk FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,CONSTRAINT job_cards_mechanic_fk FOREIGN KEY(mechanic_id) REFERENCES employees(id) ON DELETE SET NULL,CONSTRAINT job_cards_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_card_service_charges (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,job_card_id BIGINT UNSIGNED NOT NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY job_card_service_charge_unique(job_card_id),CONSTRAINT job_card_service_charge_card_fk FOREIGN KEY(job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,CONSTRAINT job_card_service_charge_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_card_items (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,job_card_id BIGINT UNSIGNED NOT NULL,item_type ENUM('part','service','manual') NOT NULL DEFAULT 'manual',stock_item_id BIGINT UNSIGNED NULL,service_id BIGINT UNSIGNED NULL,item_name VARCHAR(190) NOT NULL,item_code VARCHAR(80) NULL,quantity DECIMAL(12,2) NOT NULL DEFAULT 1,unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,discount DECIMAL(12,2) NOT NULL DEFAULT 0,amount DECIMAL(12,2) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY job_card_items_card_index(job_card_id),CONSTRAINT job_card_items_card_fk FOREIGN KEY(job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,CONSTRAINT job_card_items_stock_fk FOREIGN KEY(stock_item_id) REFERENCES stock_items(id) ON DELETE SET NULL,CONSTRAINT job_card_items_service_fk FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$pdo->query("SHOW COLUMNS FROM job_cards LIKE 'service_charge'")->fetch()) $pdo->exec("ALTER TABLE job_cards ADD service_charge DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_card_payments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,job_card_id BIGINT UNSIGNED NOT NULL,receipt_no VARCHAR(40) NOT NULL,amount DECIMAL(12,2) NOT NULL,payment_method ENUM('cash','card','bank','other') NOT NULL DEFAULT 'cash',paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_by BIGINT UNSIGNED NULL,PRIMARY KEY(id),UNIQUE KEY job_card_payments_receipt_unique(receipt_no),KEY job_card_payments_card_index(job_card_id),CONSTRAINT job_card_payments_card_fk FOREIGN KEY(job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,CONSTRAINT job_card_payments_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_card_performance (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,job_card_id BIGINT UNSIGNED NOT NULL,mechanic_id BIGINT UNSIGNED NULL,start_time DATETIME NOT NULL,end_time DATETIME NOT NULL,duration_minutes DECIMAL(10,2) NOT NULL DEFAULT 0,recorded_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY job_card_performance_card_unique(job_card_id),KEY job_card_performance_mechanic_index(mechanic_id),CONSTRAINT job_card_performance_card_fk FOREIGN KEY(job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,CONSTRAINT job_card_performance_mechanic_fk FOREIGN KEY(mechanic_id) REFERENCES employees(id) ON DELETE SET NULL,CONSTRAINT job_card_performance_user_fk FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$pdo->query("SHOW COLUMNS FROM job_card_payments LIKE 'amount_received'")->fetch()) $pdo->exec("ALTER TABLE job_card_payments ADD amount_received DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount");
    if (!$pdo->query("SHOW COLUMNS FROM job_card_payments LIKE 'change_amount'")->fetch()) $pdo->exec("ALTER TABLE job_card_payments ADD change_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_received");
    $sellingPriceColumn = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expense_external_parts' AND COLUMN_NAME='selling_price'")->fetchColumn();
    if (!(int)$sellingPriceColumn) $pdo->exec("ALTER TABLE expense_external_parts ADD COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_cost");
    foreach (['discount_type' => "VARCHAR(20) NULL", 'discount_value' => "DECIMAL(12,2) NULL"] as $column => $definition) {
        if (!$pdo->query("SHOW COLUMNS FROM job_cards LIKE '{$column}'")->fetch()) $pdo->exec("ALTER TABLE job_cards ADD {$column} {$definition}");
    }
    ensure_price_snapshot_column('job_card_items');
    $ready = true;
}

function job_card_input(?array $source = null): array
{
    $source ??= $_POST;
    $priority = $source['priority'] ?? 'normal';
    return [
        'customer_id' => max(0, (int)($source['customer_id'] ?? 0)),
        'vehicle_id' => max(0, (int)($source['vehicle_id'] ?? 0)),
        'primary_service_id' => max(0, (int)($source['primary_service_id'] ?? 0)),
        'primary_service_name' => trim((string)($source['primary_service_name'] ?? '')),
        'primary_service_price' => max(0, (float)($source['primary_service_price'] ?? 0)),
        'bay_name' => trim((string)($source['bay_name'] ?? '')),
        'mechanic_id' => max(0, (int)($source['mechanic_id'] ?? 0)),
        'complaint' => trim((string)($source['complaint'] ?? '')),
        'requested_work' => trim((string)($source['requested_work'] ?? '')),
        'notes' => trim((string)($source['notes'] ?? '')),
        'expected_delivery_date' => trim((string)($source['expected_delivery_date'] ?? '')),
        'priority' => in_array($priority, ['low','normal','high','urgent'], true) ? $priority : 'normal',
        'items' => (array)($source['items'] ?? []),
    ];
}

function job_card_validate(array $input, bool $starting = false): array
{
    $errors = [];
    if ($input['customer_id'] < 1) $errors[] = 'Select a customer.';
    if ($input['vehicle_id'] < 1) $errors[] = 'Select a vehicle.';
    if ($input['complaint'] === '') $errors[] = 'Customer complaint is required.';
    if ($starting && $input['bay_name'] === '') $errors[] = 'Select a working bay before starting the job.';
    if ($starting && $input['mechanic_id'] < 1) $errors[] = 'Select a mechanic before starting the job.';
    foreach ($input['items'] as $n => $item) {
        if (trim((string)($item['name'] ?? '')) === '') continue;
        if ((float)($item['quantity'] ?? 0) <= 0) $errors[] = 'Item ' . ($n + 1) . ' quantity must be greater than zero.';
        if ((float)($item['unit_price'] ?? 0) < 0) $errors[] = 'Item ' . ($n + 1) . ' price cannot be negative.';
    }
    return $errors;
}

function job_card_no(int $id): string
{
    return 'JC-' . date('ym') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function job_card_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
}

function available_mechanics(): array
{
    $statement = database()->prepare("SELECT DISTINCT e.id,e.name,e.role_position
        FROM employees e
        JOIN employee_attendance a ON a.employee_id=e.id
            AND a.attendance_date=:today
            AND a.check_in IS NOT NULL
        WHERE e.status='active'
        ORDER BY e.name");
    $statement->execute(['today' => job_card_today()]);
    return $statement->fetchAll();
}

function mechanic_is_available_today(int $mechanicId): bool
{
    if ($mechanicId < 1) return false;
    $statement = database()->prepare("SELECT 1
        FROM employees e
        JOIN employee_attendance a ON a.employee_id=e.id
            AND a.attendance_date=:today
            AND a.check_in IS NOT NULL
        WHERE e.id=:id AND e.status='active'
        LIMIT 1");
    $statement->execute(['id' => $mechanicId, 'today' => job_card_today()]);
    return (bool)$statement->fetchColumn();
}

function job_card_lookup(): array
{
    return [
        'customers' => database()->query("SELECT id,name,contact_number,email,address FROM customers WHERE status='active' ORDER BY name")->fetchAll(),
        'vehicles' => database()->query("SELECT v.*,c.name AS customer_name FROM vehicles v JOIN customers c ON c.id=v.customer_id WHERE v.status='active' ORDER BY v.vehicle_number")->fetchAll(),
        'employees' => available_mechanics(),
        'bays' => database()->query("SELECT bay_name FROM bays WHERE status='active' ORDER BY bay_name")->fetchAll(),
        'services' => database()->query("SELECT id,service_code,service_name,description,price FROM services WHERE status='active' ORDER BY service_name")->fetchAll(),
        'parts' => database()->query("SELECT id,part_code,part_name,stock_qty,selling_price FROM stock_items WHERE status='active' ORDER BY part_name")->fetchAll(),
    ];
}

function job_card_rows(string $status, array $filters = [], bool $count = false): array|int
{
    $where = ['j.status=:status']; $params = ['status' => $status];
    if (($filters['search'] ?? '') !== '') { $where[] = '(j.job_card_no LIKE :search OR v.vehicle_number LIKE :search OR c.name LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
    if (($filters['mechanic_id'] ?? 0) > 0) { $where[] = 'j.mechanic_id=:mechanic'; $params['mechanic'] = $filters['mechanic_id']; }
    if (($filters['bay_name'] ?? '') !== '') { $where[] = 'j.bay_name=:bay'; $params['bay'] = $filters['bay_name']; }
    if (($filters['priority'] ?? '') !== '') { $where[] = 'j.priority=:priority'; $params['priority'] = $filters['priority']; }
    if (($filters['date_from'] ?? '') !== '') { $where[] = 'DATE(j.created_at)>=:date_from'; $params['date_from'] = $filters['date_from']; }
    if (($filters['date_to'] ?? '') !== '') { $where[] = 'DATE(j.created_at)<=:date_to'; $params['date_to'] = $filters['date_to']; }
    $select = $count ? 'COUNT(*)' : "j.*,c.name AS customer_name,c.contact_number,v.vehicle_number,v.make,v.model,v.vehicle_type,v.colour,v.year,v.current_mileage,e.name AS mechanic_name,(SELECT COUNT(*) FROM job_card_items ji WHERE ji.job_card_id=j.id AND ji.item_type <> 'service') AS item_count";
    $sql = "SELECT {$select} FROM job_cards j JOIN customers c ON c.id=j.customer_id LEFT JOIN vehicles v ON v.id=j.vehicle_id LEFT JOIN employees e ON e.id=j.mechanic_id WHERE ".implode(' AND ',$where);
    if (!$count) {
        $sql .= ' ORDER BY j.id DESC';
        if (isset($filters['per_page'])) {
            $limit = max(1, (int)$filters['per_page']);
            $offset = (max(1, (int)($filters['page'] ?? 1)) - 1) * $limit;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
    }
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $count ? (int)$statement->fetchColumn() : $statement->fetchAll();
}

function job_card_record(int $id): ?array
{
    $statement = database()->prepare("SELECT j.*,c.name AS customer_name,c.contact_number,c.email,c.address,v.vehicle_number,v.make,v.model,v.vehicle_type,v.colour,v.year,v.current_mileage,e.name AS mechanic_name FROM job_cards j JOIN customers c ON c.id=j.customer_id LEFT JOIN vehicles v ON v.id=j.vehicle_id LEFT JOIN employees e ON e.id=j.mechanic_id WHERE j.id=:id LIMIT 1");
    $statement->execute(['id' => $id]);
    $record = $statement->fetch();
    if (!$record) return null;
    if (in_array($record['status'], ['pending', 'ongoing'], true) && (float)$record['service_charge'] <= 0) {
        $serviceCandidate = trim((string)($record['requested_work'] ?: $record['complaint'] ?: ''));
        if ($serviceCandidate !== '') {
            $serviceLookup = database()->prepare("SELECT price FROM services WHERE status='active' AND LOWER(service_name)=LOWER(:name) LIMIT 1");
            $serviceLookup->execute(['name' => $serviceCandidate]);
            $savedServicePrice = $serviceLookup->fetchColumn();
            if ($savedServicePrice !== false) {
                $serviceCharge = (float)$savedServicePrice;
                $record['service_charge'] = $serviceCharge;
                $record['total_amount'] = (float)$record['subtotal'] + $serviceCharge;
                $record['balance_amount'] = max(0, $record['total_amount'] - (float)$record['paid_amount']);
                database()->prepare('UPDATE job_cards SET service_charge=:charge,total_amount=:total,balance_amount=:balance WHERE id=:id')->execute(['charge'=>$serviceCharge,'total'=>$record['total_amount'],'balance'=>$record['balance_amount'],'id'=>$id]);
                database()->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user) ON DUPLICATE KEY UPDATE amount=VALUES(amount)')->execute(['job'=>$id,'amount'=>$serviceCharge,'user'=>current_user()['id'] ?? null]);
            }
        }
    }
    $items = database()->prepare('SELECT * FROM job_card_items WHERE job_card_id=:id AND item_type <> "service" ORDER BY id');
    $items->execute(['id' => $id]);
    $record['items'] = $items->fetchAll();
    return $record;
}

function add_external_part_expense(array $job, string $name, float $quantity, float $buyingPrice, float $sellingPrice): void
{
    $pdo = database();
    $expenseNo = 'EXP-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $totalCost = $quantity * $buyingPrice;
    $expense = $pdo->prepare('INSERT INTO expenses (expense_no,expense_date,category,expense_type,description,job_card_id,vehicle_id,payment_terms,payment_method,subtotal,total_amount,paid_amount,balance_amount,payment_date,status,created_by) VALUES (:no,CURDATE(),:category,"external_part",:description,:job,:vehicle,"cash","cash",:subtotal,:total,:paid,:balance,CURDATE(),:status,:user)');
    $expense->execute(['no'=>$expenseNo,'category'=>'External Spare Part Purchase','description'=>'External part: ' . $name,'job'=>$job['id'],'vehicle'=>$job['vehicle_id'] ?: null,'subtotal'=>$totalCost,'total'=>$totalCost,'paid'=>$totalCost,'balance'=>0,'status'=>'paid','user'=>current_user()['id'] ?? null]);
    $expenseId = (int)$pdo->lastInsertId();
    $part = $pdo->prepare('INSERT INTO expense_external_parts (expense_id,job_card_id,vehicle_id,part_name,quantity,unit_cost,selling_price,total_cost) VALUES (:expense,:job,:vehicle,:name,:quantity,:buying,:selling,:total)');
    $part->execute(['expense'=>$expenseId,'job'=>$job['id'],'vehicle'=>$job['vehicle_id'] ?: null,'name'=>$name,'quantity'=>$quantity,'buying'=>$buyingPrice,'selling'=>$sellingPrice,'total'=>$totalCost]);
    // The completed job invoice accounts for this part's revenue and cost.
}

function reserve_job_card_stock(PDO $pdo, int $stockItemId, float $quantity, int $itemId, array $job): void
{
    if ($stockItemId < 1 || $quantity <= 0) return;
    consume_stock_fifo(
        $stockItemId,
        $quantity,
        'job_card_item',
        $itemId,
        isset(current_user()['id']) ? (int)current_user()['id'] : null,
        'Reserved for job card ' . ($job['job_card_no'] ?? $job['id']),
        $pdo
    );
}

function sync_job_card_stock_reservations(array $job): void
{
    if (($job['status'] ?? '') !== 'ongoing') return;
    $pdo = database();
    $items = $pdo->prepare("SELECT id,stock_item_id,quantity FROM job_card_items WHERE job_card_id=:job AND item_type='part' AND stock_item_id IS NOT NULL ORDER BY id");
    $items->execute(['job' => (int)$job['id']]);
    foreach ($items as $item) {
        $check = $pdo->prepare("SELECT 1 FROM stock_movements WHERE reference_type='job_card_item' AND reference_id=:item LIMIT 1");
        $check->execute(['item' => (int)$item['id']]);
        if ($check->fetchColumn()) continue;
        try {
            $pdo->beginTransaction();
            reserve_job_card_stock($pdo, (int)$item['stock_item_id'], (float)$item['quantity'], (int)$item['id'], $job);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            break;
        }
    }
}

function release_job_card_stock(PDO $pdo, int $itemId, int $stockItemId, float $quantity, array $job): void
{
    if ($stockItemId < 1 || $quantity <= 0) return;
    $movement = $pdo->prepare("SELECT id FROM stock_movements WHERE reference_type='job_card_item' AND reference_id=:item LIMIT 1");
    $movement->execute(['item' => $itemId]);
    if (!$movement->fetchColumn()) return;

    $consumptions = $pdo->prepare("SELECT stock_batch_id,quantity FROM stock_batch_consumptions WHERE reference_type='job_card_item' AND reference_id=:item FOR UPDATE");
    $consumptions->execute(['item' => $itemId]);
    $restoreBatch = $pdo->prepare('UPDATE stock_batches SET quantity_remaining=quantity_remaining+:quantity WHERE id=:id');
    foreach ($consumptions as $consumption) {
        $restoreBatch->execute(['quantity' => (float)$consumption['quantity'], 'id' => (int)$consumption['stock_batch_id']]);
    }

    $stock = $pdo->prepare('SELECT stock_qty FROM stock_items WHERE id=:id FOR UPDATE');
    $stock->execute(['id' => $stockItemId]);
    $before = (float)$stock->fetchColumn();
    $after = $before + $quantity;
    $pdo->prepare('UPDATE stock_items SET stock_qty=:quantity WHERE id=:id')->execute(['quantity' => $after, 'id' => $stockItemId]);
    $cost = $pdo->prepare('SELECT COALESCE(SUM(total_cost),0) / NULLIF(SUM(quantity),0) FROM stock_batch_consumptions WHERE reference_type=\'job_card_item\' AND reference_id=:item');
    $cost->execute(['item' => $itemId]);
    $pdo->prepare("INSERT INTO stock_movements (stock_item_id,movement_type,quantity,quantity_before,quantity_after,unit_cost,reference_type,reference_id,notes,created_by) VALUES (:item,'return',:quantity,:before,:after,:cost,'job_card_item',:reference,:notes,:user)")->execute([
        'item' => $stockItemId,
        'quantity' => $quantity,
        'before' => $before,
        'after' => $after,
        'cost' => (float)($cost->fetchColumn() ?: 0),
        'reference' => $itemId,
        'notes' => 'Released from job card ' . ($job['job_card_no'] ?? $job['id']),
        'user' => current_user()['id'] ?? null,
    ]);
    $pdo->prepare("DELETE FROM stock_batch_consumptions WHERE reference_type='job_card_item' AND reference_id=:item")->execute(['item' => $itemId]);
    $pdo->prepare("DELETE FROM stock_movements WHERE reference_type='job_card_item' AND reference_id=:item AND movement_type='job_usage'")->execute(['item' => $itemId]);
}

function handle_job_card_request(string $section): void
{
    ensure_job_card_tables();
    ensure_stock_tables();
    $id = max(0, (int)($_GET['id'] ?? $_POST['job_card_id'] ?? 0));
    $errors = [];
    if ($section === 'jobcards-search') {
        header('Content-Type: application/json; charset=utf-8');
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') { echo json_encode([]); return; }
        $statement = database()->prepare("SELECT v.id,v.vehicle_number,v.make,v.model,v.vehicle_type,c.id AS customer_id,c.name AS customer_name,c.contact_number FROM vehicles v JOIN customers c ON c.id=v.customer_id WHERE v.status='active' AND (v.vehicle_number LIKE :query OR c.name LIKE :query OR c.contact_number LIKE :query) ORDER BY v.vehicle_number LIMIT 10");
        $statement->execute(['query' => '%' . $query . '%']);
        echo json_encode($statement->fetchAll());
        return;
    }
    if ($section === 'jobcards-services') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(database()->query("SELECT id,service_code,service_name,description,price FROM services WHERE status='active' ORDER BY service_name")->fetchAll());
        return;
    }
    if ($section === 'jobcards-start-options') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['bays' => database()->query("SELECT bay_name FROM bays WHERE status='active' ORDER BY bay_name")->fetchAll(), 'mechanics' => available_mechanics()]);
        return;
    }
    if ($section === 'jobcards-payment-summary') {
        header('Content-Type: application/json; charset=utf-8');
        $job = job_card_record($id);
        if (!$job) { http_response_code(404); echo json_encode(['error'=>'Job card not found.']); return; }
        $payments = database()->prepare('SELECT receipt_no,amount,payment_method,paid_at FROM job_card_payments WHERE job_card_id=:id ORDER BY id DESC');
        $payments->execute(['id'=>$id]);
        $performance = database()->prepare('SELECT start_time,end_time,duration_minutes FROM job_card_performance WHERE job_card_id=:id LIMIT 1');
        $performance->execute(['id'=>$id]);
        $serviceCharge = database()->prepare('SELECT COALESCE((SELECT amount FROM job_card_service_charges WHERE job_card_id=:id), :fallback)');
        $serviceCharge->execute(['id'=>$id,'fallback'=>(float)($job['service_charge'] ?? 0)]);
        echo json_encode(['service_charge'=>(float)$serviceCharge->fetchColumn(),'subtotal'=>(float)$job['subtotal'],'discount'=>(float)($job['discount']??0),'discount_type'=>$job['discount_type']??null,'discount_value'=>$job['discount_value']??null,'status'=>$job['status'],'total'=>(float)$job['total_amount'],'paid'=>(float)$job['paid_amount'],'balance'=>(float)$job['balance_amount'],'started_at'=>$job['started_at'],'performance'=>$performance->fetch() ?: null,'payments'=>$payments->fetchAll()]);
        return;
    }
    if ($section === 'jobcards-service-charge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($id < 1) {
            flash('error', 'The job card could not be identified. Please refresh the job card page and try again.');
            redirect('index.php?page=admin&section=jobcards-ongoing');
        }
        $jobStatus = database()->prepare('SELECT status FROM job_cards WHERE id=:id');
        $jobStatus->execute(['id'=>$id]);
        $status = $jobStatus->fetchColumn();
        if ($status === false) {
            flash('error', 'Job card not found.');
            redirect('index.php?page=admin&section=jobcards-ongoing');
        }
        if ($status !== 'ongoing') {
            flash('error', 'Service charge can only be changed while the job card is ongoing.');
            redirect('index.php?page=admin&section=jobcards-view&id='.$id);
        }
        $charge = max(0, (float)($_POST['service_charge'] ?? 0));
        $sum = database()->prepare('SELECT COALESCE(SUM(amount),0) FROM job_card_items WHERE job_card_id=:id AND item_type <> "service"'); $sum->execute(['id'=>$id]);
        $partsTotal = (float)$sum->fetchColumn(); $total = $partsTotal + $charge;
        database()->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user) ON DUPLICATE KEY UPDATE amount=VALUES(amount),created_by=VALUES(created_by)')->execute(['job'=>$id,'amount'=>$charge,'user'=>current_user()['id'] ?? null]);
        $updated = database()->prepare('UPDATE job_cards SET service_charge=:charge,subtotal=:subtotal,total_amount=:total,balance_amount=GREATEST(0,:total-paid_amount) WHERE id=:id AND status="ongoing"');
        $updated->execute(['charge'=>$charge,'subtotal'=>$partsTotal,'total'=>$total,'id'=>$id]);
        if ($updated->rowCount() < 1) {
            $exists = database()->prepare('SELECT id,status FROM job_cards WHERE id=:id');
            $exists->execute(['id'=>$id]);
            $existingJob = $exists->fetch();
            if (!$existingJob) {
                flash('error', 'Job card not found.');
            } elseif ($existingJob['status'] !== 'ongoing') {
                flash('error', 'Service charge could not be saved because this job card is no longer ongoing.');
            } else {
                flash('success', 'Service charge updated.');
            }
            redirect('index.php?page=admin&section=jobcards-view&id='.$id);
        }
        flash('success', 'Service charge updated.'); redirect('index.php?page=admin&section=jobcards-view&id='.$id);
    }
    if ($section === 'jobcards-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $job = job_card_record($id);
        if (!$job) {
            flash('error', 'Job card not found.');
            redirect('index.php?page=admin&section=jobcards-ongoing');
        }
        if (!in_array($job['status'], ['pending', 'ongoing'], true)) {
            flash('error', 'Only pending or ongoing job cards can be deleted.');
            redirect('index.php?page=admin&section=jobcards-view&id='.$id);
        }
        $paymentCheck = database()->prepare('SELECT COUNT(*) FROM job_card_payments WHERE job_card_id=:id');
        $paymentCheck->execute(['id' => $id]);
        if ((int)$paymentCheck->fetchColumn() > 0) {
            flash('error', 'This job card cannot be deleted because payments already exist.');
            redirect('index.php?page=admin&section=jobcards-view&id='.$id);
        }
        database()->prepare('DELETE FROM job_cards WHERE id=:id')->execute(['id' => $id]);
        flash('success', 'Job card deleted successfully.');
        redirect('index.php?page=admin&section=jobcards-' . ($job['status'] === 'ongoing' ? 'ongoing' : 'pending'));
    }
    if ($section === 'jobcards-payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $amountReceived = max(0, (float)($_POST['amount'] ?? 0)); $method = in_array($_POST['payment_method'] ?? 'cash', ['cash','card','bank','other'], true) ? $_POST['payment_method'] : 'cash';
        $job = job_card_record($id); if (!$job) { http_response_code(404); exit('Job card not found.'); }
        $performanceStartRaw = trim((string)($_POST['performance_start'] ?? ''));
        $performanceEndRaw = trim((string)($_POST['performance_end'] ?? ''));
        $existingPerformance = database()->prepare('SELECT start_time,end_time FROM job_card_performance WHERE job_card_id=:id LIMIT 1');
        $existingPerformance->execute(['id'=>$id]);
        $existingPerformance = $existingPerformance->fetch() ?: null;
        if ($performanceStartRaw === '' && $existingPerformance) $performanceStartRaw = date('Y-m-d\TH:i', strtotime($existingPerformance['start_time']));
        if ($performanceEndRaw === '' && $existingPerformance) $performanceEndRaw = date('Y-m-d\TH:i', strtotime($existingPerformance['end_time']));
        $performanceStart = $performanceStartRaw !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $performanceStartRaw) : false;
        $performanceEnd = $performanceEndRaw !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $performanceEndRaw) : false;
        if (!$performanceStart || !$performanceEnd || $performanceEnd < $performanceStart) {
            flash('error', 'Enter valid performance start and end times before generating the bill.');
            redirect('index.php?page=admin&section=jobcards-view&id='.$id);
        }
        ensure_stock_tables();
        $pdo = database(); $receiptNo = 'PAY-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM job_cards WHERE id=:id FOR UPDATE');
            $lock->execute(['id'=>$id]);
            $lockedJob = $lock->fetch();
            if (!$lockedJob) throw new RuntimeException('Job card not found.');
            $calculation = job_payment_calculation($lockedJob, $_POST);
            $amountReceived = $calculation['received']; $amountApplied = $calculation['applied']; $changeAmount = $calculation['change']; $method = $calculation['method'];
            $durationMinutes = ($performanceEnd->getTimestamp() - $performanceStart->getTimestamp()) / 60;
            $pdo->prepare('INSERT INTO job_card_performance (job_card_id,mechanic_id,start_time,end_time,duration_minutes,recorded_by) VALUES (:job,:mechanic,:start,:end,:duration,:user) ON DUPLICATE KEY UPDATE mechanic_id=VALUES(mechanic_id),start_time=VALUES(start_time),end_time=VALUES(end_time),duration_minutes=VALUES(duration_minutes),recorded_by=VALUES(recorded_by)')->execute(['job'=>$id,'mechanic'=>$job['mechanic_id'] ?: null,'start'=>$performanceStart->format('Y-m-d H:i:s'),'end'=>$performanceEnd->format('Y-m-d H:i:s'),'duration'=>$durationMinutes,'user'=>current_user()['id'] ?? null]);
            foreach ($job['items'] as $item) {
                $stockItemId = (int)($item['stock_item_id'] ?? 0);
                $quantity = (float)($item['quantity'] ?? 0);
                if ($stockItemId < 1 || $quantity <= 0) continue;
                $alreadyUsed = $pdo->prepare("SELECT 1 FROM stock_movements WHERE reference_type='job_card_item' AND reference_id=:item LIMIT 1");
                $alreadyUsed->execute(['item' => (int)$item['id']]);
                if (!$alreadyUsed->fetchColumn()) {
                    $userId = isset(current_user()['id']) ? (int) current_user()['id'] : null;
                    consume_stock_fifo($stockItemId, $quantity, 'job_card_item', (int)$item['id'], $userId, 'Stock used for job card ' . $job['job_card_no'], $pdo);
                }
            }
            $pdo->prepare('INSERT INTO job_card_payments (job_card_id,receipt_no,amount,amount_received,change_amount,payment_method,created_by) VALUES (:job,:receipt,:amount,:received,:change,:method,:user)')->execute(['job'=>$id,'receipt'=>$receiptNo,'amount'=>$amountApplied,'received'=>$amountReceived,'change'=>$changeAmount,'method'=>$method,'user'=>current_user()['id'] ?? null]);
            $paymentId = (int)$pdo->lastInsertId();
            $paid = $calculation['paid']; $newBalance = $calculation['balance'];
            $pdo->prepare('UPDATE job_cards SET discount=:discount,discount_type=:discount_type,discount_value=:discount_value,total_amount=:total,paid_amount=:paid,balance_amount=:balance,payment_method=:method,status="completed",completed_at=COALESCE(completed_at,NOW()) WHERE id=:id')->execute(['discount'=>$calculation['discount'],'discount_type'=>$calculation['discount_type'],'discount_value'=>$calculation['discount_value'],'total'=>$calculation['total'],'paid'=>$paid,'balance'=>$newBalance,'method'=>$method,'id'=>$id]);
            $pdo->commit();
            // Generate the invoice now so customer totals update before the invoice list is opened.
            require_once __DIR__ . '/invoices.php';
            invoice_ensure_tables();
            flash('success', 'Payment recorded and receipt created.'); redirect('index.php?page=admin&section=jobcards-receipt&id='.$paymentId);
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', 'Payment could not be recorded: ' . $exception->getMessage()); redirect('index.php?page=admin&section=jobcards-view&id='.$id); }
    }
    if ($section === 'jobcards-receipt') {
        $statement = database()->prepare('SELECT p.*,j.job_card_no,j.subtotal,j.service_charge,j.discount,j.discount_type,j.discount_value,j.total_amount,j.paid_amount,j.balance_amount,c.name AS customer_name,c.contact_number,v.vehicle_number,perf.start_time AS performance_start,perf.end_time AS performance_end,perf.duration_minutes AS performance_duration,(SELECT COALESCE(SUM(amount),0) FROM job_card_items WHERE job_card_id=j.id) AS parts_total FROM job_card_payments p JOIN job_cards j ON j.id=p.job_card_id JOIN customers c ON c.id=j.customer_id LEFT JOIN vehicles v ON v.id=j.vehicle_id LEFT JOIN job_card_performance perf ON perf.job_card_id=j.id WHERE p.id=:id LIMIT 1');
        $statement->execute(['id'=>$id]); $payment = $statement->fetch();
        if (!$payment) { http_response_code(404); exit('Receipt not found.'); }
        $items = database()->prepare('SELECT item_type,item_name,item_code,quantity,unit_price,list_unit_price,amount FROM job_card_items WHERE job_card_id=:job ORDER BY id');
        $items->execute(['job'=>$payment['job_card_id']]); $receiptItems = $items->fetchAll();
        $receiptSettings = receipt_settings();
        $title = 'Payment Receipt ' . $payment['receipt_no']; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcard-receipt.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    if ($section === 'jobcards-start') {
        $job = job_card_record($id);
        if (!$job) { http_response_code(404); exit('Job card not found.'); }
        if ($job['status'] !== 'pending') {
            redirect('index.php?page=admin&section=jobcards-' . ($job['status'] === 'ongoing' ? 'ongoing' : 'completed'));
        }
        $jobLookups = job_card_lookup(); $title = 'Start Job Card'; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcard-start.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    if ($section === 'jobcards-action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        $status = ['start' => 'ongoing', 'complete' => 'completed', 'cancel' => 'cancelled'][$action] ?? '';
        if ($action === 'start') {
            if (trim((string)($_POST['bay_name'] ?? '')) === '') {
                $_POST['bay_name'] = (string)(database()->query("SELECT bay_name FROM bays WHERE status='active' ORDER BY bay_name LIMIT 1")->fetchColumn() ?: '');
            }
            if (!mechanic_is_available_today((int)($_POST['mechanic_id'] ?? 0))) {
                $_POST['mechanic_id'] = 0;
            }
            if (trim((string)($_POST['bay_name'] ?? '')) === '' || (int)($_POST['mechanic_id'] ?? 0) < 1) {
                $job = job_card_record($id);
                if (!$job) { http_response_code(404); exit('Job card not found.'); }
                $jobLookups = job_card_lookup(); $title = 'Start Job Card'; $sectionForHeader = 'jobcards-start';
                require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcard-start.php'; require __DIR__ . '/../includes/footer.php'; return;
            }
        }
        if ($status !== '') {
            $extra = $status === 'ongoing' ? ',started_at=COALESCE(started_at,NOW()),bay_name=:bay_name,mechanic_id=:mechanic_id' : ($status === 'completed' ? ',completed_at=NOW()' : '');
            $params = ['status' => $status, 'id' => $id];
            if ($status === 'ongoing') { $params['bay_name'] = trim((string)$_POST['bay_name']); $params['mechanic_id'] = (int)$_POST['mechanic_id']; }
            $update = database()->prepare("UPDATE job_cards SET status=:status{$extra} WHERE id=:id");
            $update->execute($params);
            if ($status === 'ongoing') {
                $serviceTotal = database()->prepare("SELECT COALESCE(SUM(CASE WHEN item_type <> 'service' THEN amount ELSE 0 END),0) subtotal, COALESCE(SUM(CASE WHEN item_type='service' THEN amount ELSE 0 END),0) service_charge FROM job_card_items WHERE job_card_id=:id");
                $serviceTotal->execute(['id' => $id]);
                $totals = $serviceTotal->fetch() ?: ['subtotal' => 0, 'service_charge' => 0];
                $partsTotal = (float) $totals['subtotal'];
                $serviceCharge = (float) ($job['service_charge'] ?? 0);
                $total = $partsTotal + $serviceCharge;
                database()->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user) ON DUPLICATE KEY UPDATE amount=VALUES(amount),created_by=VALUES(created_by)')->execute(['job' => $id, 'amount' => $serviceCharge, 'user' => current_user()['id'] ?? null]);
                database()->prepare('UPDATE job_cards SET subtotal=:subtotal,service_charge=:charge,total_amount=:total,balance_amount=GREATEST(0,:total-paid_amount) WHERE id=:id')->execute(['subtotal' => $partsTotal, 'charge' => $serviceCharge, 'total' => $total, 'id' => $id]);
            }
            if ($status === 'ongoing' && $update->rowCount() < 1) {
                $job = job_card_record($id);
                if (!$job) { http_response_code(404); exit('Job card not found.'); }
                if ($job['status'] !== 'ongoing') { exit('Job card could not be started.'); }
            }
            if ($status === 'completed') {
                // Create the linked invoice before redirecting so dashboards and invoice lists update immediately.
                require_once __DIR__ . '/invoices.php';
                invoice_ensure_tables();
            }
            flash('success', 'Job card status updated successfully.');
        }
        redirect('index.php?page=admin&section=jobcards-' . ($status === 'ongoing' ? 'ongoing' : ($status === 'completed' ? 'completed' : 'pending')));
    }
    if ($section === 'jobcards-item-add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $type = in_array($_POST['item_type'] ?? 'manual', ['part','service','manual'], true) ? $_POST['item_type'] : 'manual';
        $name = trim((string)($_POST['item_name'] ?? '')); $quantity = max(0.01, (float)($_POST['quantity'] ?? 1)); $price = max(0, (float)($_POST['unit_price'] ?? 0)); $buyingPrice = max(0, (float)($_POST['buying_price'] ?? 0)); $stockId = max(0, (int)($_POST['stock_item_id'] ?? 0)); $serviceId = max(0, (int)($_POST['service_id'] ?? 0)); if ($type === 'service' && !empty($_POST['custom_service'])) $serviceId = 0; $code = null;
        if ($type === 'part' && $stockId > 0) { $s=database()->prepare('SELECT part_code,part_name,selling_price FROM stock_items WHERE id=:id AND status="active"'); $s->execute(['id'=>$stockId]); $stock=$s->fetch(); if($stock){$name=$stock['part_name'];$code=$stock['part_code'];if($price<=0)$price=(float)$stock['selling_price'];} else {$stockId=0;} }
        if ($type === 'service' && $serviceId > 0) { $s=database()->prepare('SELECT service_code,service_name,price FROM services WHERE id=:id AND status="active"'); $s->execute(['id'=>$serviceId]); $service=$s->fetch(); if($service){$name=$service['service_name'];$code=$service['service_code'];if($price<=0)$price=(float)$service['price'];} else {$serviceId=0;} }
        if ($name !== '' && ($type !== 'part' || $stockId > 0) && ($type !== 'service' || $serviceId >= 0)) {
            $job = job_card_record($id);
            if (!$job) { http_response_code(404); exit('Job card not found.'); }
            $pdo = database();
            try {
                $pdo->beginTransaction();
                if ($type === 'service') {
                    $sum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM job_card_items WHERE job_card_id=:id AND item_type <> 'service'");
                    $sum->execute(['id' => $id]);
                    $partsTotal = (float)$sum->fetchColumn();
                    $serviceCharge = (float)($job['service_charge'] ?? 0) + ($quantity * $price);
                    $total = $partsTotal + $serviceCharge;
                } else {
                    $pdo->prepare('INSERT INTO job_card_items (job_card_id,item_type,stock_item_id,service_id,item_name,item_code,quantity,unit_price,list_unit_price,amount) VALUES (:card,:type,:stock,:service,:name,:code,:quantity,:price,:list_price,:amount)')->execute(['card'=>$id,'type'=>$type,'stock'=>$stockId?:null,'service'=>$serviceId?:null,'name'=>$name,'code'=>$code,'quantity'=>$quantity,'price'=>$price,'list_price'=>$type==='part'?stock_list_price_snapshot($stockId):null,'amount'=>$quantity*$price]);
                    $itemId = (int)$pdo->lastInsertId();
                    if ($type === 'part') reserve_job_card_stock($pdo, $stockId, $quantity, $itemId, $job);
                    if ($type === 'manual') add_external_part_expense($job, $name, $quantity, $buyingPrice, $price);
                    $sum=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM job_card_items WHERE job_card_id=:id AND item_type <> 'service'"); $sum->execute(['id'=>$id]); $partsTotal=(float)$sum->fetchColumn(); $serviceCharge=(float)($job['service_charge'] ?? 0); $total=$partsTotal+$serviceCharge;
                }
                $pdo->prepare('UPDATE job_cards SET subtotal=:subtotal,service_charge=:service_charge,total_amount=:total,balance_amount=GREATEST(0,:total-paid_amount) WHERE id=:id')->execute(['subtotal'=>$partsTotal,'service_charge'=>$serviceCharge,'total'=>$total,'id'=>$id]);
                $pdo->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user) ON DUPLICATE KEY UPDATE amount=VALUES(amount),created_by=VALUES(created_by)')->execute(['job'=>$id,'amount'=>$serviceCharge,'user'=>current_user()['id']??null]);
                $pdo->commit();
                flash('success', $type === 'service' ? 'Service charge added to the job card.' : ($type === 'manual' ? 'Manual part added and buying cost recorded as an expense.' : 'Item added to job card.'));
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('error', 'The item could not be added: ' . $exception->getMessage());
            }
        } else {
            flash('error', 'Select a valid stock part or enter a part name before adding the item.');
        }
        $returnSection = in_array($_POST['return_section'] ?? '', ['jobcards-edit', 'jobcards-view'], true) ? $_POST['return_section'] : 'jobcards-view';
        redirect('index.php?page=admin&section='.$returnSection.'&id='.$id);
    }
    if ($section === 'jobcards-item-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $itemId = max(0, (int)($_POST['item_id'] ?? 0));
        $job = job_card_record($id);
        $pdo = database();
        try {
            $pdo->beginTransaction();
            $item = $pdo->prepare('SELECT stock_item_id,quantity FROM job_card_items WHERE id=:item AND job_card_id=:card FOR UPDATE');
            $item->execute(['item' => $itemId, 'card' => $id]);
            $item = $item->fetch();
            if ($item && $job) release_job_card_stock($pdo, $itemId, (int)$item['stock_item_id'], (float)$item['quantity'], $job);
            $pdo->prepare('DELETE FROM job_card_items WHERE id=:item AND job_card_id=:card')->execute(['item'=>$itemId,'card'=>$id]);
            $sum=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN item_type <> 'service' THEN amount ELSE 0 END),0) subtotal,COALESCE(SUM(CASE WHEN item_type='service' THEN amount ELSE 0 END),0) service_charge FROM job_card_items WHERE job_card_id=:id");$sum->execute(['id'=>$id]);$totals=$sum->fetch()?:['subtotal'=>0,'service_charge'=>0];$total=(float)$totals['subtotal']+(float)$totals['service_charge'];$pdo->prepare('UPDATE job_cards SET subtotal=:subtotal,service_charge=:service_charge,total_amount=:total,balance_amount=GREATEST(0,:total-paid_amount) WHERE id=:id')->execute(['subtotal'=>(float)$totals['subtotal'],'service_charge'=>(float)$totals['service_charge'],'total'=>$total,'id'=>$id]);$pdo->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user) ON DUPLICATE KEY UPDATE amount=VALUES(amount),created_by=VALUES(created_by)')->execute(['job'=>$id,'amount'=>(float)$totals['service_charge'],'user'=>current_user()['id']??null]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'The item could not be removed: ' . $exception->getMessage());
        }
        redirect('index.php?page=admin&section=jobcards-view&id='.$id);
    }
    if ($section === 'jobcards-edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['confirm_update'] ?? '') !== 'yes') {
            flash('error', 'Confirm Yes before updating the job card.');
            redirect('index.php?page=admin&section=jobcards-edit&id=' . $id);
        }
        $input = job_card_input();
        $errors = job_card_validate($input);
        if (!$errors) {
            $statement = database()->prepare('UPDATE job_cards SET customer_id=:customer,vehicle_id=:vehicle,bay_name=:bay,mechanic_id=:mechanic,complaint=:complaint,requested_work=:work,notes=:notes,expected_delivery_date=:delivery,priority=:priority WHERE id=:id AND status IN ("pending","ongoing")');
            $statement->execute(['customer'=>$input['customer_id'],'vehicle'=>$input['vehicle_id'] ?: null,'bay'=>$input['bay_name'] ?: null,'mechanic'=>$input['mechanic_id'] ?: null,'complaint'=>$input['complaint'],'work'=>$input['requested_work'] ?: null,'notes'=>$input['notes'] ?: null,'delivery'=>$input['expected_delivery_date'] ?: null,'priority'=>$input['priority'],'id'=>$id]);
            flash('success', 'Job card updated successfully.');
            $updatedStatus = database()->prepare('SELECT status FROM job_cards WHERE id=:id');
            $updatedStatus->execute(['id' => $id]);
            redirect('index.php?page=admin&section=jobcards-' . ($updatedStatus->fetchColumn() === 'ongoing' ? 'ongoing' : 'pending'));
        }
    }
    if ($section === 'jobcards-edit') {
        $job = job_card_record($id);
        if (!$job) { http_response_code(404); exit('Job card not found.'); }
        if (!in_array($job['status'], ['pending', 'ongoing'], true)) { redirect('index.php?page=admin&section=jobcards-view&id=' . $id); }
        $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? job_card_input() : ['customer_id'=>(int)$job['customer_id'],'vehicle_id'=>(int)$job['vehicle_id'],'bay_name'=>(string)($job['bay_name'] ?? ''),'mechanic_id'=>(int)($job['mechanic_id'] ?? 0),'complaint'=>(string)($job['complaint'] ?? ''),'requested_work'=>(string)($job['requested_work'] ?? ''),'notes'=>(string)($job['notes'] ?? ''),'expected_delivery_date'=>(string)($job['expected_delivery_date'] ?? ''),'priority'=>(string)$job['priority'],'items'=>$job['items']];
        $lookups = job_card_lookup(); $title = 'Edit Job Card ' . $job['job_card_no']; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcard-edit.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    if ($section === 'jobcards-view') {
        $job = job_card_record($id);
        if (!$job) { http_response_code(404); exit('Job card not found.'); }
        sync_job_card_stock_reservations($job);
        $jobLookups = job_card_lookup(); $title = 'Job Card ' . $job['job_card_no']; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/' . ($job['status'] === 'ongoing' ? 'jobcard-ongoing.php' : 'jobcard-view.php'); require __DIR__ . '/../includes/footer.php'; return;
    }
    if ($section === 'jobcards-new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $input = job_card_input();
        $serviceCandidate = $input['primary_service_name'] !== '' ? $input['primary_service_name'] : ($input['requested_work'] !== '' ? $input['requested_work'] : $input['complaint']);
        if ($input['primary_service_id'] < 1 && $serviceCandidate !== '') {
            $serviceLookup = database()->prepare("SELECT id,price FROM services WHERE status='active' AND LOWER(service_name)=LOWER(:name) LIMIT 1");
            $serviceLookup->execute(['name' => $serviceCandidate]);
            $matchedService = $serviceLookup->fetch();
            if ($matchedService) {
                $input['primary_service_id'] = (int)$matchedService['id'];
                $input['primary_service_price'] = (float)$matchedService['price'];
            }
        }
        if ($input['complaint'] === '' && $input['primary_service_name'] !== '') $input['complaint'] = $input['primary_service_name'];
        $errors = job_card_validate($input, ($_POST['save_mode'] ?? 'pending') === 'start');
        if (($_POST['save_mode'] ?? 'pending') === 'start') {
            if ($input['mechanic_id'] > 0 && !mechanic_is_available_today($input['mechanic_id'])) $errors[] = 'Only mechanics marked Present or Half Day today can be assigned.';
            if ($input['mechanic_id'] < 1) $errors[] = 'Select a mechanic who is Present or Half Day today.';
        }
        if (!$errors) {
            $pdo = database(); $pdo->beginTransaction();
            try {
                $selectedService = null;
                if ($input['primary_service_id'] > 0) {
                    $serviceStatement = $pdo->prepare("SELECT id,service_code,service_name,description,price FROM services WHERE id=:id AND status='active' LIMIT 1");
                    $serviceStatement->execute(['id' => $input['primary_service_id']]);
                    $selectedService = $serviceStatement->fetch() ?: null;
                    if (!$selectedService) throw new RuntimeException('Selected service is no longer active.');
                    $alreadySelected = false;
                    foreach ($input['items'] as $item) if ((int)($item['service_id'] ?? 0) === (int)$selectedService['id']) $alreadySelected = true;
                    if (!$alreadySelected) $input['items'][] = ['type' => 'service', 'name' => $selectedService['service_name'], 'code' => $selectedService['service_code'], 'quantity' => 1, 'unit_price' => $selectedService['price'], 'service_id' => $selectedService['id']];
                } elseif ($input['primary_service_name'] !== '') {
                    $input['items'][] = ['type' => 'service', 'name' => $input['primary_service_name'], 'code' => '', 'quantity' => 1, 'unit_price' => $input['primary_service_price'], 'service_id' => 0];
                }
                $itemRows = []; $subtotal = 0; $serviceCharge = 0;
                foreach ($input['items'] as $item) {
                    $name = trim((string)($item['name'] ?? '')); if ($name === '') continue;
                    $type = in_array(($item['type'] ?? 'manual'), ['part','service','manual'], true) ? $item['type'] : 'manual'; $qty = (float)($item['quantity'] ?? 1); $price = (float)($item['unit_price'] ?? 0); $amount = $qty * $price; if ($type === 'service') $serviceCharge += $amount; else $subtotal += $amount;
                    if ($type === 'service') continue;
                    $itemRows[] = ['type' => $type,'name' => $name,'code' => trim((string)($item['code'] ?? '')),'quantity' => $qty,'price' => $price,'amount' => $amount,'stock_id' => max(0,(int)($item['stock_id'] ?? 0)),'service_id' => max(0,(int)($item['service_id'] ?? 0))];
                }
                $saveStatus = ($_POST['save_mode'] ?? 'pending') === 'start' ? 'ongoing' : 'pending';
                $total = $subtotal + $serviceCharge;
                $insert = $pdo->prepare('INSERT INTO job_cards (job_card_no,customer_id,vehicle_id,bay_name,mechanic_id,complaint,requested_work,notes,expected_delivery_date,priority,status,subtotal,service_charge,total_amount,balance_amount,created_by,started_at) VALUES (:no,:customer,:vehicle,:bay,:mechanic,:complaint,:work,:notes,:delivery,:priority,:status,:subtotal,:service_charge,:total,:total,:user,:started)');
                $insert->execute(['no'=>'TMP-'.bin2hex(random_bytes(4)),'customer'=>$input['customer_id'],'vehicle'=>$input['vehicle_id'] ?: null,'bay'=>$input['bay_name'],'mechanic'=>$input['mechanic_id'] ?: null,'complaint'=>$input['complaint'] ?: null,'work'=>$input['requested_work'] ?: null,'notes'=>$input['notes'] ?: null,'delivery'=>$input['expected_delivery_date'] ?: null,'priority'=>$input['priority'],'status'=>$saveStatus,'subtotal'=>$subtotal,'service_charge'=>$serviceCharge,'total'=>$total,'user'=>current_user()['id'] ?? null,'started'=>$saveStatus==='ongoing'?date('Y-m-d H:i:s'):null]);
                $saved = (int)$pdo->lastInsertId(); $pdo->prepare('UPDATE job_cards SET job_card_no=:no WHERE id=:id')->execute(['no'=>job_card_no($saved),'id'=>$saved]);
                if ($serviceCharge > 0) $pdo->prepare('INSERT INTO job_card_service_charges (job_card_id,amount,created_by) VALUES (:job,:amount,:user)')->execute(['job' => $saved, 'amount' => $serviceCharge, 'user' => current_user()['id'] ?? null]);
                $itemInsert = $pdo->prepare('INSERT INTO job_card_items (job_card_id,item_type,stock_item_id,service_id,item_name,item_code,quantity,unit_price,list_unit_price,amount) VALUES (:card,:type,:stock,:service,:name,:code,:quantity,:price,:list_price,:amount)');
                foreach ($itemRows as $item) $itemInsert->execute(['card'=>$saved,'type'=>$item['type'],'stock'=>$item['stock_id'] ?: null,'service'=>$item['service_id'] ?: null,'name'=>$item['name'],'code'=>$item['code'] ?: null,'quantity'=>$item['quantity'],'price'=>$item['price'],'list_price'=>$item['type']==='part'?stock_list_price_snapshot($item['stock_id']):null,'amount'=>$item['amount']]);
                $pdo->commit(); flash('success','Job card created successfully.'); redirect('index.php?page=admin&section=jobcards-'.$saveStatus);
            } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = 'Job card could not be saved.'; }
        }
    } else {
        $input = job_card_input();
    }
    if ($section === 'jobcards-new') {
        $lookups = job_card_lookup(); $title = 'New Job Card'; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcard-form.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    $status = ['jobcards-pending' => 'pending', 'jobcards-ongoing' => 'ongoing', 'jobcards-completed' => 'completed'][$section] ?? 'pending';
    $filters = ['search'=>trim((string)($_GET['search']??'')),'mechanic_id'=>max(0,(int)($_GET['mechanic_id']??0)),'bay_name'=>trim((string)($_GET['bay_name']??'')),'priority'=>in_array($_GET['priority']??'', ['low','normal','high','urgent'], true)?$_GET['priority']:'','date_from'=>trim((string)($_GET['date_from']??'')),'date_to'=>trim((string)($_GET['date_to']??''))];
    $currentPage = 1; $pages = 1; $offset = 0;
    if ($status === 'completed') {
        $total = job_card_rows($status, $filters, true);
        $pages = max(1, (int)ceil($total / 5));
        $currentPage = min($pages, max(1, (int)($_GET['p'] ?? 1)));
        $filters['per_page'] = 5;
        $filters['page'] = $currentPage;
        $offset = ($currentPage - 1) * 5;
    }
    $rows = job_card_rows($status,$filters);
    if ($status !== 'completed') $total = count($rows);
    $paginationFilters = $filters;
    unset($paginationFilters['page'], $paginationFilters['per_page']);
    $paginationUrl = 'index.php?' . http_build_query(array_merge(['page'=>'admin', 'section'=>$section], $paginationFilters));
    $lookups = job_card_lookup(); $title = ucfirst($status) . ' Job Cards'; $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/jobcards.php'; require __DIR__ . '/../includes/footer.php';
}
