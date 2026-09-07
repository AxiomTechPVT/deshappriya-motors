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

CREATE TABLE IF NOT EXISTS stock_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    part_code VARCHAR(60) NOT NULL,
    part_name VARCHAR(190) NOT NULL,
    category VARCHAR(100) NULL,
    brand VARCHAR(100) NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'PCS',
    buying_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 10,
    supplier_id BIGINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY stock_items_code_unique (part_code), KEY stock_items_category_index (category), KEY stock_items_brand_index (brand), KEY stock_items_qty_index (stock_qty), CONSTRAINT stock_items_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, purchase_no VARCHAR(30) NOT NULL, supplier_id BIGINT UNSIGNED NULL, purchase_date DATE NOT NULL, invoice_no VARCHAR(80) NULL, payment_status ENUM('paid','due','partial') NOT NULL DEFAULT 'paid', paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0, balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0, total_amount DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('received','cancelled') NOT NULL DEFAULT 'received', notes TEXT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY stock_purchases_no_unique (purchase_no), KEY stock_purchases_date_index (purchase_date), KEY stock_purchases_supplier_index (supplier_id), CONSTRAINT stock_purchases_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL, CONSTRAINT stock_purchases_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_purchase_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, purchase_id BIGINT UNSIGNED NOT NULL, stock_item_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(12,2) NOT NULL DEFAULT 0, unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0, amount DECIMAL(12,2) NOT NULL DEFAULT 0, buying_price DECIMAL(12,2) NOT NULL DEFAULT 0, selling_price DECIMAL(12,2) NULL, line_total DECIMAL(12,2) NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY stock_purchase_items_purchase_index (purchase_id), KEY stock_purchase_items_stock_index (stock_item_id), CONSTRAINT stock_purchase_items_purchase_fk FOREIGN KEY (purchase_id) REFERENCES stock_purchases (id) ON DELETE CASCADE, CONSTRAINT stock_purchase_items_stock_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    stock_purchase_item_id BIGINT UNSIGNED NULL,
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_remaining DECIMAL(12,2) NOT NULL,
    buying_price DECIMAL(12,2) NOT NULL,
    selling_price_at_purchase DECIMAL(12,2) NULL,
    purchase_date DATE NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    batch_reference VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY stock_batches_item_index (stock_item_id),
    KEY stock_batches_purchase_item_index (stock_purchase_item_id),
    KEY stock_batches_date_index (purchase_date),
    KEY stock_batches_reference_index (batch_reference),
    CONSTRAINT stock_batches_item_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items (id) ON DELETE CASCADE,
    CONSTRAINT stock_batches_purchase_item_fk FOREIGN KEY (stock_purchase_item_id) REFERENCES stock_purchase_items (id) ON DELETE SET NULL,
    CONSTRAINT stock_batches_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('opening_stock','restock','job_usage','sale','adjustment_in','adjustment_out','return') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    quantity_before DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity_after DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    reference_type VARCHAR(40) NULL,
    reference_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY stock_movements_item_index (stock_item_id),
    KEY stock_movements_type_index (movement_type),
    KEY stock_movements_reference_index (reference_type, reference_id),
    CONSTRAINT stock_movements_item_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items (id) ON DELETE CASCADE,
    CONSTRAINT stock_movements_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_batch_consumptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    stock_batch_id BIGINT UNSIGNED NOT NULL,
    reference_type VARCHAR(40) NOT NULL,
    reference_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY stock_batch_consumptions_item_index (stock_item_id),
    KEY stock_batch_consumptions_batch_index (stock_batch_id),
    CONSTRAINT stock_batch_consumptions_item_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items (id) ON DELETE CASCADE,
    CONSTRAINT stock_batch_consumptions_batch_fk FOREIGN KEY (stock_batch_id) REFERENCES stock_batches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_no VARCHAR(30) NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    expense_type ENUM('general','external_part') NOT NULL DEFAULT 'general',
    reference_no VARCHAR(100) NULL,
    description VARCHAR(255) NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    vendor_supplier_name VARCHAR(160) NULL,
    job_card_id BIGINT UNSIGNED NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    payment_terms ENUM('cash','credit','partial') NOT NULL DEFAULT 'cash',
    payment_method ENUM('cash','card','bank','cheque','other') NOT NULL DEFAULT 'cash',
    location VARCHAR(100) NULL,
    bill_file VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_date DATE NULL,
    payment_note TEXT NULL,
    notes TEXT NULL,
    status ENUM('draft','pending','paid','partial','due','cancelled') NOT NULL DEFAULT 'pending',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY expenses_no_unique (expense_no), KEY expenses_date_index (expense_date), KEY expenses_category_index (category), KEY expenses_status_index (status),
    CONSTRAINT expenses_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
    CONSTRAINT expenses_user_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(190) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id), KEY expense_items_expense_index (expense_id),
    CONSTRAINT expense_items_expense_fk FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_code VARCHAR(30) NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY expense_categories_code_unique (category_code), UNIQUE KEY expense_categories_name_unique (category_name),
    CONSTRAINT expense_categories_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_external_parts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_id BIGINT UNSIGNED NOT NULL,
    job_card_id BIGINT UNSIGNED NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    part_name VARCHAR(190) NOT NULL,
    part_code VARCHAR(80) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_id BIGINT UNSIGNED NULL,
    vendor_name VARCHAR(160) NULL,
    invoice_no VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY expense_external_parts_expense_index (expense_id),
    CONSTRAINT expense_external_parts_expense_fk FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    CONSTRAINT expense_external_parts_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    CONSTRAINT expense_external_parts_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_no VARCHAR(30) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    mileage DECIMAL(12,2) NULL,
    engine_number VARCHAR(100) NULL,
    chassis_number VARCHAR(100) NULL,
    estimate_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    service_type VARCHAR(190) NOT NULL,
    notes TEXT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    service_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 18,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY estimates_no_unique (estimate_no),
    KEY estimates_customer_index (customer_id),
    KEY estimates_vehicle_index (vehicle_id),
    KEY estimates_date_index (estimate_date),
    KEY estimates_status_index (status),
    CONSTRAINT estimates_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT estimates_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(190) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY estimate_items_estimate_index (estimate_id),
    CONSTRAINT estimate_items_estimate_fk FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_cards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_card_no VARCHAR(30) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    bay_name VARCHAR(80) NULL,
    mechanic_id BIGINT UNSIGNED NULL,
    complaint TEXT NULL,
    requested_work TEXT NULL,
    notes TEXT NULL,
    expected_delivery_date DATE NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('pending','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    service_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','bank','other') NULL,
    created_by BIGINT UNSIGNED NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY job_cards_no_unique (job_card_no),
    KEY job_cards_customer_index (customer_id), KEY job_cards_vehicle_index (vehicle_id),
    KEY job_cards_status_index (status), KEY job_cards_date_index (created_at),
    CONSTRAINT job_cards_customer_fk FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT job_cards_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    CONSTRAINT job_cards_mechanic_fk FOREIGN KEY (mechanic_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT job_cards_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_card_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_card_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('part','service','manual') NOT NULL DEFAULT 'manual',
    stock_item_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NULL,
    item_name VARCHAR(190) NOT NULL,
    item_code VARCHAR(80) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY job_card_items_card_index (job_card_id),
    CONSTRAINT job_card_items_card_fk FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    CONSTRAINT job_card_items_stock_fk FOREIGN KEY (stock_item_id) REFERENCES stock_items(id) ON DELETE SET NULL,
    CONSTRAINT job_card_items_service_fk FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_card_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_card_id BIGINT UNSIGNED NOT NULL,
    receipt_no VARCHAR(40) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
    change_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','bank','other') NOT NULL DEFAULT 'cash',
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY job_card_payments_receipt_unique (receipt_no),
    KEY job_card_payments_card_index (job_card_id),
    CONSTRAINT job_card_payments_card_fk FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    CONSTRAINT job_card_payments_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_card_performance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_card_id BIGINT UNSIGNED NOT NULL,
    mechanic_id BIGINT UNSIGNED NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
    recorded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY job_card_performance_card_unique (job_card_id),
    KEY job_card_performance_mechanic_index (mechanic_id),
    CONSTRAINT job_card_performance_card_fk FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    CONSTRAINT job_card_performance_mechanic_fk FOREIGN KEY (mechanic_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT job_card_performance_user_fk FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY service_categories_name_unique (category_name),
    KEY service_categories_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_code VARCHAR(30) NOT NULL,
    service_name VARCHAR(190) NOT NULL,
    service_category_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    duration_minutes INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY services_code_unique (service_code),
    KEY services_name_index (service_name), KEY services_category_index (service_category_id), KEY services_status_index (status),
    CONSTRAINT services_category_fk FOREIGN KEY (service_category_id) REFERENCES service_categories(id) ON DELETE SET NULL,
    CONSTRAINT services_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
