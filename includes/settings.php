<?php

declare(strict_types=1);

function ensure_settings_tables(): void
{
    static $ready = false;
    if ($ready) return;
    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, setting_key VARCHAR(100) NOT NULL, setting_value TEXT NULL, setting_group VARCHAR(30) NOT NULL, updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY system_settings_key_unique (setting_key), KEY system_settings_group_index (setting_group), CONSTRAINT system_settings_user_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = $pdo->query('SHOW COLUMNS FROM receipt_settings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('secondary_contact_number', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN secondary_contact_number VARCHAR(40) NULL AFTER contact_number');
    if (!in_array('logo_path', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN logo_path VARCHAR(255) NULL AFTER address');
    $ready = true;
}

function settings_defaults(): array
{
    return [
        'invoice_prefix' => 'INV-', 'job_card_prefix' => 'JC-', 'receipt_prefix' => 'PAY-', 'estimate_prefix' => 'EST-',
        'thermal_receipt_size' => '80mm', 'show_logo_invoice' => '1', 'show_logo_thermal' => '1', 'show_address' => '1', 'show_phone' => '1', 'show_email' => '1',
        'payment_cash_enabled' => '1', 'payment_card_enabled' => '1', 'payment_bank_enabled' => '1', 'payment_cheque_enabled' => '1', 'default_payment_method' => 'cash',
        'default_stock_unit' => 'PCS', 'default_low_stock_level' => '5', 'allow_negative_stock' => '0', 'costing_method' => 'FIFO',
        'currency' => 'LKR / Rs.', 'date_format' => 'd/m/Y', 'time_format' => '12', 'timezone' => 'Asia/Colombo', 'items_per_page' => '25', 'dashboard_period' => 'today',
        'low_stock_alert_enabled' => '1', 'due_payment_alert_enabled' => '1', 'pending_job_alert_enabled' => '1', 'session_timeout' => '60', 'backup_reminder' => 'off', 'last_backup_at' => '',
    ];
}

function system_settings(): array
{
    ensure_settings_tables();
    $values = settings_defaults();
    $rows = database()->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    foreach ($rows as $row) {
        $key = (string) $row['setting_key'];
        $values[$key] = (string) ($row['setting_value'] ?? '');
    }
    return $values;
}

function save_system_settings(string $group, array $values): void
{
    $statement = database()->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_by) VALUES (:key, :value, :group, :user) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    foreach ($values as $key => $value) $statement->execute(['key' => $key, 'value' => (string) $value, 'group' => $group, 'user' => current_user()['id']]);
}

function settings_input(string $key, string $fallback = ''): string
{
    return trim((string) ($_POST[$key] ?? $fallback));
}

function settings_checkbox(string $key): string
{
    return isset($_POST[$key]) ? '1' : '0';
}

function handle_settings_request(): void
{
    require_role('administrator');
    ensure_settings_tables();
    $pdo = database();
    $requestedTab = $_GET['tab'] ?? 'business';
    $requestedTab = is_string($requestedTab) ? $requestedTab : 'business';
    $tab = in_array($requestedTab, ['business', 'invoice', 'payments', 'stock', 'system', 'security', 'backup'], true) ? $requestedTab : 'business';
    $errors = [];
    $receipt = receipt_settings();
    $settings = system_settings();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['settings_action'] ?? '';
        $tab = in_array($action, ['business', 'invoice', 'payments', 'stock', 'system', 'security', 'backup'], true) ? $action : $tab;
        if ($action === 'business') {
            $values = ['garage_name' => settings_input('garage_name'), 'tagline' => settings_input('tagline'), 'contact_number' => settings_input('contact_number'), 'secondary_contact_number' => settings_input('secondary_contact_number'), 'email' => settings_input('email'), 'address' => settings_input('address'), 'receipt_footer' => settings_input('receipt_footer')];
            if ($values['garage_name'] === '') $errors[] = 'Garage name is required.';
            if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            $logoPath = (string) ($receipt['logo_path'] ?? '');
            if (!empty($_FILES['logo']['name'])) {
                $file = $_FILES['logo'];
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) $file['size'] > 2 * 1024 * 1024 || !isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) $errors[] = 'Logo must be a valid JPG, PNG, or WEBP image up to 2 MB.';
                else { $name = 'garage-logo-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime]; $target = __DIR__ . '/../assets/uploads/' . $name; if (!move_uploaded_file($file['tmp_name'], $target)) $errors[] = 'The logo could not be uploaded.'; else $logoPath = 'assets/uploads/' . $name; }
            }
            if (!$errors) {
                $statement = $pdo->prepare('UPDATE receipt_settings SET garage_name=:garage_name, tagline=:tagline, contact_number=:contact_number, secondary_contact_number=:secondary_contact_number, email=:email, address=:address, logo_path=:logo_path, receipt_footer=:receipt_footer WHERE id=1');
                $values['logo_path'] = $logoPath;
                $statement->execute($values);
                flash('success', 'Settings updated successfully.');
                redirect('index.php?page=admin&section=settings&tab=business');
            }
            $receipt = array_merge($receipt, $values, ['logo_path' => $logoPath]);
        } elseif ($action === 'invoice') {
            $values = ['invoice_prefix' => strtoupper(settings_input('invoice_prefix', 'INV-')), 'job_card_prefix' => strtoupper(settings_input('job_card_prefix', 'JC-')), 'receipt_prefix' => strtoupper(settings_input('receipt_prefix', 'PAY-')), 'estimate_prefix' => strtoupper(settings_input('estimate_prefix', 'EST-')), 'thermal_receipt_size' => '80mm', 'show_logo_invoice' => settings_checkbox('show_logo_invoice'), 'show_logo_thermal' => settings_checkbox('show_logo_thermal'), 'show_address' => settings_checkbox('show_address'), 'show_phone' => settings_checkbox('show_phone'), 'show_email' => settings_checkbox('show_email')];
            foreach (['invoice_prefix', 'job_card_prefix', 'receipt_prefix', 'estimate_prefix'] as $key) if ($values[$key] === '' || !preg_match('/^[A-Z0-9-]{1,15}$/', $values[$key])) $errors[] = 'Prefixes may contain only letters, numbers, and hyphens.';
            if (!$errors) { save_system_settings('invoice', $values); flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=invoice'); }
            $settings = array_merge($settings, $values);
        } elseif ($action === 'payments') {
            $values = ['payment_cash_enabled' => settings_checkbox('payment_cash_enabled'), 'payment_card_enabled' => settings_checkbox('payment_card_enabled'), 'payment_bank_enabled' => settings_checkbox('payment_bank_enabled'), 'payment_cheque_enabled' => settings_checkbox('payment_cheque_enabled'), 'default_payment_method' => settings_input('default_payment_method', 'cash')];
            $enabled = array_filter(['cash' => $values['payment_cash_enabled'], 'card' => $values['payment_card_enabled'], 'bank' => $values['payment_bank_enabled'], 'cheque' => $values['payment_cheque_enabled']]);
            if (!$enabled) $errors[] = 'Enable at least one payment method.';
            if (!isset($enabled[$values['default_payment_method']])) $errors[] = 'The default payment method must be enabled.';
            if (!$errors) { save_system_settings('payment', $values); flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=payments'); }
            $settings = array_merge($settings, $values);
        } elseif ($action === 'stock') {
            $level = settings_input('default_low_stock_level', '5');
            if (!is_numeric($level) || (float) $level < 0) $errors[] = 'Low stock alert level must be zero or greater.';
            $values = ['default_stock_unit' => strtoupper(settings_input('default_stock_unit', 'PCS')), 'default_low_stock_level' => (string) max(0, (float) $level), 'allow_negative_stock' => settings_checkbox('allow_negative_stock'), 'costing_method' => 'FIFO'];
            if ($values['default_stock_unit'] === '') $errors[] = 'Default stock unit is required.';
            if (!$errors) { save_system_settings('stock', $values); flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=stock'); }
            $settings = array_merge($settings, $values);
        } elseif ($action === 'system') {
            $values = ['currency' => settings_input('currency', 'LKR / Rs.'), 'date_format' => settings_input('date_format', 'd/m/Y'), 'time_format' => settings_input('time_format', '12'), 'timezone' => 'Asia/Colombo', 'items_per_page' => settings_input('items_per_page', '25'), 'dashboard_period' => settings_input('dashboard_period', 'today'), 'low_stock_alert_enabled' => settings_checkbox('low_stock_alert_enabled'), 'due_payment_alert_enabled' => settings_checkbox('due_payment_alert_enabled'), 'pending_job_alert_enabled' => settings_checkbox('pending_job_alert_enabled')];
            if (!in_array($values['date_format'], ['d/m/Y', 'Y-m-d'], true) || !in_array($values['time_format'], ['12', '24'], true) || !in_array($values['items_per_page'], ['10', '25', '50'], true) || !in_array($values['dashboard_period'], ['today', 'week', 'month'], true)) $errors[] = 'One or more system preference values are invalid.';
            if (!$errors) { save_system_settings('system', $values); flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=system'); }
            $settings = array_merge($settings, $values);
        } elseif ($action === 'security') {
            $current = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['password'] ?? ''); $confirm = (string) ($_POST['password_confirmation'] ?? '');
            $statement = $pdo->prepare('SELECT password FROM users WHERE id=:id'); $statement->execute(['id' => current_user()['id']]);
            if (!password_verify($current, (string) $statement->fetchColumn())) $errors[] = 'The current password is incorrect.';
            if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
            if ($new !== $confirm) $errors[] = 'The password confirmation does not match.';
            if (!$errors) { $statement = $pdo->prepare('UPDATE users SET password=:password WHERE id=:id'); $statement->execute(['password' => password_hash($new, PASSWORD_DEFAULT), 'id' => current_user()['id']]); flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=security'); }
        } elseif ($action === 'backup') {
            if (($_POST['backup_reminder'] ?? '') !== '') save_system_settings('backup', ['backup_reminder' => settings_input('backup_reminder')]);
            if (isset($_POST['download_backup'])) { download_database_backup(); }
            flash('success', 'Settings updated successfully.'); redirect('index.php?page=admin&section=settings&tab=backup');
        }
    }
    $title = 'Settings';
    $section = 'settings';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../views/settings.php';
    require __DIR__ . '/../includes/footer.php';
}

function download_database_backup(): never
{
    $pdo = database(); $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: 'deshapriya_motors'); $lines = ['-- Deshappriya Motors database backup', '-- Generated: ' . date('Y-m-d H:i:s'), 'SET FOREIGN_KEY_CHECKS=0;', ''];
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) { $quoted = '`' . str_replace('`', '``', $table) . '`'; $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM); $lines[] = 'DROP TABLE IF EXISTS ' . $quoted . ';'; $lines[] = $create[1] . ';'; $rows = $pdo->query('SELECT * FROM ' . $quoted)->fetchAll(PDO::FETCH_ASSOC); foreach ($rows as $row) { $columns = implode(',', array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', array_keys($row))); $values = implode(',', array_map(static fn($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row))); $lines[] = 'INSERT INTO ' . $quoted . ' (' . $columns . ') VALUES (' . $values . ');'; } $lines[] = ''; }
    $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
    save_system_settings('backup', ['last_backup_at' => date('Y-m-d H:i:s')]);
    header('Content-Type: application/sql'); header('Content-Disposition: attachment; filename="deshappriya-motors-' . date('Y-m-d-His') . '.sql"'); header('Content-Length: ' . strlen(implode("\n", $lines))); echo implode("\n", $lines); exit;
}
