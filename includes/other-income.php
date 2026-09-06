<?php

declare(strict_types=1);

function ensure_other_income_table(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS other_income (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        income_date DATE NOT NULL,
        title VARCHAR(160) NOT NULL,
        category VARCHAR(100) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_method ENUM('cash','card','bank','other') NOT NULL DEFAULT 'cash',
        reference_no VARCHAR(80) NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY other_income_date_index (income_date),
        KEY other_income_category_index (category),
        CONSTRAINT other_income_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function other_income_filters(): array
{
    $methods = ['cash', 'card', 'bank', 'other'];
    return [
        'search' => trim((string) ($_GET['search'] ?? '')),
        'category' => trim((string) ($_GET['category'] ?? '')),
        'payment_method' => in_array($_GET['payment_method'] ?? '', $methods, true) ? $_GET['payment_method'] : '',
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    ];
}

function other_income_where(array $filters, array &$params): string
{
    $where = [];
    if ($filters['search'] !== '') {
        $where[] = '(oi.title LIKE :search OR oi.category LIKE :search OR oi.reference_no LIKE :search OR oi.notes LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if ($filters['category'] !== '') { $where[] = 'oi.category = :category'; $params['category'] = $filters['category']; }
    if ($filters['payment_method'] !== '') { $where[] = 'oi.payment_method = :payment_method'; $params['payment_method'] = $filters['payment_method']; }
    if ($filters['from'] !== '') { $where[] = 'oi.income_date >= :from_date'; $params['from_date'] = $filters['from']; }
    if ($filters['to'] !== '') { $where[] = 'oi.income_date <= :to_date'; $params['to_date'] = $filters['to']; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function other_income_rows(array $filters): array
{
    $params = [];
    $where = other_income_where($filters, $params);
    $statement = database()->prepare("SELECT oi.*, u.name AS created_by_name FROM other_income oi LEFT JOIN users u ON u.id = oi.created_by{$where} ORDER BY oi.income_date DESC, oi.id DESC");
    $statement->execute($params);
    return $statement->fetchAll();
}

function other_income_categories(): array
{
    $categories = database()->query("SELECT DISTINCT category FROM other_income WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    array_unshift($categories, 'Other Income');
    return array_values(array_unique(array_map(static fn ($category): string => (string) $category, $categories)));
}

function other_income_types(): array
{
    $types = database()->query("SELECT title FROM other_income WHERE title IS NOT NULL AND title <> '' UNION SELECT category FROM other_income WHERE category IS NOT NULL AND category <> '' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
    $defaults = ['Oil', 'Yakada Badu', 'Scrap Sale', 'Commission', 'Vehicle Rental', 'Service Charge', 'Other Income'];
    return array_values(array_unique(array_merge($defaults, array_map(static fn ($type): string => (string) $type, $types))));
}

function other_income_summary(array $filters): array
{
    $params = [];
    $where = other_income_where($filters, $params);
    $statement = database()->prepare("SELECT COUNT(*) AS records, COALESCE(SUM(oi.amount), 0) AS total FROM other_income oi{$where}");
    $statement->execute($params);
    $summary = $statement->fetch() ?: ['records' => 0, 'total' => 0];
    $month = database()->query("SELECT COALESCE(SUM(amount), 0) FROM other_income WHERE income_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND income_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)")->fetchColumn();
    return ['records' => (int) $summary['records'], 'total' => (float) $summary['total'], 'month' => (float) $month];
}

function other_income_record(int $id): ?array
{
    $statement = database()->prepare('SELECT oi.*, u.name AS created_by_name FROM other_income oi LEFT JOIN users u ON u.id = oi.created_by WHERE oi.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function other_income_input(?array $source = null): array
{
    $source ??= $_POST;
    $selectedType = trim((string) ($source['income_type'] ?? $source['title'] ?? ''));
    $customType = trim((string) ($source['custom_income_type'] ?? ''));
    return [
        'income_date' => trim((string) ($source['income_date'] ?? date('Y-m-d'))),
        'title' => $selectedType === '__custom__' ? $customType : $selectedType,
        'category' => trim((string) ($source['category'] ?? '')),
        'amount' => trim((string) ($source['amount'] ?? '')),
        'payment_method' => (string) ($source['payment_method'] ?? 'cash'),
        'reference_no' => trim((string) ($source['reference_no'] ?? '')),
        'notes' => trim((string) ($source['notes'] ?? '')),
    ];
}

function validate_other_income(array $input): array
{
    $errors = [];
    $dateValid = DateTime::createFromFormat('Y-m-d', $input['income_date']);
    if (!$dateValid || $dateValid->format('Y-m-d') !== $input['income_date']) $errors[] = 'Select a valid income date.';
    if ($input['title'] === '') $errors[] = 'Income title is required.';
    if ((float) $input['amount'] <= 0) $errors[] = 'Amount must be greater than zero.';
    if (!in_array($input['payment_method'], ['cash', 'card', 'bank', 'other'], true)) $errors[] = 'Select a valid payment method.';
    return $errors;
}

function handle_other_income_request(string $section): void
{
    ensure_other_income_table();
    $id = max(0, (int) ($_GET['id'] ?? 0));
    $input = other_income_input();
    $errors = [];

    if ($section === 'other-income-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $statement = database()->prepare('DELETE FROM other_income WHERE id = :id');
        $statement->execute(['id' => (int) ($_POST['income_id'] ?? 0)]);
        flash('success', 'Other income record deleted successfully.');
        redirect('index.php?page=admin&section=other-income');
    }

    if (in_array($section, ['other-income-view', 'other-income-edit'], true)) {
        $income = other_income_record($id);
        if (!$income) { http_response_code(404); exit('Other income record not found.'); }
        if ($section === 'other-income-view') {
            $title = 'View Other Income'; $sectionForHeader = $section;
            require __DIR__ . '/../includes/header.php';
            require __DIR__ . '/../views/other-income-view-modal.php';
            require __DIR__ . '/../includes/footer.php';
            return;
        }
        $input = other_income_input($income);
    }

    if (in_array($section, ['other-income-add', 'other-income-edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $input = other_income_input();
        $errors = validate_other_income($input);
        if (!$errors) {
            $values = [
                'income_date' => $input['income_date'], 'title' => $input['title'], 'category' => $input['category'] ?: null,
                'amount' => (float) $input['amount'], 'payment_method' => $input['payment_method'],
                'reference_no' => $input['reference_no'] ?: null, 'notes' => $input['notes'] ?: null,
            ];
            if ($section === 'other-income-add') {
                $values['created_by'] = (int) current_user()['id'];
                $statement = database()->prepare('INSERT INTO other_income (income_date, title, category, amount, payment_method, reference_no, notes, created_by) VALUES (:income_date, :title, :category, :amount, :payment_method, :reference_no, :notes, :created_by)');
                $statement->execute($values);
                flash('success', 'Other income added successfully.');
            } else {
                $values['id'] = $id;
                $statement = database()->prepare('UPDATE other_income SET income_date = :income_date, title = :title, category = :category, amount = :amount, payment_method = :payment_method, reference_no = :reference_no, notes = :notes WHERE id = :id');
                $statement->execute($values);
                flash('success', 'Other income updated successfully.');
            }
            redirect('index.php?page=admin&section=other-income');
        }
    }

    if ($section === 'other-income-add' || $section === 'other-income-edit') {
        $categories = other_income_categories();
        $types = other_income_types();
        $title = $section === 'other-income-edit' ? 'Edit Other Income' : 'Add Other Income'; $sectionForHeader = $section;
        require __DIR__ . '/../includes/header.php';
        require __DIR__ . '/../views/other-income-form-modal.php';
        require __DIR__ . '/../includes/footer.php';
        return;
    }

    $filters = other_income_filters();
    $rows = other_income_rows($filters);
    $categories = other_income_categories();
    $summary = other_income_summary($filters);
    $title = 'Other Income'; $sectionForHeader = $section;
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/other-income.php';
    require __DIR__ . '/../includes/footer.php';
}
