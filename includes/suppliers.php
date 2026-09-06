<?php

declare(strict_types=1);

function ensure_suppliers_table(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS suppliers (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, supplier_code VARCHAR(20) NOT NULL, name VARCHAR(160) NOT NULL, contact_person VARCHAR(120) NULL, phone VARCHAR(40) NOT NULL, email VARCHAR(190) NULL, address TEXT NULL, tax_number VARCHAR(100) NULL, notes TEXT NULL, status ENUM('active', 'inactive') NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY suppliers_code_unique (supplier_code), KEY suppliers_name_index (name), KEY suppliers_phone_index (phone), KEY suppliers_status_index (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS supplier_products (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, supplier_id BIGINT UNSIGNED NOT NULL, product_code VARCHAR(60) NULL, product_name VARCHAR(160) NOT NULL, category VARCHAR(100) NULL, unit VARCHAR(30) NOT NULL DEFAULT 'piece', buying_price DECIMAL(12,2) NOT NULL DEFAULT 0, selling_price DECIMAL(12,2) NOT NULL DEFAULT 0, opening_quantity DECIMAL(12,2) NOT NULL DEFAULT 0, reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY supplier_products_supplier_index (supplier_id), KEY supplier_products_name_index (product_name), CONSTRAINT supplier_products_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function supplier_filters(): array
{
    $perPage = (int) ($_GET['per_page'] ?? 10);
    return ['search'=>trim((string) ($_GET['search'] ?? '')), 'status'=>in_array($_GET['status'] ?? '', ['active','inactive'], true) ? $_GET['status'] : '', 'from'=>preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '', 'to'=>preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '', 'page'=>max(1, (int) ($_GET['p'] ?? 1)), 'per_page'=>in_array($perPage, [10,25,50], true) ? max(1, $perPage) : 10];
}

function supplier_where(array $filters, array &$params): string
{
    $where = [];
    if ($filters['search'] !== '') { $where[] = '(name LIKE :search OR supplier_code LIKE :search OR contact_person LIKE :search OR phone LIKE :search OR email LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
    if ($filters['status'] !== '') { $where[] = 'status = :status'; $params['status'] = $filters['status']; }
    if ($filters['from'] !== '') { $where[] = 'created_at >= :from_date'; $params['from_date'] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = 'created_at <= :to_date'; $params['to_date'] = $filters['to'] . ' 23:59:59'; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function supplier_rows(array $filters, bool $count = false): array|int
{
    $params = []; $where = supplier_where($filters, $params);
    $sql = $count ? "SELECT COUNT(*) FROM suppliers{$where}" : "SELECT id, supplier_code, name, contact_person, phone, email, status, created_at, (SELECT COUNT(*) FROM supplier_products WHERE supplier_products.supplier_id = suppliers.id) AS product_count FROM suppliers{$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $statement = database()->prepare($sql);
    foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
    if (!$count) { $statement->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT); $statement->bindValue(':offset', ($filters['page'] - 1) * $filters['per_page'], PDO::PARAM_INT); }
    $statement->execute();
    return $count ? (int) $statement->fetchColumn() : $statement->fetchAll();
}

function supplier_products_for(int $supplierId): array
{
    $statement = database()->prepare('SELECT id, product_code, product_name, category, unit, buying_price, selling_price, opening_quantity, reorder_level FROM supplier_products WHERE supplier_id = :supplier_id ORDER BY product_name ASC');
    $statement->execute(['supplier_id' => $supplierId]);
    return $statement->fetchAll();
}

function supplier_record(int $id): ?array
{
    $statement = database()->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function supplier_kpis(): array
{
    $row = database()->query("SELECT COUNT(*) total, SUM(status = 'active') active, SUM(status = 'inactive') inactive FROM suppliers")->fetch() ?: [];
    return ['Total Suppliers'=>(int) ($row['total'] ?? 0), 'Active Suppliers'=>(int) ($row['active'] ?? 0), 'Total Purchases'=>0, 'Outstanding Amount'=>0];
}

function supplier_input(): array
{
    return ['name'=>trim((string) ($_POST['name'] ?? '')), 'contact_person'=>trim((string) ($_POST['contact_person'] ?? '')), 'phone'=>trim((string) ($_POST['phone'] ?? '')), 'email'=>trim((string) ($_POST['email'] ?? '')), 'address'=>trim((string) ($_POST['address'] ?? '')), 'tax_number'=>trim((string) ($_POST['tax_number'] ?? '')), 'notes'=>trim((string) ($_POST['notes'] ?? '')), 'status'=>$_POST['status'] ?? 'active', 'products'=>is_array($_POST['products'] ?? null) ? $_POST['products'] : []];
}

function validate_supplier(array $input): array
{
    $errors = [];
    if ($input['name'] === '') $errors[] = 'Supplier name is required.';
    if ($input['phone'] === '') $errors[] = 'Phone number is required.';
    if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!in_array($input['status'], ['active','inactive'], true)) $errors[] = 'Select a valid status.';
    foreach ($input['products'] as $index => $product) {
        $productName = trim((string) ($product['name'] ?? ''));
        $hasProductData = $productName !== '' || trim((string) ($product['code'] ?? '')) !== '' || trim((string) ($product['category'] ?? ''));
        if (!$hasProductData) continue;
        if ($productName === '') $errors[] = 'Product ' . ($index + 1) . ' name is required.';
        if ((float) ($product['buying_price'] ?? 0) < 0 || (float) ($product['selling_price'] ?? 0) < 0) $errors[] = 'Product ' . ($index + 1) . ' prices cannot be negative.';
        if ((float) ($product['quantity'] ?? 0) < 0 || (float) ($product['reorder_level'] ?? 0) < 0) $errors[] = 'Product ' . ($index + 1) . ' quantities cannot be negative.';
    }
    return $errors;
}

function handle_supplier_request(string $section): void
{
    ensure_suppliers_table(); $filters = supplier_filters(); $input = supplier_input(); $errors = []; $supplierId = max(0, (int) ($_GET['id'] ?? 0));
    if (in_array($section, ['suppliers-edit', 'suppliers-view'], true)) {
        $supplier = supplier_record($supplierId);
        if (!$supplier) { http_response_code(404); exit('Supplier not found.'); }
        if ($section === 'suppliers-view') {
            $products = supplier_products_for($supplierId); $title = 'View Supplier'; $sectionForHeader = $section;
            require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/supplier-view.php'; require __DIR__ . '/../includes/footer.php'; return;
        }
        $input = array_merge($input, ['name'=>$supplier['name'], 'contact_person'=>$supplier['contact_person'] ?? '', 'phone'=>$supplier['phone'], 'email'=>$supplier['email'] ?? '', 'address'=>$supplier['address'] ?? '', 'tax_number'=>$supplier['tax_number'] ?? '', 'notes'=>$supplier['notes'] ?? '', 'status'=>$supplier['status'], 'products'=>supplier_products_for($supplierId)]);
    }
    if ($section === 'suppliers-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $statement = database()->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = :id"); $statement->execute(['id' => $supplierId]); flash('success', 'Supplier marked as inactive.'); redirect('index.php?page=admin&section=suppliers');
    }
    if ($section === 'suppliers-edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $errors = validate_supplier($input);
        if (!$errors) {
            $statement = database()->prepare('UPDATE suppliers SET name = :name, contact_person = :contact_person, phone = :phone, email = :email, address = :address, tax_number = :tax_number, notes = :notes, status = :status WHERE id = :id');
            $statement->execute(['name'=>$input['name'], 'contact_person'=>$input['contact_person'] ?: null, 'phone'=>$input['phone'], 'email'=>$input['email'] ?: null, 'address'=>$input['address'] ?: null, 'tax_number'=>$input['tax_number'] ?: null, 'notes'=>$input['notes'] ?: null, 'status'=>$input['status'], 'id'=>$supplierId]);
            flash('success', 'Supplier updated successfully.'); redirect('index.php?page=admin&section=suppliers');
        }
    }
    if ($section === 'suppliers-add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $errors = validate_supplier($input);
        if (!$errors) {
            $pdo = database(); $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare('INSERT INTO suppliers (supplier_code, name, contact_person, phone, email, address, tax_number, notes, status) VALUES (:code, :name, :contact_person, :phone, :email, :address, :tax_number, :notes, :status)');
                $statement->execute(['code'=>'TMP-'.bin2hex(random_bytes(5)), 'name'=>$input['name'], 'contact_person'=>$input['contact_person'] ?: null, 'phone'=>$input['phone'], 'email'=>$input['email'] ?: null, 'address'=>$input['address'] ?: null, 'tax_number'=>$input['tax_number'] ?: null, 'notes'=>$input['notes'] ?: null, 'status'=>$input['status']]);
                $id = (int) $pdo->lastInsertId(); $update = $pdo->prepare('UPDATE suppliers SET supplier_code = :code WHERE id = :id'); $update->execute(['code'=>'SUP-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT), 'id'=>$id]);
                $productStatement = $pdo->prepare('INSERT INTO supplier_products (supplier_id, product_code, product_name, category, unit, buying_price, selling_price, opening_quantity, reorder_level) VALUES (:supplier_id, :code, :name, :category, :unit, :buying_price, :selling_price, :quantity, :reorder_level)');
                foreach ($input['products'] as $product) {
                    $productName = trim((string) ($product['name'] ?? ''));
                    if ($productName === '') continue;
                    $productStatement->execute(['supplier_id'=>$id, 'code'=>trim((string) ($product['code'] ?? '')) ?: null, 'name'=>$productName, 'category'=>trim((string) ($product['category'] ?? '')) ?: null, 'unit'=>trim((string) ($product['unit'] ?? 'piece')) ?: 'piece', 'buying_price'=>(float) ($product['buying_price'] ?? 0), 'selling_price'=>(float) ($product['selling_price'] ?? 0), 'quantity'=>(float) ($product['quantity'] ?? 0), 'reorder_level'=>(float) ($product['reorder_level'] ?? 0)]);
                }
                $pdo->commit(); flash('success', 'Supplier added successfully.'); redirect('index.php?page=admin&section=suppliers');
            } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = 'The supplier could not be saved.'; }
        }
    }
    $title = in_array($section, ['suppliers-add', 'suppliers-edit'], true) ? ($section === 'suppliers-edit' ? 'Edit Supplier' : 'Add Supplier') : 'Suppliers'; $sectionForHeader = $section; require __DIR__ . '/../includes/header.php'; require __DIR__ . '/../views/' . (in_array($section, ['suppliers-add', 'suppliers-edit'], true) ? 'supplier-form.php' : 'suppliers.php'); require __DIR__ . '/../includes/footer.php';
}
