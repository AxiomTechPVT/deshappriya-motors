<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function ensure_user_access_columns(): void
{
    static $ready = false;
    if ($ready) return;

    $pdo = database();
    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $emailColumn = $pdo->query('SHOW COLUMNS FROM users LIKE "email"')->fetch();
    if ($emailColumn && ($emailColumn['Null'] ?? 'NO') !== 'YES') $pdo->exec('ALTER TABLE users MODIFY COLUMN email VARCHAR(190) NULL');
    if (!in_array('username', $columns, true)) $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER name');
    if (!in_array('mobile', $columns, true)) $pdo->exec('ALTER TABLE users ADD COLUMN mobile VARCHAR(40) NULL AFTER email');
    if (!in_array('status', $columns, true)) $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role");
    if (!in_array('last_login_at', $columns, true)) $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER status');

    $missing = $pdo->query("SELECT id, email FROM users WHERE username IS NULL OR username = ''")->fetchAll();
    foreach ($missing as $user) {
        $base = strtolower((string) preg_replace('/[^a-z0-9._-]+/i', '.', strstr((string) $user['email'], '@', true) ?: 'user'));
        $base = trim($base, '.-_') ?: 'user';
        $username = $base;
        $suffix = 1;
        do {
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id <> :id');
            $check->execute(['username' => $username, 'id' => (int) $user['id']]);
            if (!(int) $check->fetchColumn()) break;
            $username = $base . $suffix++;
        } while (true);
        $update = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');
        $update->execute(['username' => $username, 'id' => (int) $user['id']]);
    }

    $indexes = $pdo->query('SHOW INDEX FROM users')->fetchAll();
    $hasUsernameIndex = false;
    foreach ($indexes as $index) {
        if (($index['Key_name'] ?? '') === 'users_username_unique') $hasUsernameIndex = true;
    }
    if (!$hasUsernameIndex) $pdo->exec('ALTER TABLE users ADD UNIQUE KEY users_username_unique (username)');
    $ready = true;
}

function current_user(): ?array
{
    static $user;
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    ensure_user_access_columns();
    $statement = database()->prepare('SELECT id, name, username, email, mobile, role, status, last_login_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $record = $statement->fetch() ?: null;
    if (!$record || $record['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user = $record;
}

function require_guest(): void
{
    if (current_user() !== null) {
        redirect('index.php?page=dashboard');
    }
}

function require_auth(): void
{
    if (current_user() === null) {
        redirect('index.php?page=login');
    }
}

function require_role(string $role): void
{
    require_auth();
    if ((current_user()['role'] ?? '') !== $role) {
        http_response_code(403);
        require __DIR__ . '/../views/403.php';
        exit;
    }
}

function login_user(string $identifier, string $password): bool
{
    ensure_user_access_columns();
    $identifier = strtolower(trim($identifier));
    $statement = database()->prepare('SELECT id, password FROM users WHERE status = "active" AND (LOWER(email) = :identifier OR LOWER(username) = :identifier) LIMIT 1');
    $statement->execute(['identifier' => $identifier]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $update = database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => (int) $user['id']]);
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
