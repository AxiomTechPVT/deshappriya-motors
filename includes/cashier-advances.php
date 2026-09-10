<?php
declare(strict_types=1);

function ensure_cashier_advance_table(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS cashier_advance_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cashier_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        advance_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        reason VARCHAR(255) NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        disbursement_status ENUM('not_given','given') NOT NULL DEFAULT 'not_given',
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        review_note VARCHAR(255) NULL,
        given_by BIGINT UNSIGNED NULL,
        given_at DATETIME NULL,
        receipt_no VARCHAR(40) NULL,
        PRIMARY KEY (id), KEY cashier_advance_requests_cashier_index (cashier_id), KEY cashier_advance_requests_employee_index (employee_id),
        KEY cashier_advance_requests_date_index (advance_date), KEY cashier_advance_requests_status_index (status), UNIQUE KEY cashier_advance_requests_receipt_unique (receipt_no),
        CONSTRAINT cashier_advance_requests_cashier_fk FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT cashier_advance_requests_employee_fk FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT cashier_advance_requests_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT cashier_advance_requests_given_by_fk FOREIGN KEY (given_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        "ALTER TABLE cashier_advance_requests ADD COLUMN employee_id BIGINT UNSIGNED NULL AFTER cashier_id",
        "ALTER TABLE cashier_advance_requests MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
        "ALTER TABLE cashier_advance_requests ADD COLUMN disbursement_status ENUM('not_given','given') NOT NULL DEFAULT 'not_given' AFTER status",
        "ALTER TABLE cashier_advance_requests ADD COLUMN given_by BIGINT UNSIGNED NULL AFTER review_note",
        "ALTER TABLE cashier_advance_requests ADD COLUMN given_at DATETIME NULL AFTER given_by"
        ,"ALTER TABLE cashier_advance_requests ADD COLUMN receipt_no VARCHAR(40) NULL AFTER given_at"
    ] as $sql) { try { database()->exec($sql); } catch (Throwable $ignored) {} }
    $ready = true;
}

function cashier_advance_requests_for_date(string $date, int $cashierId): array
{
    ensure_cashier_advance_table();
    $statement = database()->prepare("SELECT CONCAT('CADV-', r.id) reference_no, r.requested_at AS happened_at, r.amount,
        CONCAT('Employee advance - ', e.name) description FROM cashier_advance_requests r JOIN employees e ON e.id=r.employee_id
        WHERE r.advance_date=:date AND r.cashier_id=:cashier AND r.status='approved' AND r.disbursement_status='given' ORDER BY r.requested_at, r.id");
    $statement->execute(['date' => $date, 'cashier' => $cashierId]);
    return $statement->fetchAll();
}

function handle_cashier_advance_request(string $section): void
{
    ensure_cashier_advance_table();
    $user = current_user();
    $isAdmin = ($user['role'] ?? '') === 'administrator';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['advance_request_action'] ?? '');
        $id = (int) ($_POST['request_id'] ?? 0);
        if ($isAdmin && in_array($action, ['approve', 'reject'], true) && $id > 0) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $statement = database()->prepare("UPDATE cashier_advance_requests SET status=:status, reviewed_by=:reviewer,
                reviewed_at=NOW(), review_note=:note WHERE id=:id AND status='pending'");
            $statement->execute(['status' => $status, 'reviewer' => (int) $user['id'], 'note' => trim((string) ($_POST['review_note'] ?? '')) ?: null, 'id' => $id]);
            flash($statement->rowCount() ? 'success' : 'error', $statement->rowCount() ? 'Advance request updated.' : 'Request was already reviewed.');
            redirect('index.php?page=admin&section=cashier-advance-requests');
        }
        if (in_array($action, ['mark_given', 'give'], true) && $id > 0) {
            $receiptNo = 'ADV-' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(2)));
            $statement = database()->prepare("UPDATE cashier_advance_requests SET disbursement_status='given', given_by=:given_by, given_at=NOW(), receipt_no=:receipt WHERE id=:id AND status='approved' AND disbursement_status='not_given'");
            $statement->execute(['given_by' => (int) $user['id'], 'receipt' => $receiptNo, 'id' => $id]);
            if ($statement->rowCount()) redirect('index.php?page=admin&section=cashier-advance-requests&advance_receipt_id=' . $id);
            flash('error', 'Only approved advances can be given.');
            redirect('index.php?page=admin&section=cashier-advance-requests');
        }
        if ($isAdmin && $action === 'update' && $id > 0) {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $date = trim((string) ($_POST['advance_date'] ?? ''));
            $status = (string) ($_POST['status'] ?? 'pending');
            $dateObject = DateTime::createFromFormat('Y-m-d', $date);
            $employeeCheck = database()->prepare("SELECT id FROM employees WHERE id=:id AND status='active' LIMIT 1");
            $employeeCheck->execute(['id' => $employeeId]);
            if (!$employeeCheck->fetch()) $errors[] = 'Select a valid employee.';
            if ($amount <= 0) $errors[] = 'Advance amount must be greater than zero.';
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date) $errors[] = 'Select a valid date.';
            if (!in_array($status, ['pending', 'approved', 'rejected'], true)) $errors[] = 'Select a valid status.';
            if (!$errors) {
                $editableCheck = database()->prepare("SELECT id FROM cashier_advance_requests WHERE id=:id AND disbursement_status='not_given' LIMIT 1");
                $editableCheck->execute(['id' => $id]);
                if (!$editableCheck->fetch()) $errors[] = 'Given advances cannot be edited.';
            }
            if (!$errors) {
                $statement = database()->prepare("UPDATE cashier_advance_requests SET employee_id=:employee, advance_date=:date, amount=:amount, reason=:reason, status=:status, disbursement_status='not_given', given_by=NULL, given_at=NULL, receipt_no=NULL WHERE id=:id AND disbursement_status='not_given'");
                $statement->execute(['employee' => $employeeId, 'date' => $date, 'amount' => $amount, 'reason' => trim((string) ($_POST['reason'] ?? '')) ?: null, 'status' => $status, 'id' => $id]);
                flash('success', 'Advance request updated.');
                redirect('index.php?page=admin&section=cashier-advance-requests');
            }
        }
        if (!$isAdmin && $action === 'create') {
            $amount = (float) ($_POST['amount'] ?? 0);
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $date = trim((string) ($_POST['advance_date'] ?? date('Y-m-d')));
            $dateObject = DateTime::createFromFormat('Y-m-d', $date);
            if ($amount <= 0) $errors[] = 'Advance amount must be greater than zero.';
            $employeeCheck = database()->prepare("SELECT id FROM employees WHERE id=:id AND status='active' LIMIT 1");
            $employeeCheck->execute(['id' => $employeeId]);
            if (!$employeeCheck->fetch()) $errors[] = 'Select a valid employee.';
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date) $errors[] = 'Select a valid date.';
            if (!$errors) {
                $statement = database()->prepare('INSERT INTO cashier_advance_requests (cashier_id, employee_id, advance_date, amount, reason) VALUES (:cashier, :employee, :date, :amount, :reason)');
                $statement->execute(['cashier' => (int) $user['id'], 'employee' => $employeeId, 'date' => $date, 'amount' => $amount, 'reason' => trim((string) ($_POST['reason'] ?? '')) ?: null]);
                flash('success', 'Advance request sent to administrator.');
                redirect('index.php?page=admin&section=cashier-advance-requests');
            }
        }
    }

    if ($isAdmin) {
        $employeeOptions = database()->query("SELECT id, name, employee_code FROM employees WHERE status='active' ORDER BY name")->fetchAll();
        $editRequest = null;
        $editRequestId = (int) ($_GET['advance_edit_id'] ?? 0);
        if ($editRequestId > 0) { $editStatement = database()->prepare("SELECT * FROM cashier_advance_requests WHERE id=:id AND disbursement_status='not_given' LIMIT 1"); $editStatement->execute(['id' => $editRequestId]); $editRequest = $editStatement->fetch() ?: null; }
        $rows = database()->query("SELECT r.*, u.name cashier_name, e.name employee_name, v.name reviewer_name FROM cashier_advance_requests r JOIN users u ON u.id=r.cashier_id JOIN employees e ON e.id=r.employee_id LEFT JOIN users v ON v.id=r.reviewed_by ORDER BY r.status='pending' DESC, r.requested_at DESC, r.id DESC")->fetchAll();
        $advanceReceiptId = (int) ($_GET['advance_receipt_id'] ?? 0);
        $advanceReceipt = null;
        if ($advanceReceiptId > 0) { $receiptStatement = database()->prepare("SELECT r.*, e.name employee_name, e.employee_code, u.name cashier_name, g.name given_by_name FROM cashier_advance_requests r JOIN employees e ON e.id=r.employee_id JOIN users u ON u.id=r.cashier_id LEFT JOIN users g ON g.id=r.given_by WHERE r.id=:id LIMIT 1"); $receiptStatement->execute(['id' => $advanceReceiptId]); $advanceReceipt = $receiptStatement->fetch() ?: null; }
        $title = 'Cashier Advance Requests'; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/cashier-advance-requests.php'; if ($editRequest) require __DIR__ . '/../views/cashier-advance-edit.php'; if ($advanceReceipt) require __DIR__ . '/../views/cashier-advance-receipt.php'; require __DIR__ . '/../includes/footer.php'; return;
    }
    $employeeOptions = database()->query("SELECT id, name, employee_code FROM employees WHERE status='active' ORDER BY name")->fetchAll();
    $statement = database()->prepare('SELECT r.*, e.name employee_name, v.name reviewer_name FROM cashier_advance_requests r JOIN employees e ON e.id=r.employee_id LEFT JOIN users v ON v.id=r.reviewed_by WHERE r.cashier_id=:cashier ORDER BY r.requested_at DESC, r.id DESC');
    $statement->execute(['cashier' => (int) $user['id']]);
    $rows = $statement->fetchAll();
    $existingAdvances = database()->query("SELECT a.id, a.advance_date, a.amount, a.reason, e.name employee_name, 'approved' status, 'given' disbursement_status, NULL reviewer_name FROM employee_advances a JOIN employees e ON e.id=a.employee_id ORDER BY a.advance_date DESC, a.id DESC")->fetchAll();
    foreach ($existingAdvances as &$existingAdvance) {
        $existingAdvance['requested_at'] = null;
        $existingAdvance['reviewed_at'] = null;
        $existingAdvance['cashier_name'] = 'Existing record';
    }
    unset($existingAdvance);
    $rows = array_merge($rows, $existingAdvances);
    usort($rows, static fn(array $left, array $right): int => strcmp((string) ($right['advance_date'] ?? ''), (string) ($left['advance_date'] ?? '')) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0)));
    $advanceReceiptId = (int) ($_GET['advance_receipt_id'] ?? 0);
    $advanceReceipt = null;
    if ($advanceReceiptId > 0) { $receiptStatement = database()->prepare("SELECT r.*, e.name employee_name, e.employee_code, u.name cashier_name, g.name given_by_name FROM cashier_advance_requests r JOIN employees e ON e.id=r.employee_id JOIN users u ON u.id=r.cashier_id LEFT JOIN users g ON g.id=r.given_by WHERE r.id=:id AND r.cashier_id=:cashier LIMIT 1"); $receiptStatement->execute(['id' => $advanceReceiptId, 'cashier' => (int) $user['id']]); $advanceReceipt = $receiptStatement->fetch() ?: null; }
    $title = 'Employee Advances'; $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/cashier-advance-requests.php'; if ($advanceReceipt) require __DIR__ . '/../views/cashier-advance-receipt.php'; require __DIR__ . '/../includes/footer.php';
}
