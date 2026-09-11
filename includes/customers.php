<?php

declare(strict_types=1);

function ensure_customers_table(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS customers (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_code VARCHAR(20) NOT NULL, customer_type ENUM('individual','company','walk_in') NOT NULL DEFAULT 'individual', name VARCHAR(160) NOT NULL, contact_number VARCHAR(40) NOT NULL, secondary_contact_number VARCHAR(40) NULL, email VARCHAR(190) NULL, nic_or_registration VARCHAR(100) NULL, address TEXT NULL, notes TEXT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY customers_code_unique (customer_code), KEY customers_contact_index (contact_number), KEY customers_email_index (email), KEY customers_type_index (customer_type), KEY customers_status_index (status), KEY customers_created_index (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function ensure_vehicles_table(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS vehicles (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id BIGINT UNSIGNED NOT NULL, vehicle_number VARCHAR(40) NOT NULL, vehicle_type VARCHAR(60) NOT NULL, make VARCHAR(100) NULL, model VARCHAR(100) NOT NULL, colour VARCHAR(60) NULL, engine_number VARCHAR(100) NULL, chassis_number VARCHAR(100) NULL, current_mileage DECIMAL(12,2) NULL, next_service_mileage DECIMAL(12,2) NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY vehicles_customer_index (customer_id), KEY vehicles_number_index (vehicle_number), CONSTRAINT vehicles_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function customer_filters(): array
{
    $requestedPerPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
    return ['search'=>trim((string) ($_GET['search'] ?? '')), 'status'=>in_array($_GET['status'] ?? '', ['active','inactive'], true) ? $_GET['status'] : '', 'type'=>in_array($_GET['type'] ?? '', ['individual','company','walk_in'], true) ? $_GET['type'] : '', 'from'=>preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '', 'to'=>preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '', 'per_page'=>in_array($requestedPerPage, [5,10,25,50], true) ? $requestedPerPage : 5, 'page'=>max(1, (int) ($_GET['p'] ?? 1))];
}

function customer_where(array $filters, array &$params): string
{
    $where = [];
    if ($filters['search'] !== '') { $where[] = '(name LIKE :search OR customer_code LIKE :search OR contact_number LIKE :search OR secondary_contact_number LIKE :search OR email LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
    if ($filters['status'] !== '') { $where[] = 'status = :status'; $params['status'] = $filters['status']; }
    if ($filters['type'] !== '') { $where[] = 'customer_type = :type'; $params['type'] = $filters['type']; }
    if ($filters['from'] !== '') { $where[] = 'created_at >= :from_date'; $params['from_date'] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = 'created_at <= :to_date'; $params['to_date'] = $filters['to'] . ' 23:59:59'; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function customer_query(array $filters, bool $count = false): array|int
{
    $params = []; $where = customer_where($filters, $params);
    $sql = $count ? "SELECT COUNT(*) FROM customers{$where}" : "SELECT id, customer_code, customer_type, name, contact_number, email, status, created_at FROM customers{$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $statement = database()->prepare($sql);
    foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
    if (!$count) { $statement->bindValue(':limit', max(1, (int) $filters['per_page']), PDO::PARAM_INT); $statement->bindValue(':offset', max(0, ($filters['page'] - 1) * $filters['per_page']), PDO::PARAM_INT); }
    $statement->execute();
    return $count ? (int) $statement->fetchColumn() : $statement->fetchAll();
}

function customer_kpis(): array
{
    $row = database()->query("SELECT COUNT(*) total, SUM(status = 'active') active, SUM(status = 'inactive') inactive, SUM(created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')) new_customers FROM customers")->fetch() ?: [];
    return ['Total Customers'=>(int) ($row['total'] ?? 0), 'Active Customers'=>(int) ($row['active'] ?? 0), 'New Customers'=>(int) ($row['new_customers'] ?? 0), 'Inactive Customers'=>(int) ($row['inactive'] ?? 0)];
}

function customer_input(): array
{
    return ['customer_type'=>$_POST['customer_type'] ?? 'individual', 'name'=>trim((string) ($_POST['name'] ?? '')), 'contact_number'=>trim((string) ($_POST['contact_number'] ?? '')), 'secondary_contact_number'=>trim((string) ($_POST['secondary_contact_number'] ?? '')), 'email'=>trim((string) ($_POST['email'] ?? '')), 'nic_or_registration'=>trim((string) ($_POST['nic_or_registration'] ?? '')), 'address'=>trim((string) ($_POST['address'] ?? '')), 'notes'=>trim((string) ($_POST['notes'] ?? '')), 'status'=>$_POST['status'] ?? 'active', 'vehicles'=>is_array($_POST['vehicles'] ?? null) ? $_POST['vehicles'] : []];
}

function vehicle_input(array $vehicle): array
{
    return ['vehicle_number'=>trim((string) ($vehicle['vehicle_number'] ?? '')), 'vehicle_type'=>trim((string) ($vehicle['vehicle_type'] ?? '')), 'make'=>trim((string) ($vehicle['make'] ?? '')), 'model'=>trim((string) ($vehicle['model'] ?? '')), 'colour'=>trim((string) ($vehicle['colour'] ?? '')), 'engine_number'=>trim((string) ($vehicle['engine_number'] ?? '')), 'chassis_number'=>trim((string) ($vehicle['chassis_number'] ?? '')), 'current_mileage'=>trim((string) ($vehicle['current_mileage'] ?? '')), 'next_service_mileage'=>trim((string) ($vehicle['next_service_mileage'] ?? '')), 'notes'=>trim((string) ($vehicle['notes'] ?? ''))];
}

function validate_customer(array $input): array
{
    $errors = [];
    if (!in_array($input['customer_type'], ['individual','company','walk_in'], true)) $errors[] = 'Select a valid customer type.';
    if ($input['name'] === '') $errors[] = 'Customer or company name is required.';
    if ($input['contact_number'] === '') $errors[] = 'Contact number is required.';
    if ($input['address'] === '') $errors[] = 'Address is required.';
    if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!in_array($input['status'], ['active','inactive'], true)) $errors[] = 'Select a valid status.';
    return $errors;
}

function validate_vehicles(array $vehicles): array
{
    if (!$vehicles) return ['Add at least one vehicle.'];
    $errors = [];
    foreach ($vehicles as $index => $data) { $vehicle = vehicle_input((array) $data); $number = $index + 1; if ($vehicle['vehicle_number'] === '') $errors[] = "Vehicle {$number}: vehicle number is required."; if ($vehicle['vehicle_type'] === '') $errors[] = "Vehicle {$number}: vehicle type is required."; if ($vehicle['model'] === '') $errors[] = "Vehicle {$number}: model is required."; foreach (['current_mileage'=>'current mileage','next_service_mileage'=>'next service mileage'] as $key => $label) if ($vehicle[$key] !== '' && (!is_numeric($vehicle[$key]) || (float) $vehicle[$key] < 0)) $errors[] = "Vehicle {$number}: {$label} must be a positive number."; }
    return $errors;
}

function customer_by_id(int $id): ?array { $statement = database()->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1'); $statement->execute(['id'=>$id]); return $statement->fetch() ?: null; }
function customer_vehicles(int $customerId): array { $statement = database()->prepare('SELECT * FROM vehicles WHERE customer_id = :customer_id ORDER BY id ASC'); $statement->execute(['customer_id'=>$customerId]); return $statement->fetchAll(); }
function customer_query_string(array $filters, array $extra = []): string { return http_build_query(array_merge(['search'=>$filters['search'],'status'=>$filters['status'],'type'=>$filters['type'],'from'=>$filters['from'],'to'=>$filters['to'],'per_page'=>$filters['per_page']], $extra)); }

function insert_customer_vehicles(PDO $pdo, int $customerId, array $vehicles): void
{
    $statement = $pdo->prepare('INSERT INTO vehicles (customer_id, vehicle_number, vehicle_type, make, model, colour, engine_number, chassis_number, current_mileage, next_service_mileage, notes) VALUES (:customer_id,:vehicle_number,:vehicle_type,:make,:model,:colour,:engine_number,:chassis_number,:current_mileage,:next_service_mileage,:notes)');
    foreach ($vehicles as $data) { $vehicle = vehicle_input((array) $data); $statement->execute(['customer_id'=>$customerId,'vehicle_number'=>$vehicle['vehicle_number'],'vehicle_type'=>$vehicle['vehicle_type'],'make'=>$vehicle['make'] ?: null,'model'=>$vehicle['model'],'colour'=>$vehicle['colour'] ?: null,'engine_number'=>$vehicle['engine_number'] ?: null,'chassis_number'=>$vehicle['chassis_number'] ?: null,'current_mileage'=>$vehicle['current_mileage'] !== '' ? $vehicle['current_mileage'] : null,'next_service_mileage'=>$vehicle['next_service_mileage'] !== '' ? $vehicle['next_service_mileage'] : null,'notes'=>$vehicle['notes'] ?: null]); }
}

function handle_customer_request(string $section): void
{
    ensure_customers_table(); ensure_vehicles_table(); $id = max(0, (int) ($_GET['id'] ?? 0));
    if ($section === 'customers-export') { $filters = customer_filters(); $filters['per_page'] = max(1, customer_query($filters, true)); $filters['page'] = 1; $rows = customer_query($filters); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="customers.csv"'); $out = fopen('php://output', 'w'); fputcsv($out, ['Customer Code','Name','Contact Number','Email','Customer Type','Status','Created Date']); foreach ($rows as $row) fputcsv($out, [$row['customer_code'],$row['name'],$row['contact_number'],$row['email'],ucwords(str_replace('_',' ',$row['customer_type'])),ucfirst($row['status']),$row['created_at']]); fclose($out); return; }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf();
        if ($section === 'customers-add') { $input = customer_input(); $errors = array_merge(validate_customer($input), validate_vehicles($input['vehicles'])); if (!$errors) { $pdo = database(); $pdo->beginTransaction(); try { $statement = $pdo->prepare('INSERT INTO customers (customer_code,customer_type,name,contact_number,secondary_contact_number,email,nic_or_registration,address,notes,status) VALUES (:code,:type,:name,:contact,:secondary,:email,:nic,:address,:notes,:status)'); $statement->execute(['code'=>'TMP-'.bin2hex(random_bytes(6)),'type'=>$input['customer_type'],'name'=>$input['name'],'contact'=>$input['contact_number'],'secondary'=>$input['secondary_contact_number'] ?: null,'email'=>$input['email'] ?: null,'nic'=>$input['nic_or_registration'] ?: null,'address'=>$input['address'],'notes'=>$input['notes'] ?: null,'status'=>$input['status']]); $newId = (int) $pdo->lastInsertId(); $update = $pdo->prepare('UPDATE customers SET customer_code = :code WHERE id = :id'); $update->execute(['code'=>'CUS-'.str_pad((string) $newId, 4, '0', STR_PAD_LEFT),'id'=>$newId]); insert_customer_vehicles($pdo, $newId, $input['vehicles']); $pdo->commit(); flash('success','Customer and vehicle added successfully.'); redirect('index.php?page=admin&section=customers'); } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = 'The customer and vehicle could not be saved.'; } } }
        elseif ($section === 'customers-edit' && $id > 0) { if (($_POST['confirm_update'] ?? '') !== 'yes') { flash('error', 'Confirm Yes before updating the customer.'); redirect('index.php?page=admin&section=customers-edit&id=' . $id); } $input = customer_input(); $errors = validate_customer($input); if (!$errors) { $statement = database()->prepare('UPDATE customers SET customer_type=:type,name=:name,contact_number=:contact,secondary_contact_number=:secondary,email=:email,nic_or_registration=:nic,address=:address,notes=:notes,status=:status WHERE id=:id'); $statement->execute(['type'=>$input['customer_type'],'name'=>$input['name'],'contact'=>$input['contact_number'],'secondary'=>$input['secondary_contact_number'] ?: null,'email'=>$input['email'] ?: null,'nic'=>$input['nic_or_registration'] ?: null,'address'=>$input['address'],'notes'=>$input['notes'] ?: null,'status'=>$input['status'],'id'=>$id]); flash('success','Customer updated successfully.'); redirect('index.php?page=admin&section=customers-edit&id=' . $id); } }
        elseif ($section === 'customers-deactivate' && $id > 0) { if (($_POST['confirm_status'] ?? '') !== 'yes') { flash('error', 'Confirm Yes before changing the customer status.'); redirect('index.php?page=admin&section=customers'); } $statement = database()->prepare("UPDATE customers SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id"); $statement->execute(['id'=>$id]); flash('success','Customer status updated.'); redirect('index.php?page=admin&section=customers'); }
    }
    if ($section === 'customers-view') render_customer_modal_page($filters ?? customer_filters(), $id, $errors); else render_customer_page($section, $id, $errors);
}

function render_customer_modal_page(array $filters, int $id, array $errors): void
{
    $customer = customer_by_id($id);
    if (!$customer) { http_response_code(404); exit('Customer not found.'); }
    $vehicles = customer_vehicles($id); $title = 'All Customers'; $section = 'customers-view';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/customers.php';
    require __DIR__ . '/../views/customer-modal.php';
    require __DIR__ . '/../includes/footer.php';
}

function render_customer_page(string $section, int $id, array $errors): void
{
    $filters = customer_filters(); $customer = $id ? customer_by_id($id) : null; if (in_array($section, ['customers-view','customers-edit'], true) && !$customer) { http_response_code(404); exit('Customer not found.'); } $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? customer_input() : ($customer ?: customer_input()); $title = $section === 'customers-add' ? 'Add Customer' : ($section === 'customers-view' ? 'View Customer' : ($section === 'customers-edit' ? 'Edit Customer' : 'All Customers')); require __DIR__ . '/../includes/header.php'; if (in_array($section, ['customers-view','customers-edit','customers-add'], true)) require __DIR__ . '/../views/customer-form.php'; else require __DIR__ . '/../views/customers.php'; require __DIR__ . '/../includes/footer.php';
}
