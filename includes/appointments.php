<?php

declare(strict_types=1);

function ensure_appointments_table(): void
{
    static $ready = false;
    if ($ready) return;
    ensure_customers_table();
    ensure_vehicles_table();
    ensure_vehicle_columns();
    database()->exec("CREATE TABLE IF NOT EXISTS appointments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, appointment_no VARCHAR(30) NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, vehicle_id BIGINT UNSIGNED NULL, appointment_date DATE NOT NULL, appointment_time TIME NOT NULL, requested_service VARCHAR(190) NOT NULL, status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending', notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY appointments_no_unique (appointment_no), KEY appointments_customer_index (customer_id), KEY appointments_vehicle_index (vehicle_id), KEY appointments_date_index (appointment_date), KEY appointments_status_index (status), CONSTRAINT appointments_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE, CONSTRAINT appointments_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function close_expired_appointments(): void
{
    database()->exec("UPDATE appointments SET status = 'cancelled' WHERE status IN ('pending','confirmed','no_show') AND appointment_date < CURRENT_DATE");
}

function appointment_customers(): array
{
    return database()->query("SELECT id, name, customer_code, contact_number FROM customers WHERE status = 'active' ORDER BY name")->fetchAll();
}

function appointment_vehicles(): array
{
    return database()->query("SELECT v.id, v.customer_id, v.vehicle_number, v.make, v.model, c.name AS customer_name FROM vehicles v JOIN customers c ON c.id = v.customer_id WHERE c.status = 'active' AND COALESCE(v.status, 'active') = 'active' ORDER BY v.vehicle_number")->fetchAll();
}

function appointment_record(int $id): ?array
{
    $statement = database()->prepare('SELECT a.*, c.name AS customer_name, c.customer_code, c.contact_number, v.vehicle_number, v.make, v.model FROM appointments a JOIN customers c ON c.id = a.customer_id LEFT JOIN vehicles v ON v.id = a.vehicle_id WHERE a.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function appointment_filters(): array
{
    $statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
    $validDate = static fn ($value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? (string) $value : '';
    return ['search' => trim((string) ($_GET['search'] ?? '')), 'status' => in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '', 'from' => $validDate($_GET['from'] ?? ''), 'to' => $validDate($_GET['to'] ?? '')];
}

function appointment_rows(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['search'] !== '') { $where[] = '(a.appointment_no LIKE :search OR c.name LIKE :search OR c.contact_number LIKE :search OR v.vehicle_number LIKE :search OR a.requested_service LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
    if ($filters['status'] !== '') { $where[] = 'a.status = :status'; $params['status'] = $filters['status']; }
    if ($filters['from'] !== '') { $where[] = 'a.appointment_date >= :date_from'; $params['date_from'] = $filters['from']; }
    if ($filters['to'] !== '') { $where[] = 'a.appointment_date <= :date_to'; $params['date_to'] = $filters['to']; }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $statement = database()->prepare("SELECT a.*, c.name AS customer_name, c.contact_number, v.vehicle_number, v.make, v.model FROM appointments a JOIN customers c ON c.id = a.customer_id LEFT JOIN vehicles v ON v.id = a.vehicle_id{$whereSql} ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC");
    $statement->execute($params);
    return $statement->fetchAll();
}

function appointment_summary(): array
{
    $row = database()->query("SELECT COUNT(*) AS total, SUM(appointment_date = CURRENT_DATE AND status NOT IN ('cancelled','no_show')) AS today, SUM(status = 'pending') AS pending, SUM(status = 'confirmed') AS confirmed, SUM(status = 'cancelled') AS cancelled FROM appointments")->fetch() ?: [];
    return ['total' => (int) ($row['total'] ?? 0), 'today' => (int) ($row['today'] ?? 0), 'pending' => (int) ($row['pending'] ?? 0), 'confirmed' => (int) ($row['confirmed'] ?? 0), 'cancelled' => (int) ($row['cancelled'] ?? 0)];
}

function appointment_input(?array $source = null): array
{
    $source ??= $_POST;
    $time = trim((string) ($source['appointment_time'] ?? '09:00'));
    return ['customer_id' => max(0, (int) ($source['customer_id'] ?? 0)), 'vehicle_id' => max(0, (int) ($source['vehicle_id'] ?? 0)), 'appointment_date' => trim((string) ($source['appointment_date'] ?? date('Y-m-d'))), 'appointment_time' => substr($time, 0, 5), 'requested_service' => trim((string) ($source['requested_service'] ?? '')), 'status' => in_array($source['status'] ?? '', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'], true) ? $source['status'] : 'pending', 'notes' => trim((string) ($source['notes'] ?? ''))];
}

function validate_appointment(array $input): array
{
    $errors = [];
    $date = DateTime::createFromFormat('Y-m-d', $input['appointment_date']);
    if ($input['customer_id'] < 1 || !database()->query('SELECT id FROM customers WHERE id = ' . (int) $input['customer_id'] . " AND status = 'active'")->fetchColumn()) $errors[] = 'Select an active customer.';
    if (!$date || $date->format('Y-m-d') !== $input['appointment_date']) $errors[] = 'Select a valid appointment date.';
    if (!preg_match('/^\d{2}:\d{2}$/', $input['appointment_time'])) $errors[] = 'Enter a valid appointment time.';
    if ($input['requested_service'] === '') $errors[] = 'Requested service is required.';
    if ($input['vehicle_id'] > 0) {
        $statement = database()->prepare('SELECT id FROM vehicles WHERE id = :id AND customer_id = :customer_id');
        $statement->execute(['id' => $input['vehicle_id'], 'customer_id' => $input['customer_id']]);
        if (!$statement->fetchColumn()) $errors[] = 'Select a vehicle belonging to the selected customer.';
    }
    return $errors;
}

function handle_appointment_request(string $section): void
{
    ensure_appointments_table();
    close_expired_appointments();
    $id = max(0, (int) ($_GET['id'] ?? $_POST['appointment_id'] ?? 0));
    $errors = [];
    $input = appointment_input();
    if (in_array($section, ['appointments-edit', 'appointments-view'], true)) {
        $appointment = appointment_record($id);
        if (!$appointment) { http_response_code(404); exit('Appointment not found.'); }
        if ($section === 'appointments-view') {
            $title = 'View Appointment'; $sectionForHeader = $section;
            require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/appointment-view-modal.php'; require __DIR__ . '/../includes/footer.php'; return;
        }
        $input = appointment_input($appointment);
    }
    if ($section === 'appointments-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $statement = database()->prepare('DELETE FROM appointments WHERE id = :id'); $statement->execute(['id' => $id]);
        flash('success', 'Appointment deleted successfully.'); redirect('index.php?page=admin&section=appointments');
    }
    if (in_array($section, ['appointments-add', 'appointments-edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $input = appointment_input(); $errors = validate_appointment($input);
        if (!$errors) {
            $pdo = database(); $values = ['customer_id' => $input['customer_id'], 'vehicle_id' => $input['vehicle_id'] ?: null, 'appointment_date' => $input['appointment_date'], 'appointment_time' => $input['appointment_time'] . ':00', 'requested_service' => $input['requested_service'], 'status' => $input['status'], 'notes' => $input['notes'] ?: null];
            if ($section === 'appointments-add') {
                $statement = $pdo->prepare('INSERT INTO appointments (appointment_no, customer_id, vehicle_id, appointment_date, appointment_time, requested_service, status, notes) VALUES (:appointment_no, :customer_id, :vehicle_id, :appointment_date, :appointment_time, :requested_service, :status, :notes)');
                $statement->execute(array_merge(['appointment_no' => 'TMP-' . bin2hex(random_bytes(4))], $values)); $newId = (int) $pdo->lastInsertId();
                $statement = $pdo->prepare('UPDATE appointments SET appointment_no = :appointment_no WHERE id = :id'); $statement->execute(['appointment_no' => 'APP-' . date('ymd') . '-' . str_pad((string) $newId, 4, '0', STR_PAD_LEFT), 'id' => $newId]);
                flash('success', 'Appointment added successfully.');
            } else {
                $values['id'] = $id; $statement = $pdo->prepare('UPDATE appointments SET customer_id = :customer_id, vehicle_id = :vehicle_id, appointment_date = :appointment_date, appointment_time = :appointment_time, requested_service = :requested_service, status = :status, notes = :notes WHERE id = :id'); $statement->execute($values); flash('success', 'Appointment updated successfully.');
            }
            redirect('index.php?page=admin&section=appointments');
        }
    }
    if (in_array($section, ['appointments-add', 'appointments-edit'], true)) {
        $customers = appointment_customers(); $vehicles = appointment_vehicles(); $title = $section === 'appointments-edit' ? 'Edit Appointment' : 'Add Appointment'; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/appointment-form-modal.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    $filters = appointment_filters(); $rows = appointment_rows($filters); $summary = appointment_summary(); $title = 'Appointments'; $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/appointments.php'; require __DIR__ . '/../includes/footer.php';
}
