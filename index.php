<?php

declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/includes/auth.php';

$page = $_GET['page'] ?? (current_user() ? 'dashboard' : 'login');
$base = 'views/';

if ($page === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user();
    redirect('index.php?page=login');
}

if ($page === 'login') {
    require_guest();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $errors[] = 'Enter a valid email address and password.';
        } elseif (!login_user($email, $password)) {
            $errors[] = 'The email or password is incorrect.';
        } else {
            redirect('index.php?page=dashboard');
        }
    }
    require __DIR__ . '/views/login.php';
    exit;
}

if ($page === 'password') {
    require_auth();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';
        $statement = database()->prepare('SELECT password FROM users WHERE id = :id');
        $statement->execute(['id' => current_user()['id']]);
        $stored = $statement->fetchColumn();
        if (!password_verify($current, (string) $stored)) $errors[] = 'The current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'The password confirmation does not match.';
        if (!$errors) {
            $update = database()->prepare('UPDATE users SET password = :password WHERE id = :id');
            $update->execute(['password' => password_hash($new, PASSWORD_DEFAULT), 'id' => current_user()['id']]);
            flash('success', 'Your password was changed successfully.');
            redirect('index.php?page=password');
        }
    }
    $title = 'Account settings'; require __DIR__ . '/views/password.php'; exit;
}

if ($page === 'dashboard') {
    require_auth();
    redirect('index.php?page=' . (current_user()['role'] === 'administrator' ? 'admin' : 'cashier'));
}

if ($page === 'admin') {
    require_role('administrator');
    $user = current_user();
    $section = preg_replace('/[^a-z0-9_-]/i', '', $_GET['section'] ?? 'dashboard');
    if (str_starts_with($section, 'customers')) {
        require_once __DIR__ . '/includes/customers.php';
        handle_customer_request($section);
        exit;
    }
    if (str_starts_with($section, 'vehicles')) {
        require_once __DIR__ . '/includes/customers.php';
        require_once __DIR__ . '/includes/vehicles.php';
        handle_vehicle_request($section);
        exit;
    }
    if (str_starts_with($section, 'suppliers')) {
        require_once __DIR__ . '/includes/suppliers.php';
        handle_supplier_request($section);
        exit;
    }
    if (str_starts_with($section, 'stock')) {
        require_once __DIR__ . '/includes/suppliers.php';
        require_once __DIR__ . '/includes/stock.php';
        handle_stock_request($section);
        exit;
    }
    if (str_starts_with($section, 'estimates')) {
        require_once __DIR__ . '/includes/customers.php';
        require_once __DIR__ . '/includes/vehicles.php';
        require_once __DIR__ . '/includes/estimates.php';
        handle_estimate_request($section);
        exit;
    }
    if (str_starts_with($section, 'other-income')) {
        require_once __DIR__ . '/includes/other-income.php';
        handle_other_income_request($section);
        exit;
    }
    if (str_starts_with($section, 'expenses')) {
        require_once __DIR__ . '/includes/suppliers.php';
        require_once __DIR__ . '/includes/expenses.php';
        handle_expense_request($section);
        exit;
    }
    if (str_starts_with($section, 'bays')) {
        require_once __DIR__ . '/includes/employees.php';
        require_once __DIR__ . '/includes/bays.php';
        handle_bay_request($section);
        exit;
    }
    if (str_starts_with($section, 'appointments')) {
        require_once __DIR__ . '/includes/customers.php';
        require_once __DIR__ . '/includes/vehicles.php';
        require_once __DIR__ . '/includes/appointments.php';
        handle_appointment_request($section);
        exit;
    }
    if (str_starts_with($section, 'employees')) {
        require_once __DIR__ . '/includes/employees.php';
        handle_employee_request($section);
        exit;
    }
    if ($section === 'settings-password') {
        redirect('index.php?page=password');
    }
    $title = 'Administrator';
    require __DIR__ . '/views/admin.php';
    exit;
}

if ($page === 'cashier') {
    require_role('cashier');
    $user = current_user();
    $title = 'Cashier dashboard';
    require __DIR__ . '/views/cashier-dashboard.php';
    exit;
}

http_response_code(404);
echo 'Page not found.';
