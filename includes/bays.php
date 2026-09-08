<?php

declare(strict_types=1);

function ensure_bays_table(): void
{
    static $ready = false;
    if ($ready) return;
    ensure_employee_tables();
    database()->exec("CREATE TABLE IF NOT EXISTS bays (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, bay_name VARCHAR(80) NOT NULL, status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active', assigned_employee_id BIGINT UNSIGNED NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY bays_name_unique (bay_name), KEY bays_status_index (status), CONSTRAINT bays_employee_fk FOREIGN KEY (assigned_employee_id) REFERENCES employees (id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function bay_options(): array
{
    return database()->query("SELECT id, name, employee_code, role_position FROM employees WHERE status = 'active' ORDER BY name")->fetchAll();
}

function bay_record(int $id): ?array
{
    $statement = database()->prepare('SELECT b.*, e.name AS employee_name, e.employee_code, e.role_position FROM bays b LEFT JOIN employees e ON e.id = b.assigned_employee_id WHERE b.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function bay_rows(): array
{
    $hasJobCards = (bool) database()->query("SHOW TABLES LIKE 'job_cards'")->fetchColumn();
    $jobColumns = $hasJobCards ? ', jc.job_card_no, jc.status AS job_status, v.vehicle_number' : ", NULL AS job_card_no, NULL AS job_status, NULL AS vehicle_number";
    $jobJoins = $hasJobCards ? " LEFT JOIN job_cards jc ON jc.bay_name = b.bay_name AND jc.status IN ('pending','ongoing') LEFT JOIN vehicles v ON v.id = jc.vehicle_id" : '';
    $groupColumns = $hasJobCards ? ', jc.job_card_no, jc.status, v.vehicle_number' : '';
    $sql = "SELECT b.id, b.bay_name, b.status, b.assigned_employee_id, b.notes, b.created_at, b.updated_at, e.name AS employee_name, e.employee_code{$jobColumns} FROM bays b LEFT JOIN employees e ON e.id = b.assigned_employee_id{$jobJoins} GROUP BY b.id, b.bay_name, b.status, b.assigned_employee_id, b.notes, b.created_at, b.updated_at, e.name, e.employee_code{$groupColumns} ORDER BY b.id ASC";
    return database()->query($sql)->fetchAll();
}

function bay_input(?array $source = null): array
{
    $source ??= $_POST;
    return ['bay_name' => trim((string) ($source['bay_name'] ?? '')), 'status' => in_array($source['status'] ?? '', ['active', 'inactive', 'maintenance'], true) ? $source['status'] : 'active', 'assigned_employee_id' => max(0, (int) ($source['assigned_employee_id'] ?? 0)), 'notes' => trim((string) ($source['notes'] ?? ''))];
}

function validate_bay(array $input, int $id = 0): array
{
    $errors = [];
    if ($input['bay_name'] === '') $errors[] = 'Bay name or number is required.';
    if ($input['assigned_employee_id'] > 0) {
        $statement = database()->prepare("SELECT id FROM employees WHERE id = :id AND status = 'active'");
        $statement->execute(['id' => $input['assigned_employee_id']]);
        if (!$statement->fetchColumn()) $errors[] = 'Select an active employee for this bay.';
    }
    $statement = database()->prepare('SELECT id FROM bays WHERE bay_name = :bay_name AND id <> :id LIMIT 1');
    $statement->execute(['bay_name' => $input['bay_name'], 'id' => $id]);
    if ($statement->fetchColumn()) $errors[] = 'This bay name or number already exists.';
    return $errors;
}

function handle_bay_request(string $section): void
{
    ensure_bays_table();
    $id = max(0, (int) ($_GET['id'] ?? $_POST['bay_id'] ?? 0));
    $errors = [];
    $input = bay_input();
    if ($section === 'bays-edit') {
        $bay = bay_record($id);
        if (!$bay) { http_response_code(404); exit('Bay not found.'); }
        $input = bay_input($bay);
    }
    if ($section === 'bays-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $statement = database()->prepare('DELETE FROM bays WHERE id = :id');
        $statement->execute(['id' => $id]);
        flash('success', 'Bay deleted successfully.');
        redirect('index.php?page=admin&section=bays');
    }
    if (in_array($section, ['bays-add', 'bays-edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $input = bay_input();
        $errors = validate_bay($input, $section === 'bays-edit' ? $id : 0);
        if (!$errors) {
            $values = ['bay_name' => $input['bay_name'], 'status' => $input['status'], 'assigned_employee_id' => $input['assigned_employee_id'] ?: null, 'notes' => $input['notes'] ?: null];
            if ($section === 'bays-add') {
                $statement = database()->prepare('INSERT INTO bays (bay_name, status, assigned_employee_id, notes) VALUES (:bay_name, :status, :assigned_employee_id, :notes)');
                $statement->execute($values);
                flash('success', 'Bay added successfully.');
            } else {
                $values['id'] = $id;
                $statement = database()->prepare('UPDATE bays SET bay_name = :bay_name, status = :status, assigned_employee_id = :assigned_employee_id, notes = :notes WHERE id = :id');
                $statement->execute($values);
                flash('success', 'Bay updated successfully.');
            }
            redirect('index.php?page=admin&section=bays');
        }
    }
    if (in_array($section, ['bays-add', 'bays-edit'], true)) {
        $options = bay_options();
        $title = $section === 'bays-edit' ? 'Edit Bay' : 'Add Bay';
        $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php';
        require __DIR__ . '/../views/bay-form-modal.php';
        require __DIR__ . '/../includes/footer.php';
        return;
    }
    $rows = bay_rows();
    $title = 'Bay Management';
    $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/bays.php';
    require __DIR__ . '/../includes/footer.php';
}
