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
    if ($section === 'vehicles-view') { $vehicle = vehicle_by_id($id); if (!$vehicle) { http_response_code(404); exit('Vehicle not found.'); } redirect('index.php?page=admin&section=customers-view&id=' . (int) $vehicle['customer_id']); }
    if ($section === 'vehicles-edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') { $existing = vehicle_by_id($id); if (!$existing) { http_response_code(404); exit('Vehicle not found.'); } $input = vehicle_input_record($existing); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-add') { verify_csrf(); $errors = validate_vehicle_record($input); if (!$errors) { $statement = database()->prepare('INSERT INTO vehicles (customer_id,vehicle_number,vehicle_type,make,model,year,colour,engine_number,chassis_number,fuel_type,current_mileage,notes) VALUES (:customer_id,:vehicle_number,:vehicle_type,:make,:model,:year,:colour,:engine_number,:chassis_number,:fuel_type,:current_mileage,:notes)'); $statement->execute(['customer_id'=>$input['customer_id'],'vehicle_number'=>$input['vehicle_number'],'vehicle_type'=>$input['vehicle_type'],'make'=>$input['make'] ?: null,'model'=>$input['model'],'year'=>$input['year'] !== '' ? $input['year'] : null,'colour'=>$input['colour'] ?: null,'engine_number'=>$input['engine_number'] ?: null,'chassis_number'=>$input['chassis_number'] ?: null,'fuel_type'=>$input['fuel_type'] ?: null,'current_mileage'=>$input['current_mileage'],'notes'=>$input['notes'] ?: null]); flash('success','Vehicle added successfully.'); redirect('index.php?page=admin&section=vehicles'); } }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-edit') { verify_csrf(); $errors = validate_vehicle_record($input); if (!$errors) { $statement = database()->prepare('UPDATE vehicles SET customer_id=:customer_id,vehicle_number=:vehicle_number,vehicle_type=:vehicle_type,make=:make,model=:model,year=:year,colour=:colour,engine_number=:engine_number,chassis_number=:chassis_number,fuel_type=:fuel_type,current_mileage=:current_mileage,notes=:notes WHERE id=:id'); $statement->execute(['customer_id'=>$input['customer_id'],'vehicle_number'=>$input['vehicle_number'],'vehicle_type'=>$input['vehicle_type'],'make'=>$input['make'] ?: null,'model'=>$input['model'],'year'=>$input['year'] !== '' ? $input['year'] : null,'colour'=>$input['colour'] ?: null,'engine_number'=>$input['engine_number'] ?: null,'chassis_number'=>$input['chassis_number'] ?: null,'fuel_type'=>$input['fuel_type'] ?: null,'current_mileage'=>$input['current_mileage'],'notes'=>$input['notes'] ?: null,'id'=>$id]); flash('success','Vehicle updated successfully.'); redirect('index.php?page=admin&section=vehicles'); } }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'vehicles-remove') { verify_csrf(); $statement = database()->prepare("UPDATE vehicles SET status = 'inactive' WHERE id = :id"); $statement->execute(['id'=>$id]); flash('success','Vehicle removed from the active list.'); redirect('index.php?page=admin&section=vehicles'); }
    $total = vehicle_list($filters, true); $rows = vehicle_list($filters); $pages = max(1, (int) ceil($total / $filters['per_page'])); $customers = vehicle_customers(); $title = $section === 'vehicles-add' ? 'Add Vehicle' : 'All Vehicles';
    require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/' . (in_array($section, ['vehicles-add', 'vehicles-edit'], true) ? 'vehicle-add.php' : 'vehicles.php'); require __DIR__ . '/../includes/footer.php';
}
