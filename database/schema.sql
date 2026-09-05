CREATE DATABASE IF NOT EXISTS `deshappriya_motors` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `deshappriya_motors`;

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

CREATE TABLE IF NOT EXISTS `job_cards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_card_no` VARCHAR(30) NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `complaint` TEXT NULL,
    `requested_work` TEXT NULL,
    `bay` VARCHAR(60) NOT NULL,
    `expected_delivery_date` DATE NOT NULL,
    `priority` ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    `mechanic` VARCHAR(120) NULL,
    `notes` TEXT NULL,
    `status` ENUM('pending', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `job_cards_no_unique` (`job_card_no`),
    KEY `job_cards_customer_index` (`customer_id`),
    KEY `job_cards_vehicle_index` (`vehicle_id`),
    KEY `job_cards_status_index` (`status`),
    CONSTRAINT `job_cards_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
    CONSTRAINT `job_cards_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
    CONSTRAINT `job_cards_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_card_services` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_card_id` BIGINT UNSIGNED NOT NULL,
    `service_name` VARCHAR(160) NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `job_card_services_job_index` (`job_card_id`),
    CONSTRAINT `job_card_services_job_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_card_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_card_id` BIGINT UNSIGNED NOT NULL,
    `item_type` ENUM('part', 'service') NOT NULL DEFAULT 'part',
    `item_name` VARCHAR(160) NOT NULL,
    `item_code` VARCHAR(60) NULL,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `job_card_items_job_index` (`job_card_id`),
    CONSTRAINT `job_card_items_job_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
