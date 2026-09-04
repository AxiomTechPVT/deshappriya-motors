<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    static $user;
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $statement = database()->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $_SESSION['user_id']]);
    return $user = $statement->fetch() ?: null;
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

function login_user(string $email, string $password): bool
{
    $statement = database()->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
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
