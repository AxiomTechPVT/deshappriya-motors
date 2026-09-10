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
    ensure_user_access_columns();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $errors[] = 'Enter your username or email and password.';
        } elseif (!login_user($email, $password)) {
            $errors[] = 'The username/email or password is incorrect.';
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

if ($page === 'account') {
    require_auth();
    ensure_user_access_columns();
    $user = current_user();
    $errors = [];
    $profileInput = ['name' => (string) ($user['name'] ?? ''), 'username' => (string) ($user['username'] ?? ''), 'email' => (string) ($user['email'] ?? ''), 'mobile' => (string) ($user['mobile'] ?? '')];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = $_POST['account_action'] ?? '';
        if ($action === 'profile') {
            $profileInput['name'] = trim((string) ($_POST['name'] ?? ''));
            $profileInput['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
            $profileInput['mobile'] = trim((string) ($_POST['mobile'] ?? ''));
            if ($profileInput['name'] === '') $errors[] = 'Full name is required.';
            if ($profileInput['email'] !== '' && !filter_var($profileInput['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($profileInput['mobile'] !== '' && !preg_match('/^[0-9+() .-]{7,40}$/', $profileInput['mobile'])) $errors[] = 'Enter a valid mobile number.';
            if (!$errors) {
                $update = database()->prepare('UPDATE users SET name = :name, email = :email, mobile = :mobile WHERE id = :id');
                $update->execute(['name' => $profileInput['name'], 'email' => $profileInput['email'] ?: null, 'mobile' => $profileInput['mobile'] ?: null, 'id' => $user['id']]);
                flash('success', 'Your profile was updated successfully.');
                redirect('index.php?page=account');
            }
        } elseif ($action === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');
            $statement = database()->prepare('SELECT password FROM users WHERE id = :id');
            $statement->execute(['id' => $user['id']]);
            if (!password_verify($current, (string) $statement->fetchColumn())) $errors[] = 'The current password is incorrect.';
            if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
            if ($new !== $confirm) $errors[] = 'The password confirmation does not match.';
            if (!$errors) {
                $update = database()->prepare('UPDATE users SET password = :password WHERE id = :id');
                $update->execute(['password' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
                flash('success', 'Your password was changed successfully.');
                redirect('index.php?page=account');
            }
        }
    }
    $section = 'account';
    $title = 'Account settings';
    require __DIR__ . '/views/account.php';
    exit;
}

if ($page === 'dashboard') {
    require_auth();
    redirect('index.php?page=' . (current_user()['role'] === 'administrator' ? 'admin' : 'cashier'));
}

if ($page === 'admin') {
    require_auth();
    $user = current_user();
    $section = preg_replace('/[^a-z0-9_-]/i', '', $_GET['section'] ?? 'dashboard');
    if ($user['role'] === 'cashier') {
        $cashierSections = ['customers', 'customers-add', 'vehicles', 'vehicles-add', 'jobcards-pending', 'jobcards-ongoing', 'jobcards-completed', 'estimates', 'invoices-mine', 'invoices', 'invoices-quick', 'invoices-view', 'invoices-thermal', 'invoices-payment', 'invoices-receipt', 'reports-payments', 'cashier-register', 'cashier-advance-requests', 'expenses', 'expenses-add', 'expenses-view', 'other-income', 'other-income-add', 'other-income-view', 'employees-advances', 'employees-attendance', 'stock', 'stock-low', 'stock-add', 'stock-view', 'stock-restock', 'services', 'services-add', 'services-view', 'appointments', 'appointments-add', 'appointments-view', 'appointments-edit', 'appointments-delete'];
        // Customer list actions use the existing customer handlers for both roles.
        $customerActions = ['customers-view', 'customers-edit', 'customers-deactivate', 'customers-export'];
        // Vehicle list actions reuse the existing history, edit, and removal handlers.
        $vehicleActions = ['vehicles-view', 'vehicles-edit', 'vehicles-remove'];
        // Estimate buttons and forms must reach their existing handlers for cashiers.
        $estimateActions = ['estimates-add', 'estimates-view', 'estimates-edit', 'estimates-print', 'estimates-print-settings', 'estimates-status', 'estimates-delete'];
        $allowed = in_array($section, $cashierSections, true) || in_array($section, $customerActions, true) || in_array($section, $vehicleActions, true) || in_array($section, $estimateActions, true) || str_starts_with($section, 'invoices-') || str_starts_with($section, 'jobcards-');
        if (!$allowed) {
            redirect('index.php?page=cashier');
        }
    }
    if (str_starts_with($section, 'customers')) {
        require_once __DIR__ . '/includes/customers.php';
        handle_customer_request($section);
        exit;
    }
    if (str_starts_with($section, 'vehicles')) {
        require_once __DIR__ . '/includes/customers.php';
        require_once __DIR__ . '/includes/job-cards.php';
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
    if ($section === 'cashier-register') {
        require_once __DIR__ . '/includes/cashier-register.php';
        handle_cashier_register_request($section);
        exit;
    }
    if ($section === 'cashier-handovers') {
        require_once __DIR__ . '/includes/cashier-register.php';
        handle_cashier_handover_request($section);
        exit;
    }
    if ($section === 'cashier-advance-requests') {
        require_once __DIR__ . '/includes/cashier-advances.php';
        handle_cashier_advance_request($section);
        exit;
    }
    if (str_starts_with($section, 'reports')) {
        require_once __DIR__ . '/includes/job-cards.php';
        ensure_job_card_tables();
        require_once __DIR__ . '/includes/reports.php';
        handle_report_request($section);
        exit;
    }
    if ($section === 'users-cashiers') {
        require_once __DIR__ . '/includes/users.php';
        handle_user_management_request($section);
        exit;
    }
    if ($section === 'settings') {
        require_once __DIR__ . '/includes/settings.php';
        handle_settings_request();
        exit;
    }
    if (str_starts_with($section, 'expenses')) {
        require_once __DIR__ . '/includes/suppliers.php';
        require_once __DIR__ . '/includes/expenses.php';
        handle_expense_request($section);
        exit;
    }
    if (str_starts_with($section, 'services')) {
        require_once __DIR__ . '/includes/services.php';
        handle_service_request($section);
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
    if (str_starts_with($section, 'jobcards')) {
        require_once __DIR__ . '/includes/suppliers.php';
        require_once __DIR__ . '/includes/stock.php';
        require_once __DIR__ . '/includes/job-cards.php';
        handle_job_card_request($section);
        exit;
    }
    if (str_starts_with($section, 'invoices')) {
        require_once __DIR__ . '/includes/suppliers.php';
        require_once __DIR__ . '/includes/stock.php';
        require_once __DIR__ . '/includes/job-cards.php';
        require_once __DIR__ . '/includes/invoices.php';
        handle_invoice_request($section);
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
    $cashierDashboard = cashier_dashboard_data((int) ($user['id'] ?? 0));
    $title = 'Cashier dashboard';
    require __DIR__ . '/views/cashier-dashboard.php';
    exit;
}

http_response_code(404);
echo 'Page not found.';
