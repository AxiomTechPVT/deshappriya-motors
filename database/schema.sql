CREATE DATABASE IF NOT EXISTS `deshappriya_motors` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `deshappriya_motors`;

CREATE TABLE IF NOT EXISTS `receipt_settings` (
    `id` TINYINT UNSIGNED NOT NULL,
    `garage_name` VARCHAR(160) NOT NULL,
    `tagline` VARCHAR(190) NULL,
    `contact_number` VARCHAR(40) NULL,
    `email` VARCHAR(190) NULL,
    `address` TEXT NULL,
    `receipt_header` TEXT NULL,
    `receipt_footer` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `receipt_settings` (`id`, `garage_name`, `tagline`, `contact_number`, `email`, `address`, `receipt_footer`)
VALUES (1, 'Deshappriya Motors', 'Garage Management System', '077 345 6789', 'info@deshappriyamotors.lk', 'No. 123, Main Street, Kurunegala, Sri Lanka', 'Thank you for your business.')
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `role` ENUM('administrator', 'cashier') NOT NULL DEFAULT 'cashier',
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_code VARCHAR(20) NOT NULL,
    name VARCHAR(160) NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    tax_number VARCHAR(100) NULL,
    notes TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY suppliers_code_unique (supplier_code),
    KEY suppliers_name_index (name),
    KEY suppliers_phone_index (phone),
    KEY suppliers_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    product_code VARCHAR(60) NULL,
    product_name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'piece',
    buying_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    opening_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY supplier_products_supplier_index (supplier_id),
    KEY supplier_products_name_index (product_name),
    CONSTRAINT supplier_products_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS other_income (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(20) NOT NULL,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(190) NULL,
    role_position VARCHAR(120) NOT NULL,
    join_date DATE NOT NULL,
    salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    address TEXT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY employees_code_unique (employee_code),
    KEY employees_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_salaries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    salary_month DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    advance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    loan_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    unpaid_leave_days DECIMAL(8,2) NOT NULL DEFAULT 0,
    attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    receipt_no VARCHAR(40) NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY employee_salary_month_unique (employee_id, salary_month),
    UNIQUE KEY employee_salary_receipt_unique (receipt_no),
    CONSTRAINT employee_salaries_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_advances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    advance_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    status ENUM('unsettled','settled') NOT NULL DEFAULT 'unsettled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT employee_advances_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_loans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    loan_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    installment DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    status ENUM('active','settled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT employee_loans_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_status ENUM('present','absent','leave','half_day') NOT NULL DEFAULT 'present',
    check_in TIME NULL,
    check_out TIME NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY employee_attendance_day_unique (employee_id, attendance_date),
    KEY employee_attendance_date_index (attendance_date),
    KEY employee_attendance_status_index (attendance_status),
    CONSTRAINT employee_attendance_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bays (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bay_name VARCHAR(80) NOT NULL,
    status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    assigned_employee_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY bays_name_unique (bay_name),
    KEY bays_status_index (status),
    CONSTRAINT bays_employee_fk FOREIGN KEY (assigned_employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`name`, `email`, `role`, `password`)
VALUES
('Garage Administrator', 'admin@deshappriyamotors.test', 'administrator', '$2y$10$KKscRXRVulvlZBPWhNV/QukjXYSIk8VdwHTpN9Nx35TgA2pjmJlYG'),
('Garage Cashier', 'cashier@deshappriyamotors.test', 'cashier', '$2y$10$jdWdnfuqI75ylEBcenK9LOQGnbzV9x/KLwr7UwjJaUxezCrBX9IHq')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `role` = VALUES(`role`);

CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_code` VARCHAR(20) NOT NULL,
    `customer_type` ENUM('individual', 'company', 'walk_in') NOT NULL DEFAULT 'individual',
    `name` VARCHAR(160) NOT NULL,
    `contact_number` VARCHAR(40) NOT NULL,
    `secondary_contact_number` VARCHAR(40) NULL,
    `email` VARCHAR(190) NULL,
    `nic_or_registration` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `notes` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customers_code_unique` (`customer_code`),
    KEY `customers_contact_index` (`contact_number`),
    KEY `customers_email_index` (`email`),
    KEY `customers_type_index` (`customer_type`),
    KEY `customers_status_index` (`status`),
    KEY `customers_created_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_number` VARCHAR(40) NOT NULL,
    `vehicle_type` VARCHAR(60) NOT NULL,
    `year` SMALLINT UNSIGNED NULL,
    `fuel_type` VARCHAR(30) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `make` VARCHAR(100) NULL,
    `model` VARCHAR(100) NOT NULL,
    `colour` VARCHAR(60) NULL,
    `engine_number` VARCHAR(100) NULL,
    `chassis_number` VARCHAR(100) NULL,
    `current_mileage` DECIMAL(12,2) NULL,
    `next_service_mileage` DECIMAL(12,2) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `vehicles_customer_index` (`customer_id`),
    KEY `vehicles_number_index` (`vehicle_number`),
    CONSTRAINT `vehicles_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_no VARCHAR(30) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    requested_service VARCHAR(190) NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY appointments_no_unique (appointment_no),
    KEY appointments_customer_index (customer_id),
    KEY appointments_vehicle_index (vehicle_id),
    KEY appointments_date_index (appointment_date),
    KEY appointments_status_index (status),
    CONSTRAINT appointments_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT appointments_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
