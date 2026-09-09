<?php

declare(strict_types=1);

function ensure_cashier_register_table(): void
{
    static $ready = false;
    if ($ready) return;

    database()->exec("CREATE TABLE IF NOT EXISTS cashier_registers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cashier_id BIGINT UNSIGNED NOT NULL,
        register_date DATE NOT NULL,
        opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        actual_closing_amount DECIMAL(12,2) NULL,
        expected_closing_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        difference_amount DECIMAL(12,2) NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        handover_status ENUM('not_submitted','pending','accepted','rejected') NOT NULL DEFAULT 'not_submitted',
        handover_amount DECIMAL(12,2) NULL,
        handed_over_at DATETIME NULL,
        accepted_amount DECIMAL(12,2) NULL,
        accepted_by BIGINT UNSIGNED NULL,
        accepted_at DATETIME NULL,
        acceptance_note VARCHAR(255) NULL,
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        closed_at DATETIME NULL,
        notes TEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY cashier_register_day_unique (cashier_id, register_date),
        KEY cashier_register_date_index (register_date),
        CONSTRAINT cashier_register_user_fk FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT cashier_register_accepted_by_fk FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        "ALTER TABLE cashier_registers MODIFY COLUMN handover_status ENUM('not_submitted','pending','accepted','rejected') NOT NULL DEFAULT 'not_submitted'",
        "ALTER TABLE cashier_registers ADD COLUMN handover_amount DECIMAL(12,2) NULL AFTER handover_status",
        "ALTER TABLE cashier_registers ADD COLUMN handed_over_at DATETIME NULL AFTER handover_amount",
        "ALTER TABLE cashier_registers ADD COLUMN accepted_amount DECIMAL(12,2) NULL AFTER handed_over_at",
        "ALTER TABLE cashier_registers ADD COLUMN accepted_by BIGINT UNSIGNED NULL AFTER accepted_amount",
        "ALTER TABLE cashier_registers ADD COLUMN accepted_at DATETIME NULL AFTER accepted_by",
        "ALTER TABLE cashier_registers ADD COLUMN acceptance_note VARCHAR(255) NULL AFTER accepted_at"
    ] as $sql) { try { database()->exec($sql); } catch (Throwable $ignored) {} }
    $ready = true;
}

function cashier_register_date(): string
{
    $date = trim((string) ($_GET['register_date'] ?? $_POST['register_date'] ?? date('Y-m-d')));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
}

function cashier_register_transactions(string $date, int $cashierId): array
{
    $pdo = database();
    $transactions = [];

    $statement = $pdo->prepare("SELECT p.payment_no AS reference_no, p.payment_date AS happened_at,
        p.amount_applied AS amount, CONCAT('Invoice payment - ', i.invoice_no) AS description
        FROM invoice_payments p
        JOIN invoices i ON i.id = p.invoice_id
        WHERE DATE(p.payment_date) = :date AND p.payment_method = 'cash' AND p.created_by = :cashier
        ORDER BY p.payment_date, p.id");
    $statement->execute(['date' => $date, 'cashier' => $cashierId]);
    foreach ($statement->fetchAll() as $row) {
        $transactions[] = ['type' => 'Cash sale', 'direction' => 'in', 'amount' => (float) $row['amount'], 'description' => $row['description'], 'reference' => $row['reference_no'], 'happened_at' => $row['happened_at']];
    }

    $statement = $pdo->prepare("SELECT p.receipt_no AS reference_no, p.paid_at AS happened_at,
        p.amount AS amount, CONCAT('Job card payment - ', j.job_card_no) AS description
        FROM job_card_payments p
        JOIN job_cards j ON j.id = p.job_card_id
        LEFT JOIN invoices i ON i.job_card_id = p.job_card_id
        WHERE DATE(p.paid_at) = :date AND p.payment_method = 'cash' AND p.created_by = :cashier
          AND i.id IS NULL
        ORDER BY p.paid_at, p.id");
    $statement->execute(['date' => $date, 'cashier' => $cashierId]);
    foreach ($statement->fetchAll() as $row) {
        $transactions[] = ['type' => 'Cash sale', 'direction' => 'in', 'amount' => (float) $row['amount'], 'description' => $row['description'], 'reference' => $row['reference_no'], 'happened_at' => $row['happened_at']];
    }

    $statement = $pdo->prepare("SELECT reference_no, created_at AS happened_at, amount,
        title AS description
        FROM other_income
        WHERE income_date = :date AND payment_method = 'cash' AND created_by = :cashier
          AND (category IS NULL OR category <> 'Invoice Payment')
        ORDER BY created_at, id");
    $statement->execute(['date' => $date, 'cashier' => $cashierId]);
    foreach ($statement->fetchAll() as $row) {
        $transactions[] = ['type' => 'Other income', 'direction' => 'in', 'amount' => (float) $row['amount'], 'description' => $row['description'], 'reference' => $row['reference_no'], 'happened_at' => $row['happened_at']];
    }

    $statement = $pdo->prepare("SELECT expense_no AS reference_no, created_at AS happened_at,
        paid_amount AS amount, description
        FROM expenses
        WHERE expense_date = :date AND payment_method = 'cash' AND status <> 'cancelled'
          AND created_by = :cashier AND paid_amount > 0
        ORDER BY created_at, id");
    $statement->execute(['date' => $date, 'cashier' => $cashierId]);
    foreach ($statement->fetchAll() as $row) {
        $transactions[] = ['type' => 'Expense', 'direction' => 'out', 'amount' => (float) $row['amount'], 'description' => $row['description'], 'reference' => $row['reference_no'], 'happened_at' => $row['happened_at']];
    }

    $statement = $pdo->prepare("SELECT CONCAT('ADV-', a.id) AS reference_no, a.created_at AS happened_at,
        a.amount, CONCAT('Employee advance - ', e.name) AS description
        FROM employee_advances a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.advance_date = :date
        ORDER BY a.created_at, a.id");
    $statement->execute(['date' => $date]);
    foreach ($statement->fetchAll() as $row) {
        $transactions[] = ['type' => 'Staff advance', 'direction' => 'out', 'amount' => (float) $row['amount'], 'description' => $row['description'], 'reference' => $row['reference_no'], 'happened_at' => $row['happened_at']];
    }

    usort($transactions, static fn(array $a, array $b): int => strcmp((string) $a['happened_at'], (string) $b['happened_at']));
    return $transactions;
}

function cashier_register_totals(array $transactions, float $opening): array
{
    $sales = $income = $expenses = $advances = 0.0;
    foreach ($transactions as $transaction) {
        if ($transaction['direction'] === 'in') {
            if ($transaction['type'] === 'Cash sale') $sales += $transaction['amount'];
            else $income += $transaction['amount'];
        } elseif ($transaction['type'] === 'Staff advance') {
            $advances += $transaction['amount'];
        } else {
            $expenses += $transaction['amount'];
        }
    }
    return ['sales' => $sales, 'income' => $income, 'expenses' => $expenses, 'advances' => $advances, 'expected' => $opening + $sales + $income - $expenses - $advances];
}

function cashier_register_record(string $date, int $cashierId): ?array
{
    $statement = database()->prepare('SELECT * FROM cashier_registers WHERE cashier_id = :cashier AND register_date = :date LIMIT 1');
    $statement->execute(['cashier' => $cashierId, 'date' => $date]);
    return $statement->fetch() ?: null;
}

function handle_cashier_register_request(string $section): void
{
    ensure_cashier_register_table();
    $user = current_user();
    $cashierId = (int) ($user['id'] ?? 0);
    $date = cashier_register_date();
    $errors = [];
    $record = cashier_register_record($date, $cashierId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['register_action'] ?? '';
        if ($action === 'open') {
            $opening = (float) ($_POST['opening_amount'] ?? -1);
            if ($opening < 0) $errors[] = 'Opening cash cannot be negative.';
            if ($record) $errors[] = 'A cash register already exists for this date.';
            if (!$errors) {
                $statement = database()->prepare('INSERT INTO cashier_registers (cashier_id, register_date, opening_amount, notes) VALUES (:cashier, :date, :opening, :notes)');
                $statement->execute(['cashier' => $cashierId, 'date' => $date, 'opening' => $opening, 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null]);
                flash('success', 'Cash drawer opened successfully.');
                redirect('index.php?page=admin&section=cashier-register&register_date=' . rawurlencode($date));
            }
        } elseif ($action === 'handover') {
            $handoverAmount = (float) ($_POST['handover_amount'] ?? -1);
            if (!$record || $record['status'] !== 'closed') $errors[] = 'Close the cash drawer before handing over cash.';
            if (!in_array($record['handover_status'] ?? 'not_submitted', ['not_submitted', 'rejected'], true)) $errors[] = 'This cash drawer has already been handed over.';
            if ($handoverAmount < 0) $errors[] = 'Handover amount cannot be negative.';
            if (!$errors) {
                $statement = database()->prepare("UPDATE cashier_registers SET handover_status='pending', handover_amount=:amount, handed_over_at=NOW(), notes=COALESCE(NULLIF(:notes,''), notes) WHERE id=:id AND status='closed' AND handover_status='not_submitted'");
                $statement->execute(['amount' => $handoverAmount, 'notes' => trim((string) ($_POST['notes'] ?? '')), 'id' => (int) $record['id']]);
                flash('success', 'Cash handed over to admin for acceptance.');
                redirect('index.php?page=admin&section=cashier-register&register_date=' . rawurlencode($date));
            }
        } elseif ($action === 'close') {
            $actual = (float) ($_POST['actual_closing_amount'] ?? -1);
            if (!$record || $record['status'] !== 'open') $errors[] = 'This cash register is already closed or has not been opened.';
            if ($actual < 0) $errors[] = 'Closing cash cannot be negative.';
            if (!$errors) {
                $transactions = cashier_register_transactions($date, $cashierId);
                $totals = cashier_register_totals($transactions, (float) $record['opening_amount']);
                $statement = database()->prepare("UPDATE cashier_registers SET actual_closing_amount = :actual, expected_closing_amount = :expected, handover_status = 'pending', handover_amount = :handover, handed_over_at = NOW(), acceptance_note = NULL, notes = :notes WHERE id = :id AND status = 'open' AND handover_status IN ('not_submitted','rejected')");
                $statement->execute(['actual' => $actual, 'expected' => $totals['expected'], 'handover' => $actual, 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null, 'id' => (int) $record['id']]);
                flash('success', 'Cash closing submitted for admin acceptance.');
                redirect('index.php?page=admin&section=cashier-register&register_date=' . rawurlencode($date));
            }
        }
    }

    $record = cashier_register_record($date, $cashierId);
    $transactions = $record ? cashier_register_transactions($date, $cashierId) : [];
    $totals = cashier_register_totals($transactions, (float) ($record['opening_amount'] ?? 0));
    $historyFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['history_from'] ?? '')) ? $_GET['history_from'] : '';
    $historyTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['history_to'] ?? '')) ? $_GET['history_to'] : '';
    $historyStatus = in_array($_GET['history_status'] ?? '', ['pending', 'accepted', 'rejected'], true) ? $_GET['history_status'] : '';
    $historyWhere = ['cashier_id = :cashier'];
    $historyParams = ['cashier' => $cashierId];
    if ($historyFrom !== '') { $historyWhere[] = 'register_date >= :history_from'; $historyParams['history_from'] = $historyFrom; }
    if ($historyTo !== '') { $historyWhere[] = 'register_date <= :history_to'; $historyParams['history_to'] = $historyTo; }
    if ($historyStatus !== '') { $historyWhere[] = 'handover_status = :history_status'; $historyParams['history_status'] = $historyStatus; }
    $historyStatement = database()->prepare('SELECT * FROM cashier_registers WHERE '.implode(' AND ', $historyWhere).' ORDER BY register_date DESC, id DESC');
    $historyStatement->execute($historyParams);
    $historyRows = $historyStatement->fetchAll();
    foreach ($historyRows as &$historyRow) {
        $received = $historyRow['accepted_amount'] !== null ? (float) $historyRow['accepted_amount'] : (float) ($historyRow['handover_amount'] ?? 0);
        $historyRow['balance_amount'] = max(0, (float) $historyRow['expected_closing_amount'] - $received);
    }
    unset($historyRow);
    $title = 'Daily Cash Drawer';
    $sectionForHeader = $section;
    require __DIR__ . '/../views/cashier-register.php';
}

function handle_cashier_handover_request(string $section): void
{
    ensure_cashier_register_table();
    if ((current_user()['role'] ?? '') !== 'administrator') { http_response_code(403); require __DIR__ . '/../views/403.php'; return; }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['handover_action'] ?? '', ['accept', 'reject'], true)) {
        verify_csrf();
        $id = max(0, (int) ($_POST['register_id'] ?? 0));
        $action = $_POST['handover_action'];
        $amount = (float) ($_POST['accepted_amount'] ?? -1);
        if ($action === 'accept' && $amount < 0) $errors[] = 'Received amount cannot be negative.';
        $check = database()->prepare("SELECT expected_closing_amount FROM cashier_registers WHERE id=:id AND handover_status='pending' LIMIT 1");
        $check->execute(['id' => $id]);
        $pendingRegister = $check->fetch();
        if (!$pendingRegister) $errors[] = 'This cash handover is no longer pending.';
        if (!$errors && $action === 'reject') {
            $statement = database()->prepare("UPDATE cashier_registers SET handover_status='rejected', status='open', actual_closing_amount=NULL, difference_amount=NULL, handover_amount=NULL, closed_at=NULL, acceptance_note=:note WHERE id=:id AND handover_status='pending'");
            $statement->execute(['note' => trim((string) ($_POST['acceptance_note'] ?? '')) ?: 'Cash amount was not accepted. Please recount and submit again.', 'id' => $id]);
            flash('success', 'Cash handover rejected and sent back to cashier.');
            redirect('index.php?page=admin&section=cashier-handovers');
        }
        if (!$errors) {
            $statement = database()->prepare("UPDATE cashier_registers SET handover_status='accepted', accepted_amount=:amount, actual_closing_amount=:amount_for_actual, difference_amount=:difference, status='closed', closed_at=COALESCE(closed_at,NOW()), accepted_by=:user, accepted_at=NOW(), acceptance_note=:note WHERE id=:id AND handover_status='pending'");
            $statement->execute(['amount' => $amount, 'amount_for_actual' => $amount, 'difference' => $amount - (float) $pendingRegister['expected_closing_amount'], 'user' => (int) current_user()['id'], 'note' => trim((string) ($_POST['acceptance_note'] ?? '')) ?: null, 'id' => $id]);
            flash('success', 'Cash handover accepted successfully.');
            redirect('index.php?page=admin&section=cashier-handovers');
        }
    }
    $search = trim((string) ($_GET['search'] ?? ''));
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '';
    $statusFilter = in_array($_GET['handover_status'] ?? '', ['pending', 'accepted', 'rejected'], true) ? $_GET['handover_status'] : '';
    $balanceFilter = in_array($_GET['balance_filter'] ?? '', ['due', 'balanced'], true) ? $_GET['balance_filter'] : '';
    $where = [];
    $params = [];
    if ($search !== '') { $where[] = '(u.name LIKE :search OR r.register_date LIKE :search)'; $params['search'] = '%'.$search.'%'; }
    if ($from !== '') { $where[] = 'r.register_date >= :from_date'; $params['from_date'] = $from; }
    if ($to !== '') { $where[] = 'r.register_date <= :to_date'; $params['to_date'] = $to; }
    if ($statusFilter !== '') { $where[] = 'r.handover_status = :handover_status'; $params['handover_status'] = $statusFilter; }
    $sql = "SELECT r.*, u.name AS cashier_name, COALESCE(a.name, CASE WHEN r.status='closed' THEN 'Waiting for cashier handover' ELSE 'Drawer is still open' END) AS accepted_by_name FROM cashier_registers r JOIN users u ON u.id=r.cashier_id LEFT JOIN users a ON a.id=r.accepted_by" . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY COALESCE(r.handed_over_at, r.closed_at, r.opened_at) DESC, r.id DESC';
    $statement = database()->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $received = $row['accepted_amount'] !== null ? (float) $row['accepted_amount'] : (float) ($row['handover_amount'] ?? 0);
        $row['balance_amount'] = max(0, (float) $row['expected_closing_amount'] - $received);
        $row['difference_amount'] = $received - (float) $row['expected_closing_amount'];
    }
    unset($row);
    if ($balanceFilter !== '') {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => $balanceFilter === 'due' ? $row['balance_amount'] > 0 : $row['balance_amount'] <= 0));
    }
    $title = 'Cashier Handovers';
    $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/cashier-handovers.php';
    require __DIR__ . '/../includes/footer.php';
}
