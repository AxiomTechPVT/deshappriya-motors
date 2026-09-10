<?php

declare(strict_types=1);

function ensure_vehicle_columns(): void
{
    $pdo = database();
    $columns = $pdo->query('SHOW COLUMNS FROM vehicles')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('year', $columns, true)) $pdo->exec('ALTER TABLE vehicles ADD COLUMN year SMALLINT UNSIGNED NULL AFTER vehicle_type');
    if (!in_array('fuel_type', $columns, true)) $pdo->exec('ALTER TABLE vehicles ADD COLUMN fuel_type VARCHAR(30) NULL AFTER year');
    if (!in_array('status', $columns, true)) $pdo->exec("ALTER TABLE vehicles ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER fuel_type");
}

function vehicle_filters(): array
{
    $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
    return ['search'=>trim((string) ($_GET['search'] ?? '')), 'type'=>trim((string) ($_GET['type'] ?? '')), 'status'=>in_array($_GET['status'] ?? '', ['active','inactive'], true) ? $_GET['status'] : '', 'per_page'=>in_array($perPage, [5,10,25,50], true) ? $perPage : 5, 'page'=>max(1, (int) ($_GET['p'] ?? 1))];
}

function vehicle_input_record(array $input): array
{
    return ['customer_id'=>(int) ($input['customer_id'] ?? 0), 'vehicle_number'=>trim((string) ($input['vehicle_number'] ?? '')), 'vehicle_type'=>trim((string) ($input['vehicle_type'] ?? '')), 'make'=>trim((string) ($input['make'] ?? '')), 'model'=>trim((string) ($input['model'] ?? '')), 'year'=>trim((string) ($input['year'] ?? '')), 'colour'=>trim((string) ($input['colour'] ?? '')), 'engine_number'=>trim((string) ($input['engine_number'] ?? '')), 'chassis_number'=>trim((string) ($input['chassis_number'] ?? '')), 'fuel_type'=>trim((string) ($input['fuel_type'] ?? '')), 'current_mileage'=>trim((string) ($input['current_mileage'] ?? '')), 'notes'=>trim((string) ($input['notes'] ?? ''))];
}

function validate_vehicle_record(array $vehicle): array
{
    $errors = [];
    if ($vehicle['customer_id'] < 1) $errors[] = 'Select a customer.';
    if ($vehicle['vehicle_number'] === '') $errors[] = 'Vehicle number is required.';
    if ($vehicle['vehicle_type'] === '') $errors[] = 'Vehicle type is required.';
    if ($vehicle['model'] === '') $errors[] = 'Model is required.';
    if ($vehicle['current_mileage'] === '' || !is_numeric($vehicle['current_mileage']) || (float) $vehicle['current_mileage'] < 0) $errors[] = 'Current mileage must be a positive number.';
    if ($vehicle['year'] !== '' && (!ctype_digit($vehicle['year']) || (int) $vehicle['year'] < 1900 || (int) $vehicle['year'] > ((int) date('Y') + 1))) $errors[] = 'Enter a valid vehicle year.';
    return $errors;
}

function vehicle_customers(): array { return database()->query("SELECT id, name, contact_number FROM customers WHERE status = 'active' ORDER BY name ASC")->fetchAll(); }
function vehicle_by_id(int $id): ?array { $statement = database()->prepare('SELECT * FROM vehicles WHERE id = :id LIMIT 1'); $statement->execute(['id'=>$id]); return $statement->fetch() ?: null; }

function vehicle_history_record(int $id): ?array
{
    $jobSearch = trim((string) ($_GET['job_search'] ?? ''));
    $itemSearch = trim((string) ($_GET['item_search'] ?? ''));
    $statement = database()->prepare('SELECT v.*, c.name AS customer_name, c.customer_code, c.contact_number AS customer_contact, c.email AS customer_email FROM vehicles v JOIN customers c ON c.id = v.customer_id WHERE v.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $vehicle = $statement->fetch();
    if (!$vehicle) return null;

    $jobWhere = ['j.vehicle_id = :vehicle_id'];
    $jobParams = ['vehicle_id' => $id];
    if ($jobSearch !== '') {
        $jobWhere[] = '(j.job_card_no LIKE :job_search OR j.complaint LIKE :job_search OR j.requested_work LIKE :job_search OR e.name LIKE :job_search)';
        $jobParams['job_search'] = '%' . $jobSearch . '%';
    }
    $jobs = database()->prepare('SELECT j.id, j.job_card_no, j.status, j.complaint, j.requested_work, j.total_amount, j.paid_amount, j.balance_amount, j.created_at, j.completed_at, e.name AS mechanic_name, p.start_time, p.end_time FROM job_cards j LEFT JOIN employees e ON e.id = j.mechanic_id LEFT JOIN job_card_performance p ON p.job_card_id = j.id WHERE ' . implode(' AND ', $jobWhere) . ' ORDER BY j.id DESC');
    $jobs->execute($jobParams);
    $vehicle['jobs'] = $jobs->fetchAll();

    $itemWhere = ['j.vehicle_id = :vehicle_id'];
    $itemParams = ['vehicle_id' => $id];
    if ($itemSearch !== '') {
        $itemWhere[] = '(ji.item_name LIKE :item_search OR ji.item_code LIKE :item_search OR j.job_card_no LIKE :item_search)';
        $itemParams['item_search'] = '%' . $itemSearch . '%';
    }
    $items = database()->prepare('SELECT j.job_card_no, j.created_at AS job_date, ji.item_type, ji.item_name, ji.item_code, ji.quantity, ji.unit_price, ji.amount FROM job_card_items ji JOIN job_cards j ON j.id = ji.job_card_id WHERE ' . implode(' AND ', $itemWhere) . ' ORDER BY ji.id DESC');
    $items->execute($itemParams);
    $vehicle['items'] = $items->fetchAll();

    $externalWhere = ['ep.vehicle_id = :vehicle_id', 'e.status <> "cancelled"'];
    $externalParams = ['vehicle_id' => $id];
    if ($itemSearch !== '') {
        $externalWhere[] = '(ep.part_name LIKE :external_search OR ep.part_code LIKE :external_search OR e.expense_no LIKE :external_search)';
        $externalParams['external_search'] = '%' . $itemSearch . '%';
    }
    $externalParts = database()->prepare('SELECT e.expense_no, e.expense_date, e.status, ep.part_name, ep.part_code, ep.quantity, ep.unit_cost, ep.selling_price, ep.total_cost FROM expense_external_parts ep JOIN expenses e ON e.id = ep.expense_id WHERE ' . implode(' AND ', $externalWhere) . ' ORDER BY ep.id DESC');
    $externalParts->execute($externalParams);
    $vehicle['external_parts'] = $externalParts->fetchAll();

    $invoices = database()->prepare('SELECT i.id, i.invoice_no, i.invoice_date, i.total_amount, i.paid_amount, i.balance_amount, i.payment_status FROM invoices i WHERE i.vehicle_id = :vehicle_id ORDER BY i.id DESC');
    $invoices->execute(['vehicle_id' => $id]);
    $vehicle['invoices'] = $invoices->fetchAll();

    $vehicle['job_count'] = count($vehicle['jobs']);
    $vehicle['invoice_count'] = count($vehicle['invoices']);
    $vehicle['item_count'] = count($vehicle['items']) + count($vehicle['external_parts']);
    $vehicle['total_billed'] = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], $vehicle['invoices']));
    $vehicle['job_search'] = $jobSearch;
    $vehicle['item_search'] = $itemSearch;
    return $vehicle;
}

function vehicle_list(array $filters, bool $count = false): array|int
{
    $params = []; $where = [];
    if ($filters['search'] !== '') { $where[] = '(v.vehicle_number LIKE :search OR v.make LIKE :search OR v.model LIKE :search OR c.name LIKE :search OR c.contact_number LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
    if ($filters['type'] !== '') { $where[] = 'v.vehicle_type = :type'; $params['type'] = $filters['type']; }
    if ($filters['status'] !== '') { $where[] = 'v.status = :status'; $params['status'] = $filters['status']; }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $sql = $count ? "SELECT COUNT(*) FROM vehicles v INNER JOIN customers c ON c.id = v.customer_id{$whereSql}" : "SELECT v.*, c.name customer_name, c.contact_number customer_contact FROM vehicles v INNER JOIN customers c ON c.id = v.customer_id{$whereSql} ORDER BY v.id DESC LIMIT :limit OFFSET :offset";
    $statement = database()->prepare($sql); foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value); if (!$count) { $statement->bindValue(':limit', (int) $filters['per_page'], PDO::PARAM_INT); $statement->bindValue(':offset', ($filters['page'] - 1) * $filters['per_page'], PDO::PARAM_INT); } $statement->execute(); return $count ? (int) $statement->fetchColumn() : $statement->fetchAll();
}

function handle_vehicle_request(string $section): void
{
    ensure_customers_table(); ensure_vehicles_table(); ensure_vehicle_columns(); $filters = vehicle_filters(); $id = max(0, (int) ($_GET['id'] ?? 0)); $errors = []; $input = vehicle_input_record($_POST);
    if ($section === 'vehicles-view') {
        ensure_job_card_tables();
        $vehicle = vehicle_history_record($id);
        if (!$vehicle) { http_response_code(404); exit('Vehicle not found.'); }
        $title = 'Vehicle History';
        require __DIR__ . '/../includes/header.php';
        require __DIR__ . '/../views/vehicle-history.php';
        require __DIR__ . '/../includes/footer.php';
        return;
    }
    if ($section === 'vehicles-edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') { $existing = vehicle_by_id($id); if (!$existing) { http_response_code(404); exit('Vehicle not found.'); } $input = vehicle_input_record($existing); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-add') { verify_csrf(); $errors = validate_vehicle_record($input); if (!$errors) { $statement = database()->prepare('INSERT INTO vehicles (customer_id,vehicle_number,vehicle_type,make,model,year,colour,engine_number,chassis_number,fuel_type,current_mileage,notes) VALUES (:customer_id,:vehicle_number,:vehicle_type,:make,:model,:year,:colour,:engine_number,:chassis_number,:fuel_type,:current_mileage,:notes)'); $statement->execute(['customer_id'=>$input['customer_id'],'vehicle_number'=>$input['vehicle_number'],'vehicle_type'=>$input['vehicle_type'],'make'=>$input['make'] ?: null,'model'=>$input['model'],'year'=>$input['year'] !== '' ? $input['year'] : null,'colour'=>$input['colour'] ?: null,'engine_number'=>$input['engine_number'] ?: null,'chassis_number'=>$input['chassis_number'] ?: null,'fuel_type'=>$input['fuel_type'] ?: null,'current_mileage'=>$input['current_mileage'],'notes'=>$input['notes'] ?: null]); flash('success','Vehicle added successfully.'); redirect('index.php?page=admin&section=vehicles'); } }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-edit') { verify_csrf(); if (($_POST['confirm_update'] ?? '') !== 'yes') { flash('error', 'Confirm Yes before updating the vehicle.'); redirect('index.php?page=admin&section=vehicles-edit&id=' . $id); } $errors = validate_vehicle_record($input); if (!$errors) { $statement = database()->prepare('UPDATE vehicles SET customer_id=:customer_id,vehicle_number=:vehicle_number,vehicle_type=:vehicle_type,make=:make,model=:model,year=:year,colour=:colour,engine_number=:engine_number,chassis_number=:chassis_number,fuel_type=:fuel_type,current_mileage=:current_mileage,notes=:notes WHERE id=:id'); $statement->execute(['customer_id'=>$input['customer_id'],'vehicle_number'=>$input['vehicle_number'],'vehicle_type'=>$input['vehicle_type'],'make'=>$input['make'] ?: null,'model'=>$input['model'],'year'=>$input['year'] !== '' ? $input['year'] : null,'colour'=>$input['colour'] ?: null,'engine_number'=>$input['engine_number'] ?: null,'chassis_number'=>$input['chassis_number'] ?: null,'fuel_type'=>$input['fuel_type'] ?: null,'current_mileage'=>$input['current_mileage'],'notes'=>$input['notes'] ?: null,'id'=>$id]); flash('success','Vehicle updated successfully.'); redirect('index.php?page=admin&section=vehicles-edit&id=' . $id); } }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-remove') { verify_csrf(); if (($_POST['confirm_remove'] ?? '') !== 'yes') { flash('error', 'Confirm Yes before removing the vehicle.'); redirect('index.php?page=admin&section=vehicles'); } $statement = database()->prepare("UPDATE vehicles SET status = 'inactive' WHERE id = :id"); $statement->execute(['id'=>$id]); flash('success','Vehicle removed from the active list.'); redirect('index.php?page=admin&section=vehicles'); }
    $total = vehicle_list($filters, true); $rows = vehicle_list($filters); $pages = max(1, (int) ceil($total / $filters['per_page'])); $customers = vehicle_customers(); $title = $section === 'vehicles-add' ? 'Add Vehicle' : ($section === 'vehicles-edit' ? 'Edit Vehicle' : 'All Vehicles');
    require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/' . (in_array($section, ['vehicles-add', 'vehicles-edit'], true) ? 'vehicle-add.php' : 'vehicles.php'); require __DIR__ . '/../includes/footer.php';
}
