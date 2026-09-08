<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token. Please refresh and try again.');
    }
}

function old(string $key): string
{
    return e($_SESSION['old'][$key] ?? '');
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function receipt_settings(): array
{
    static $settings;
    if (is_array($settings)) return $settings;

    $pdo = database();
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_settings (id TINYINT UNSIGNED NOT NULL, garage_name VARCHAR(160) NOT NULL, tagline VARCHAR(190) NULL, contact_number VARCHAR(40) NULL, secondary_contact_number VARCHAR(40) NULL, email VARCHAR(190) NULL, address TEXT NULL, logo_path VARCHAR(255) NULL, receipt_header TEXT NULL, receipt_footer TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = $pdo->query('SHOW COLUMNS FROM receipt_settings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('secondary_contact_number', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN secondary_contact_number VARCHAR(40) NULL AFTER contact_number');
    if (!in_array('logo_path', $columns, true)) $pdo->exec('ALTER TABLE receipt_settings ADD COLUMN logo_path VARCHAR(255) NULL AFTER address');
    $settings = $pdo->query('SELECT * FROM receipt_settings WHERE id=1 LIMIT 1')->fetch() ?: [
        'garage_name' => 'Deshappriya Motors', 'tagline' => 'Garage Management System',
        'contact_number' => '077 345 6789', 'secondary_contact_number' => '', 'email' => 'info@deshappriyamotors.lk', 'address' => 'No. 123, Main Street, Kurunegala, Sri Lanka', 'logo_path' => '',
        'receipt_header' => '', 'receipt_footer' => 'Thank you for your business.',
    ];
    if (!isset($settings['id'])) {
        $insert = $pdo->prepare('INSERT INTO receipt_settings (id,garage_name,tagline,contact_number,secondary_contact_number,email,address,logo_path,receipt_header,receipt_footer) VALUES (1,:garage_name,:tagline,:contact_number,:secondary_contact_number,:email,:address,:logo_path,:receipt_header,:receipt_footer)');
        $insert->execute($settings);
        $settings['id'] = 1;
    }
    return $settings;
}

function powered_by_text(): string
{
    return 'Powered By Axiom Rec';
}

function receipt_logo_path(array $settings): string
{
    $path = (string) ($settings['logo_path'] ?? '');
    return preg_match('#^assets/uploads/[A-Za-z0-9._/-]+$#', $path) && is_file(__DIR__ . '/../' . $path) ? $path : '';
}
